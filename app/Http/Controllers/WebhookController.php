<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WebhookController extends Controller
{

    public function handle(Request $request)
    {
        if ($request->isMethod('GET')) {

            if (
                $request->input('hub.mode') === 'subscribe' &&
                $request->input('hub.verify_token') === env('FB_VERIFY_TOKEN')
            ) {
                return response($request->input('hub.challenge'), 200);
            }

            return response('Forbidden', 403);
        }

        Log::channel('single')->info('Facebook Webhook', [
            'headers' => $request->headers->all(),
            'body'    => $request->getContent(),
            'json'    => $request->all(),
        ]);

        $messaging = $request->input('entry.0.messaging.0');

        $recipientId = $messaging['sender']['id'] ?? null;
        $messageText = $messaging['message']['text'] ?? '';

        if ($recipientId) {
            $response = Http::withToken(env('FB_PAGE_ACCESS_TOKEN'))
                ->post('https://graph.facebook.com/v25.0/me/messages', [
                    'recipient' => [
                        'id' => $recipientId,
                    ],
                    'message' => [
                        'text' => 'Laravel received: ' . $messageText,
                    ],
                ]);

            Log::info('Facebook Send API Response', $response->json());
        }


        return response('EVENT_RECEIVED', 200);
    }
}
