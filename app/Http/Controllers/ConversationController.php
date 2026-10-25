<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\CustomerSupportService;
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
    ) {}

    /**
     * Conversation List
     */
    public function index(): View
    {
        $user = Auth::user();
        $query = Conversation::with(['channelAccount.channel'])->latest('last_message_at');

        if ($user && ! $user->hasRole(RoleEnum::SUPERADMIN->value)) {
            $query->whereHas('channelAccount', fn ($q) => $q->where('workspace_id', (int) $user->workspace_id));
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

        return view('admin.conversations.show', compact('conversations', 'conversation'));
    }

    /**
     * Send Manual Staff Reply
     */
    public function reply(SendMessageRequest $request, Conversation $conversation): RedirectResponse
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

        return redirect()->route('admin.conversations.show', $conversation)
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

        $supportResult = $this->customerSupportService->handleQuery(
            query: $prompt,
            workspaceId: $workspaceId,
            conversation: $conversation,
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
}
