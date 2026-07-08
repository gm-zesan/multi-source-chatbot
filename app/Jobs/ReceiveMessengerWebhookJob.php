<?php

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

        // Persist conversation and message in a transaction
        $result = DB::transaction(function () use ($account, $data) {
            $wasRecentlyCreated = false;

            $conversation = Conversation::firstOrCreate(
                [
                    'channel_account_id' => $account->id,
                    'external_user_id'   => $data['external_user_id'],
                ],
                [
                    'customer_name'   => $data['customer_name'],
                    'customer_avatar' => $data['customer_avatar'],
                    'status'          => 'open',
                    'last_direction'  => 'inbound',
                ],
            );

            if ($conversation->wasRecentlyCreated) {
                $wasRecentlyCreated = true;
            }

            // Ignore duplicate messages by external_message_id
            if (! empty($data['external_message_id'])) {
                $exists = Message::where('conversation_id', $conversation->id)
                    ->where('external_message_id', $data['external_message_id'])
                    ->exists();

                if ($exists) {
                    Log::info('[WebhookJob] Duplicate message ignored', [
                        'conversation_id' => $conversation->id,
                        'external_message_id' => $data['external_message_id'],
                    ]);
                    return ['conversation' => $conversation, 'message' => null, 'was_recently_created' => $wasRecentlyCreated ?: false];
                }
            }

            $message = Message::create([
                'conversation_id'     => $conversation->id,
                'external_message_id' => $data['external_message_id'],
                'direction'           => 'inbound',
                'type'                => $data['type'] ?? 'text',
                'body'                => $data['text'] ?? '',
                'metadata'            => $data,
            ]);

            $conversation->update([
                'last_message'    => $data['text'] ?? '',
                'last_message_at' => now(),
                'last_direction'  => 'inbound',
                'unread_count'    => DB::raw('unread_count + 1'),
            ]);

            Log::info('[WebhookJob] Message saved', [
                'conversation_id' => $conversation->id,
                'message_id'      => $message->id,
            ]);

            return [
                'conversation' => $conversation,
                'message'      => $message,
                'was_recently_created' => $wasRecentlyCreated ?: false,
            ];
        });

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
}
