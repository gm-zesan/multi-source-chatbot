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
        ResponseFormatter $formatter
    ) {

        $query = $request->message;
        $intent = $parser->parse($query);
        ChatLog::create([
            'query'  => $query,
            'intent' => json_encode($intent),
        ]);

        // must be a select action and table must be detected
        if (empty($intent['action']) || $intent['action'] !== 'select' || empty($intent['table'])) {
            return response()->json(
                $formatter->text('No matching data found.')
            );
        }

        $result = $executor->execute($intent);

        if (count($result)) {
            return response()->json(
                $formatter->table($result)
            );
        }

        return response()->json(
            $formatter->text('No matching data found.')
        );
    }
}
