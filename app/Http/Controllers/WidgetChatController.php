<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\AI\Orchestration\CustomerSupportService;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\Subscription\SubscriptionQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WidgetChatController extends Controller
{
    public function __construct(
        protected CustomerSupportService $supportService,
        protected SubscriptionQuotaService $quotaService,
    ) {}

    /**
     * Show Web Chat Widget Management & Live Preview Dashboard.
     */
    public function manage(Request $request): \Illuminate\View\View
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $user || (! $user->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value) && ! $user->hasRole(\App\Enums\RoleEnum::ADMIN->value))) {
            abort(403, 'Unauthorized. Only workspace business owners can manage the web widget.');
        }
        $workspaceId = $user->workspace_id ?? session('active_workspace_id', 1);
        $workspace = Workspace::find($workspaceId) ?? Workspace::first();

        $websiteChannel = Channel::firstOrCreate(
            ['slug' => 'website'],
            ['name' => 'Website', 'is_active' => true]
        );

        $channelAccount = ChannelAccount::where('workspace_id', $workspace?->id ?? 1)
            ->whereHas('channel', fn($q) => $q->whereIn('slug', ['website', 'web']))
            ->first();

        if (!$channelAccount && $workspace) {
            $channelAccount = ChannelAccount::create([
                'workspace_id' => $workspace->id,
                'channel_id'   => $websiteChannel->id,
                'name'         => $workspace->name . ' Web Widget',
                'external_id'  => 'widget_' . Str::random(12),
                'access_token' => 'widget_public_token',
                'is_active'    => true,
            ]);
        }

        $widgetUrl = url('/widget/chat.js');
        $chatEndpoint = url('/widget/chat');

        return view('admin.widget.manage', [
            'workspace'      => $workspace,
            'channelAccount' => $channelAccount,
            'widgetUrl'      => $widgetUrl,
            'chatEndpoint'   => $chatEndpoint,
        ]);
    }

    /**
     * Process synchronous chat interaction from the Web Chat Widget.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text'               => ['required', 'string', 'max:2000'],
            'visitor_id'         => ['required', 'string', 'max:100'],
            'visitor_name'       => ['nullable', 'string', 'max:100'],
            'channel_account_id' => ['nullable'],
            'workspace_id'       => ['nullable', 'integer'],
        ]);

        $visitorId = trim((string) $validated['visitor_id']);
        $text = trim((string) $validated['text']);
        $visitorName = !empty($validated['visitor_name']) ? trim((string) $validated['visitor_name']) : 'Web Visitor';

        // 1. Resolve Channel Account
        $channelAccount = null;
        if (!empty($validated['channel_account_id'])) {
            $channelAccount = ChannelAccount::find($validated['channel_account_id']);
        }

        if (!$channelAccount && !empty($validated['workspace_id'])) {
            $channelAccount = ChannelAccount::where('workspace_id', (int) $validated['workspace_id'])
                ->whereHas('channel', fn($q) => $q->whereIn('slug', ['website', 'web']))
                ->first();
        }

        if (!$channelAccount) {
            $channelAccount = ChannelAccount::whereHas('channel', fn($q) => $q->whereIn('slug', ['website', 'web']))->first();
        }

        // If still no channel account, find or create default website channel & account
        if (!$channelAccount) {
            $websiteChannel = Channel::firstOrCreate(
                ['slug' => 'website'],
                ['name' => 'Website', 'is_active' => true]
            );

            $workspace = !empty($validated['workspace_id']) ? Workspace::find($validated['workspace_id']) : Workspace::first();
            if (!$workspace) {
                return response()->json([
                    'success' => false,
                    'reply'   => 'Service temporarily unavailable: no active workspace found.',
                ], 503);
            }

            $channelAccount = ChannelAccount::create([
                'workspace_id' => $workspace->id,
                'channel_id'   => $websiteChannel->id,
                'name'         => 'Storefront Web Widget',
                'external_id'  => 'widget_' . Str::random(12),
                'access_token' => 'widget_public_token',
                'is_active'    => true,
            ]);
        }

        $workspaceId = (int) $channelAccount->workspace_id;

        // 2. Enforce SaaS Subscription Quota
        if (!$this->quotaService->canCreateConversation($workspaceId)) {
            return response()->json([
                'success'        => false,
                'reply'          => 'This organization has reached its conversation quota limit. Please contact support.',
                'quota_exceeded' => true,
            ], 429);
        }

        // 3. Find or Create Conversation
        $conversation = Conversation::firstOrCreate([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => $visitorId,
        ], [
            'customer_name'   => $visitorName,
            'status'          => 'open',
            'priority'        => 'normal',
            'last_direction'  => 'inbound',
            'last_message'    => $text,
            'last_message_at' => now(),
        ]);

        $conversation->update([
            'last_direction'  => 'inbound',
            'last_message'    => $text,
            'last_message_at' => now(),
        ]);

        // 4. Record Inbound Message
        $inboundMessage = Message::create([
            'conversation_id'     => $conversation->id,
            'external_message_id' => 'msg_' . Str::uuid(),
            'direction'           => 'inbound',
            'type'                => 'text',
            'body'                => $text,
            'status'              => 'delivered',
        ]);

        // 5. Orchestrate Synchronous AI Decision & Tool Execution
        $outboundMessage = $this->supportService->handleConversation($conversation, $inboundMessage);

        // 6. Record Subscription Quota Usage
        $this->quotaService->recordConversationUsage($workspaceId);

        $reply = $outboundMessage?->body ?? 'ধন্যবাদ! আপনার বার্তাটি আমরা পেয়েছি। আমাদের প্রতিনিধি শীঘ্রই আপনার সাথে যোগাযোগ করবেন।';

        return response()->json([
            'success'         => true,
            'reply'           => $reply,
            'conversation_id' => $conversation->id,
            'message_id'      => $outboundMessage?->id,
            'timestamp'       => now()->toIso8601String(),
        ]);
    }
}
