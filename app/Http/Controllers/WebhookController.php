<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {

        Log::info($request->all());

        return response('EVENT_RECEIVED', 200);


        // Facebook webhook verification
        if ($request->isMethod('GET')) {

            $mode = $request->query('hub_mode');
            $token = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');

            if (
                $mode === 'subscribe' &&
                $token === env('FB_VERIFY_TOKEN')
            ) {
                return response($challenge, 200);
            }

            return response('Forbidden', 403);
        }

        // Incoming Messenger events
        Log::info($request->all());

        return response('EVENT_RECEIVED', 200);
    }
}
