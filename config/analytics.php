<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Python Analytics Service Base URL
    |--------------------------------------------------------------------------
    |
    | Base URL for the Python Analytics Service (now part of the AI Engine).
    | By default, this runs on port 8001 alongside the Retrieval Engine.
    |
    */
    'base_url' => env('PYTHON_ANALYTICS_URL', env('PYTHON_RETRIEVAL_URL', 'http://localhost:8001')),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum execution timeout in seconds when awaiting LLM Query Planning,
    | deterministic compilation, safe MySQL execution, and Markdown formatting.
    |
    */
    'timeout' => (int) env('PYTHON_ANALYTICS_TIMEOUT', 30),
];
