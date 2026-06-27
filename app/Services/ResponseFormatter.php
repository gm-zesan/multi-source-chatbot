<?php

namespace App\Services;

class ResponseFormatter
{
    /**
     * Format tabular data for the frontend.
     *
     * @param  array|\Illuminate\Support\Collection $data  Result rows
     * @param  float|null  $confidence  Routing confidence score (0.0 – 1.0)
     * @return array
     */
    public function table($data, ?float $confidence = null): array
    {
        $response = [
            'type' => 'table',
            'data' => $data,
        ];

        if ($confidence !== null) {
            $response['confidence'] = round($confidence, 4);
        }

        return $response;
    }

    /**
     * Format a text message for the frontend.
     *
     * @param  string     $message     The message text
     * @param  float|null $confidence  Routing confidence score (0.0 – 1.0)
     * @return array
     */
    public function text(string $message, ?float $confidence = null): array
    {
        $response = [
            'type'    => 'text',
            'message' => $message,
        ];

        if ($confidence !== null) {
            $response['confidence'] = round($confidence, 4);
        }

        return $response;
    }
}
