<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Services\ConversationService;
use App\Support\ChannelManager;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    public function __construct(protected ConversationService $conversationService) {
    }

    /**
     * Conversation List
     */
    public function index()
    {
        $conversations = Conversation::with(['channelAccount.channel'])->latest('last_message_at')->paginate(20);

        return view('conversations.index',compact('conversations'));
    }

    /**
     * Conversation Details
     */
    public function show(Conversation $conversation)
    {
        $conversations = Conversation::with(['channelAccount.channel'])->latest('last_message_at')->paginate(20);
        
        $conversation->load(['messages','channelAccount.channel']);

        return view('conversations.show',compact('conversations','conversation'));
    }

    /**
     * Send Reply
     */
    public function reply(SendMessageRequest $request,Conversation $conversation) {
        // Load relationships (if not already loaded)
        $conversation->load('channelAccount.channel');

        $message = $request->validated()['message'];

        // Resolve channel driver
        $driver = ChannelManager::driver($conversation->channelAccount->channel->slug);

        // Send message to external platform
        $response = $driver->send($conversation->channelAccount,$conversation,$message);
        
        Log::info('Message sent', [
            'conversation_id' => $conversation->id,
            'channel_account_id' => $conversation->channel_account_id,
            'message' => $message,
            'response' => $response,
        ]);

        // Save outgoing message
        $this->conversationService->saveOutgoing($conversation,$message,$response);

        return redirect()->back()->with('success', 'Message sent successfully.');
    }
}
