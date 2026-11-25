<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ConversationCreated;
use App\Events\IncomingMessageReceived;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\ChannelManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiveMessengerWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Maximum number of allowed exceptions.
     */
    public int $maxExceptions = 3;

    /**
     * The backoff strategy.
     *
     * @return int[]
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     *
     * @param int    $channelAccountId
     * @param string $channel
     * @param array  $payload         Parsed webhook payload from the driver.
     */
    public function __construct(
        public readonly int $channelAccountId,
        public readonly string $channel,
        public readonly array $payload,
    ) {
        $this->connection = 'database';
        $this->queue = 'messenger';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('[WebhookJob] Processing incoming message', [
            'channel'    => $this->channel,
            'account_id' => $this->channelAccountId,
            'msg_id'     => $this->payload['external_message_id'] ?? null,
        ]);

        // Resolve account
        $account = ChannelAccount::with('channel')->find($this->channelAccountId);

        if (! $account) {
            Log::error('[WebhookJob] Channel account not found', [
                'account_id' => $this->channelAccountId,
            ]);
            return;
        }

        // Fetch customer profile via channel driver
        $driver = ChannelManager::driver($this->channel);

        try {
            $profile = $driver->getUserProfile($account, $this->payload['external_user_id']);
        } catch (\Throwable $e) {
            Log::warning('[WebhookJob] Failed to fetch profile, continuing', [
                'error' => $e->getMessage(),
            ]);
            $profile = [];
        }

        $data = array_merge($this->payload, [
            'customer_name'   => $profile['name'] ?? $this->payload['customer_name'] ?? null,
            'customer_avatar' => $profile['profile_pic'] ?? $this->payload['customer_avatar'] ?? null,
        ]);

        // 1. Resolve or create conversation with concurrency collision protection
        $conversation = null;
        $wasRecentlyCreated = false;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $existing = Conversation::where('channel_account_id', $account->id)
                ->where('external_user_id', $data['external_user_id'])
                ->first();

            if ($existing) {
                $conversation = $existing;
                break;
            }

            try {
                $conversation = Conversation::create([
                    'channel_account_id' => $account->id,
                    'external_user_id'   => $data['external_user_id'],
                    'customer_name'      => $data['customer_name'],
                    'customer_avatar'    => $data['customer_avatar'],
                    'status'             => 'open',
                    'last_direction'     => 'inbound',
                ]);
                $wasRecentlyCreated = true;
                break;
            } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
                // If another concurrent worker created the conversation in this microsecond, resolve it
                $found = Conversation::where('channel_account_id', $account->id)
                    ->where('external_user_id', $data['external_user_id'])
                    ->first();
                if ($found) {
                    $conversation = $found;
                    break;
                }
                if ($attempt === 5) {
                    throw $e;
                }
                usleep(25000); // Wait 25ms for concurrent insert to commit
            }
        }

        if (! $conversation) {
            $conversation = Conversation::where('channel_account_id', $account->id)
                ->where('external_user_id', $data['external_user_id'])
                ->firstOrFail();
        }

        // 2. Persist message in a transaction with atomic duplicate protection
        $result = DB::transaction(function () use ($account, $conversation, $data, $wasRecentlyCreated) {
            $externalMessageId = ! empty($data['external_message_id']) ? (string) $data['external_message_id'] : null;

            // Ignore duplicate messages by external_message_id (Fast path check)
            if ($externalMessageId !== null) {
                $exists = Message::where('conversation_id', $conversation->id)
                    ->where('external_message_id', $externalMessageId)
                    ->exists();

                if ($exists) {
                    Log::info('[WebhookJob] Duplicate message ignored (sequential check)', [
                        'conversation_id' => $conversation->id,
                        'external_message_id' => $externalMessageId,
                    ]);
                    return ['conversation' => $conversation, 'message' => null, 'was_recently_created' => $wasRecentlyCreated ?: false];
                }
            }

            try {
                $message = Message::create([
                    'conversation_id'     => $conversation->id,
                    'external_message_id' => $externalMessageId,
                    'direction'           => 'inbound',
                    'type'                => $data['type'] ?? 'text',
                    'body'                => $data['text'] ?? '',
                    'metadata'            => $data,
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // Strictly guard: only absorb if violation is specifically on external_message_id unique key
                $msg = $e->getMessage();
                $isTargetUniqueConstraint = str_contains($msg, 'messages_conv_external_id_unique')
                    || str_contains($msg, 'external_message_id')
                    || str_contains($msg, 'Duplicate entry')
                    || str_contains($msg, 'UNIQUE constraint failed');

                if ($isTargetUniqueConstraint) {
                    Log::warning('[WebhookJob] Concurrent duplicate message caught by unique constraint', [
                        'conversation_id'     => $conversation->id,
                        'external_message_id' => $externalMessageId,
                    ]);
                    return ['conversation' => $conversation, 'message' => null, 'was_recently_created' => false];
                }
                // Any other unique violation must throw visibly
                throw $e;
            } catch (\Illuminate\Database\QueryException $e) {
                // Strictly guard: only absorb MySQL 1062 (ER_DUP_ENTRY) or SQLite unique key violations specifically for external_message_id
                $errorInfo = $e->errorInfo ?? [];
                $sqlErrorCode = $errorInfo[1] ?? 0;
                $msg = $e->getMessage();

                $isSpecificDuplicateKey = ($sqlErrorCode === 1062 && (str_contains($msg, 'messages_conv_external_id_unique') || str_contains($msg, 'external_message_id') || str_contains($msg, 'Duplicate entry')))
                    || (str_contains($msg, 'UNIQUE constraint failed') && (str_contains($msg, 'external_message_id') || str_contains($msg, 'messages.conversation_id')));

                if ($isSpecificDuplicateKey) {
                    Log::warning('[WebhookJob] Concurrent duplicate message caught by specific duplicate key guard', [
                        'conversation_id'     => $conversation->id,
                        'external_message_id' => $externalMessageId,
                    ]);
                    return ['conversation' => $conversation, 'message' => null, 'was_recently_created' => false];
                }

                // Any other integrity/schema failure (e.g. FK 1452, NOT NULL 1048) must fail visibly
                throw $e;
            }

            $isMedia = ($data['type'] ?? 'text') !== 'text' || ! empty($data['attachments']);
            $convUpdates = [
                'last_message'    => $data['text'] ?? '',
                'last_message_at' => now(),
                'last_direction'  => 'inbound',
                'unread_count'    => DB::raw('unread_count + 1'),
            ];
            if ($isMedia) {
                $existingTags = is_array($conversation->ai_tags) ? $conversation->ai_tags : (json_decode($conversation->ai_tags ?? '[]', true) ?: []);
                if (! in_array('media_received', $existingTags, true)) {
                    $existingTags[] = 'media_received';
                }
                $convUpdates['ai_tags'] = $existingTags;
                $convUpdates['priority'] = 'high';
            }

            $conversation->update($convUpdates);

            Log::info('[WebhookJob] Message saved', [
                'conversation_id' => $conversation->id,
                'message_id'      => $message->id,
            ]);

            return [
                'conversation' => $conversation,
                'message'      => $message,
                'was_recently_created' => $wasRecentlyCreated ?: false,
            ];
        }, 3);

        $conversation = $result['conversation'];
        $message = $result['message'];

        // Dispatch events for downstream processing
        if ($message !== null) {
            // Dispatch IncomingMessageReceived — both CRM and FAQ listen to this
            IncomingMessageReceived::dispatch(
                conversation: $conversation,
                message: $message,
                account: $account,
                rawPayload: $this->payload,
            );

            Log::info('[WebhookJob] IncomingMessageReceived event dispatched', [
                'conversation_id' => $conversation->id,
                'message_id'      => $message->id,
            ]);
        }

        // Also dispatch ConversationCreated if brand new
        if ($result['was_recently_created']) {
            if ($account->workspace_id) {
                try {
                    app(\App\Services\Subscription\SubscriptionQuotaService::class)->recordConversationUsage((int) $account->workspace_id);
                } catch (\Throwable) {}
            }

            ConversationCreated::dispatch(
                conversation: $conversation,
                rawPayload: $this->payload,
            );

            Log::info('[WebhookJob] ConversationCreated event dispatched', [
                'conversation_id' => $conversation->id,
            ]);
        }

        Log::info('[WebhookJob] Completed', [
            'conversation_id' => $conversation->id,
            'message_id'      => $message?->id,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[WebhookJob] Job failed after all retries', [
            'channel_account_id' => $this->channelAccountId,
            'channel'            => $this->channel,
            'error'              => $exception->getMessage(),
        ]);
    }
}
