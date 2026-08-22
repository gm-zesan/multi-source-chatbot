<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\AI\Agents\CustomerSupportAgent;
use App\AI\Tools\KnowledgeRetrievalTool;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
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

        Log::info('Message sent', [
            'conversation_id' => $conversation->id,
            'channel_account_id' => $conversation->channel_account_id,
            'message' => $message,
            'response' => $response,
        ]);

        // Save outgoing message
        $this->conversationService->saveOutgoing($conversation, $message, $response);

        return redirect()->back()->with('success', 'Message sent successfully.');
    }

    /**
     * Generate AI Agent Reply for Conversation.
     */
    public function aiReply(Conversation $conversation): RedirectResponse
    {
        $conversation->load('channelAccount.channel');

        $lastInbound = $conversation->messages()->where('direction', 'inbound')->latest('id')->first();
        $prompt = $lastInbound?->body ?? 'Hello, how can I help you today?';

        $retrievalTool = new KnowledgeRetrievalTool(
            faqSearch: $this->faqSearch,
            workspaceId: $conversation->channelAccount?->workspace_id,
        );

        $agent = new CustomerSupportAgent(
            conversation: $conversation,
            retrievalTool: $retrievalTool,
        );

        $provider = config('ai.default', 'openrouter');
        $model = config('ai.default_model', 'deepseek/deepseek-chat');

        $response = $agent->prompt($prompt, provider: $provider, model: $model);
        $replyText = (string) $response;

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

            $this->conversationService->saveOutgoing(
                conversation: $conversation,
                message: $replyText,
                response: array_merge($deliveryResponse, [
                    'source'     => 'customer_support_agent',
                    'provider'   => $provider,
                    'model'      => $model,
                    'usage'      => $response->usage ?? null,
                ]),
            );
        }

        return redirect()->back()->with('success', 'AI response generated successfully.');
    }
}
