<?php

namespace App\Services;

class ResponseFormatter
{
    public function table($data)
    {
        return [
            'type' => 'table',
            'data' => $data
        ];
    }

    public function text($message)
    {
        return [
            'type' => 'text',
            'message' => $message
        ];
    }
}