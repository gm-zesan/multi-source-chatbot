<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Models\User;
use App\AI\Decision\UnifiedOrchestrator;
use App\AI\Orchestration\CustomerSupportService;
use App\Services\Chat\ConversationService;
use App\Services\FAQ\FAQSearch;
use App\Support\ChannelManager;
use App\Http\Controllers\Concerns\AuthorizesConversationAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ConversationController extends Controller
{
    use AuthorizesConversationAccess;

    public function __construct(
        protected ConversationService $conversationService,
        protected FAQSearch $faqSearch,
        protected CustomerSupportService $customerSupportService,
        protected UnifiedOrchestrator $orchestrator,
    ) {}

    /**
     * Conversation List
     */
    public function index(\Illuminate\Http\Request $request): View
    {
        $user = Auth::user();
        $query = Conversation::with(['channelAccount.channel', 'assignedTo'])->latest('last_message_at');

        if ($user && ! $user->hasRole(RoleEnum::SUPERADMIN->value)) {
            $query->whereHas('channelAccount', fn ($q) => $q->where('workspace_id', (int) $user->workspace_id));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('assigned_to')) {
            $assignedVal = $request->input('assigned_to');
            if ($assignedVal === 'me' && $user) {
                $query->where('assigned_to_user_id', $user->id);
            } elseif ($assignedVal === 'unassigned') {
                $query->whereNull('assigned_to_user_id');
            } else {
                $query->where('assigned_to_user_id', (int) $assignedVal);
            }
        }

        $conversations = $query->paginate(20);

        return view('admin.conversations.index', compact('conversations'));
    }

    /**
     * Conversation Details
     */
    public function show(Conversation $conversation): View
    {
        $conversation->load(['messages', 'channelAccount.channel']);
        $this->authorizeConversation($conversation);

        $user = Auth::user();
        $query = Conversation::with(['channelAccount.channel'])->latest('last_message_at');
        if ($user && ! $user->hasRole(RoleEnum::SUPERADMIN->value)) {
            $query->whereHas('channelAccount', fn ($q) => $q->where('workspace_id', (int) $user->workspace_id));
        }
        $conversations = $query->paginate(20);

        $workspaceId = $conversation->channelAccount?->workspace_id;
        if ($workspaceId) {
            $teamMembers = User::where(function ($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId)
                  ->orWhereHas('roles', fn ($r) => $r->where('name', RoleEnum::SUPERADMIN->value));
            })->orderBy('name')->get();
        } else {
            $teamMembers = User::orderBy('name')->get();
        }

        return view('admin.conversations.show', compact('conversations', 'conversation', 'teamMembers'));
    }

    /**
     * Send Manual Staff Reply
     */
    public function reply(SendMessageRequest $request, Conversation $conversation): \Symfony\Component\HttpFoundation\Response
    {
        // Load relationships (if not already loaded)
        $conversation->load('channelAccount.channel');
        $this->authorizeConversation($conversation);

        $message = $request->validated()['message'];

        // Resolve channel driver
        $driver = ChannelManager::driver($conversation->channelAccount->channel->slug);

        // Send message to external platform
        $response = $driver->send($conversation->channelAccount, $conversation, $message);

        // Save staff outgoing message
        $this->conversationService->saveOutgoing(
            conversation: $conversation,
            message: $message,
            response: $response,
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'status' => 'Reply sent successfully!']);
        }

        return redirect()->route('conversations.show', $conversation)
            ->with('status', 'Reply sent successfully!');
    }

    /**
     * Trigger AI Agent Reply for a Conversation Thread.
     */
    public function aiReply(Conversation $conversation): RedirectResponse
    {
        $conversation->loadMissing('channelAccount.channel');

        $workspaceId = ($conversation->channelAccount?->workspace_id !== null && (int) $conversation->channelAccount->workspace_id > 0)
            ? (int) $conversation->channelAccount->workspace_id
            : null;

        if ($workspaceId === null) {
            Log::error('[ConversationController] Cannot generate AI reply: missing or invalid workspace association', [
                'conversation_id'    => $conversation->id,
                'channel_account_id' => $conversation->channel_account_id,
            ]);

            return redirect()->back()->with('error', 'Cannot generate AI response: conversation has no valid workspace association.');
        }

        $this->authorizeWorkspace($workspaceId);

        $lastInbound = $conversation->messages()->where('direction', 'inbound')->latest('id')->first();
        $prompt = $lastInbound?->body ?? 'Hello, how can I help you today?';

        $channelSlug = $conversation->channelAccount?->channel?->slug ?? 'web';
        $isCustomer = in_array(mb_strtolower($channelSlug), ['facebook', 'messenger', 'whatsapp', 'instagram', 'telegram', 'web', 'website', 'web_widget', 'widget', 'public'], true);

        $supportResult = $this->orchestrator->process(
            query: $prompt,
            workspaceId: $workspaceId,
            user: Auth::user(),
            conversation: $conversation,
            channelSlug: $channelSlug,
            isCustomer: $isCustomer,
        );

        $replyText = $supportResult['reply'] ?? '';

        if (trim($replyText) !== '') {
            $deliveryResponse = [];
            if ($conversation->channelAccount?->channel) {
                try {
                    $driver = ChannelManager::driver($conversation->channelAccount->channel->slug);
                    $deliveryResponse = $driver->send($conversation->channelAccount, $conversation, $replyText) ?? [];
                } catch (\Throwable $sendError) {
                    Log::warning('[ConversationController] Failed to deliver AI message to channel', [
                        'conversation_id' => $conversation->id,
                        'error'           => $sendError->getMessage(),
                    ]);
                }
            }

            $this->customerSupportService->saveOutboundReply(
                conversation: $conversation,
                replyText: $replyText,
                deliveryResponse: array_merge($deliveryResponse, [
                    'route' => $supportResult['route'] ?? 'knowledge',
                    'confidence' => $supportResult['confidence'] ?? 1.0,
                    'answered' => $supportResult['answered'] ?? false,
                    'total_time_ms' => $supportResult['routing_telemetry']['total_e2e_ms'] ?? null,
                    'answerability_decision' => $supportResult['answerability_decision'] ?? null,
                    'routing_telemetry' => $supportResult['routing_telemetry'] ?? [],
                ]),
            );
        }

        return redirect()->back()->with('success', 'AI response generated successfully.');
    }

    /**
     * Assign Conversation to a User / Agent.
     */
    public function assign(\Illuminate\Http\Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeConversation($conversation);

        $validated = $request->validate([
            'assigned_to_user_id' => 'nullable|exists:users,id',
        ]);

        $assignedUserId = !empty($validated['assigned_to_user_id']) ? (int) $validated['assigned_to_user_id'] : null;

        // Verify assigned user belongs to the same workspace if set
        if ($assignedUserId !== null) {
            $assignedUser = User::find($assignedUserId);
            $workspaceId = $conversation->channelAccount?->workspace_id;
            $isSuperAdmin = Auth::user()?->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value);
            if (! $isSuperAdmin && $assignedUser && ! $assignedUser->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value) && (int) $assignedUser->workspace_id !== (int) $workspaceId) {
                abort(403, 'Cannot assign conversation to user from another workspace.');
            }
        }

        $conversation->update([
            'assigned_to_user_id' => $assignedUserId,
        ]);

        return redirect()->back()->with('status', 'Conversation assignment updated successfully.');
    }

    /**
     * Update Conversation Status (open, pending, resolved).
     */
    public function updateStatus(\Illuminate\Http\Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeConversation($conversation);

        $validated = $request->validate([
            'status'   => 'nullable|in:open,pending,in_progress,resolved,closed',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ]);

        $updateData = [];
        if (! empty($validated['status'])) {
            $status = $validated['status'];
            $updateData['status'] = $status;
            $updateData['resolved_at'] = in_array($status, ['resolved', 'closed'], true) ? now() : null;
        }

        if (! empty($validated['priority'])) {
            $updateData['priority'] = $validated['priority'];
        }

        if (! empty($updateData)) {
            $conversation->update($updateData);
        }

        return redirect()->back()->with('status', 'Conversation updated successfully.');
    }
}
