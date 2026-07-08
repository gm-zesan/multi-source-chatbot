<?php

namespace App\Http\Controllers;

use App\Jobs\ReceiveMessengerWebhookJob;
use App\Services\Chat\ChannelAccountResolver;
use App\Support\ChannelManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected ChannelAccountResolver $resolver,
    ) {}

    /**
     * Handle incoming webhook — ultra-thin.
     *
     * 1. Verify webhook (GET)
     * 2. Parse payload
     * 3. Dispatch a single queued job
     * 4. Return HTTP 200 immediately
     *
     * All business logic (persistence, CRM, FAQ, etc.) runs asynchronously
     * via jobs, events, and listeners on dedicated queues.
     */
    public function handle(Request $request)
    {
        $channel = 'facebook';
        $driver = ChannelManager::driver($channel);

        // Webhook verification (Meta/Facebook)
        if ($request->isMethod('GET')) {
            return $driver->verifyWebhook($request);
        }

        $payload = $request->all();

        // Parse webhook payload via channel driver
        $data = $driver->parseWebhook($payload);

        // Ignore non-message events (delivery, read, echo)
        if ($data === null) {
            return response('EVENT_IGNORED', 200);
        }

        // Resolve channel account ID synchronously (lightweight DB query)
        $account = $this->resolver->resolve(
            $channel,
            $driver->extractAccountId($payload),
        );

        // Dispatch a single job — everything else is event-driven
        ReceiveMessengerWebhookJob::dispatch(
            channelAccountId: $account->id,
            channel: $channel,
            payload: $data,
        );

        Log::info('[Webhook] Job dispatched', [
            'channel'      => $channel,
            'account_id'   => $account->id,
            'msg_id'       => $data['external_message_id'] ?? null,
        ]);

        return response('EVENT_RECEIVED', 200);
    }
}
