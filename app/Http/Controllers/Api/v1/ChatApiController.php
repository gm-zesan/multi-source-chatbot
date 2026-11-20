<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\v1;

use App\AI\Orchestration\CustomerSupportService;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\Subscription\SubscriptionQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatApiController extends Controller
{
    public function __construct(
        protected CustomerSupportService $supportService,
        protected SubscriptionQuotaService $quotaService,
    ) {}

    /**
     * Programmatic Chat Completion Endpoint via Developer REST API.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message'          => ['required', 'string', 'max:4000'],
            'conversation_id'  => ['nullable', 'integer'],
            'external_user_id' => ['nullable', 'string', 'max:120'],
            'customer_name'    => ['nullable', 'string', 'max:120'],
            'metadata'         => ['nullable', 'array'],
        ]);

        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');
        if (! $workspace) {
            return new JsonResponse([
                'success' => false,
                'error'   => 'workspace_not_found',
                'message' => 'No active workspace context associated with this request.',
            ], 400);
        }

        // 1. Enforce SaaS Subscription Quota
        if (! $this->quotaService->canCreateConversation($workspace->id)) {
            return new JsonResponse([
                'success'        => false,
                'error'          => 'quota_exceeded',
                'message'        => 'This workspace has reached its monthly AI conversation quota limit. Please upgrade your plan.',
                'quota_exceeded' => true,
            ], 429);
        }

        // 2. Resolve or Provision API Channel Account for this workspace
        $apiChannel = Channel::firstOrCreate(
            ['slug' => 'api'],
            ['name' => 'REST API', 'icon' => 'ri-terminal-box-line', 'is_active' => true]
        );

        $channelAccount = ChannelAccount::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'channel_id'   => $apiChannel->id,
            ],
            [
                'name'         => $workspace->name . ' Developer API',
                'display_name' => $workspace->name . ' API Client',
                'external_id'  => 'api_' . Str::random(12),
                'access_token' => 'api_channel_internal_token',
                'is_active'    => true,
            ]
        );

        // 3. Resolve or Create Conversation
        $externalUserId = !empty($validated['external_user_id']) 
            ? trim((string) $validated['external_user_id']) 
            : 'api_client_' . Str::random(10);
        $customerName = !empty($validated['customer_name']) 
            ? trim((string) $validated['customer_name']) 
            : 'API Client User';

        $conversation = null;
        if (!empty($validated['conversation_id'])) {
            $conversation = Conversation::where('id', (int) $validated['conversation_id'])
                ->where('channel_account_id', $channelAccount->id)
                ->first();
        }

        if (! $conversation) {
            $conversation = Conversation::create([
                'channel_account_id' => $channelAccount->id,
                'external_user_id'   => $externalUserId,
                'customer_name'      => $customerName,
                'status'             => 'open',
                'priority'           => 'normal',
                'last_direction'     => 'inbound',
                'last_message'       => $validated['message'],
                'last_message_at'    => now(),
            ]);
        } else {
            $conversation->update([
                'last_direction'  => 'inbound',
                'last_message'    => $validated['message'],
                'last_message_at' => now(),
            ]);
        }

        // 4. Record Inbound Message
        $inboundMessage = Message::create([
            'conversation_id'     => $conversation->id,
            'external_message_id' => 'api_msg_' . Str::uuid(),
            'direction'           => 'inbound',
            'type'                => 'text',
            'body'                => $validated['message'],
            'status'              => 'delivered',
        ]);

        // 5. Orchestrate Synchronous AI Decision & Knowledge Retrieval
        $outboundMessage = $this->supportService->handleConversation($conversation, $inboundMessage);

        // 6. Record Subscription Quota Usage
        $this->quotaService->recordConversationUsage($workspace->id);

        $reply = $outboundMessage?->body 
            ?? 'Thank you. We have received your query and will reply shortly.';

        return new JsonResponse([
            'success' => true,
            'data'    => [
                'conversation_id' => $conversation->id,
                'message_id'      => $outboundMessage?->id,
                'reply'           => $reply,
                'channel'         => 'api',
                'workspace'       => [
                    'id'   => $workspace->id,
                    'name' => $workspace->name,
                ],
                'timestamp'       => now()->toIso8601String(),
            ],
        ]);
    }
}
