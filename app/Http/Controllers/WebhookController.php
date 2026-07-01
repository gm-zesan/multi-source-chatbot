<?php

namespace App\Http\Controllers;

use App\Services\ChannelAccountResolver;
use App\Services\ConversationService;
use App\Support\ChannelManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
        protected ChannelAccountResolver $resolver
    ) {}

    public function handle(Request $request)
    {
        $channel = 'facebook';
        $driver = ChannelManager::driver($channel);

        if ($request->isMethod('GET')) {
            return $driver->verifyWebhook($request);
        }

        Log::info('Webhook received', [
            'channel' => $channel,
            'payload' => $request->all(),
        ]);

        $payload = $request->all();

        // Parse first
        $data = $driver->parseWebhook($payload);

        // Ignore delivery/read/echo events
        if ($data === null) {
            return response('EVENT_IGNORED', 200);
        }

        // Resolve account
        $account = $this->resolver->resolve($channel,$driver->extractAccountId($payload));

        // Fetch customer profile
        $profile = $driver->getUserProfile(
            $account,
            $data['external_user_id']
        );

        $data['customer_name'] = $profile['name'] ?? null;
        $data['customer_avatar'] = $profile['profile_pic'] ?? null;

        // Save conversation/message
        $this->conversationService->saveIncoming($account, $data);

        return response('EVENT_RECEIVED', 200);
    }
}
