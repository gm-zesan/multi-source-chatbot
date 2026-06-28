<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        return response('EVENT_RECEIVED', 200);
    }
}
