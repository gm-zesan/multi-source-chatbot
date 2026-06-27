<?php

namespace App\Http\Controllers;

use App\Models\ChatLog;
use App\Services\QueryExecutor;
use Illuminate\Http\Request;
use App\Services\QueryParser;
use App\Services\ResponseFormatter;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat');
    }

    public function send(
        Request $request,
        QueryParser $parser,
        QueryExecutor $executor,
        ResponseFormatter $formatter,
    ) {

        // ── Validate input ──
        $validated = $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $query = $validated['message'];

        // ── Parse with ContextRouter (injected via QueryParser constructor) ──
        $intent = $parser->parse($query);

        // ── Get routing metadata from the parser ──
        $routingResult = $parser->getLastRoutingResult();

        // ── Log query, intent, and routing decision ──
        $logData = [
            'query'             => $query,
            'intent'            => json_encode($intent),
            'routing_confidence' => $intent['routing_confidence'] ?? null,
            'routing_source'    => $intent['routing_source'] ?? ($intent['source'] ?? null),
            'routing_method'    => $intent['routing_method'] ?? 'none',
        ];

        ChatLog::create($logData);

        // ── Must be a select action and table must be detected ──
        if (empty($intent['action']) || $intent['action'] !== 'select' || empty($intent['table'])) {
            return response()->json(
                $formatter->text('No matching data found.', $intent['routing_confidence'] ?? null)
            );
        }

        $result = $executor->execute($intent);

        if (count($result)) {
            return response()->json(
                $formatter->table($result, $intent['routing_confidence'] ?? null)
            );
        }

        return response()->json(
            $formatter->text('No matching data found.', $intent['routing_confidence'] ?? null)
        );
    }
}
