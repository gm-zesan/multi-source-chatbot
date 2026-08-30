<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Services\AI\CustomerSupportService;
use App\Services\Chat\ConversationService;
use App\Services\FAQ\FAQSearch;
use App\Support\ChannelManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ConversationController extends Controller
{
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
        $conversations = Conversation::with(['channelAccount.channel'])->latest('last_message_at')->paginate(20);

        return view('admin.conversations.index', compact('conversations'));
    }

    /**
     * Conversation Details
     */
    public function show(Conversation $conversation): View
    {
        $conversations = Conversation::with(['channelAccount.channel'])->latest('last_message_at')->paginate(20);

        $conversation->load(['messages', 'channelAccount.channel']);

        return view('admin.conversations.show', compact('conversations', 'conversation'));
    }

    /**
     * Send Manual Staff Reply
     */
    public function reply(SendMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        // Load relationships (if not already loaded)
        $conversation->load('channelAccount.channel');

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
        $conversation->load('channelAccount.channel');

        $lastInbound = $conversation->messages()->where('direction', 'inbound')->latest('id')->first();
        $prompt = $lastInbound?->body ?? 'Hello, how can I help you today?';
        $workspaceId = $conversation->channelAccount?->workspace_id;

        $replyText = $this->customerSupportService->generateReply(
            conversation: $conversation,
            query: $prompt,
            workspaceId: $workspaceId,
        );

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
                deliveryResponse: $deliveryResponse,
            );
        }

        return redirect()->back()->with('success', 'AI response generated successfully.');
    }
}
