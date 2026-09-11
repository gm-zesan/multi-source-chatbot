<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Python Analytics Service Base URL
    |--------------------------------------------------------------------------
    |
    | Base URL for the Python Baseline Analytics Service (running via FastAPI / Uvicorn).
    | By default, this runs on port 8200 (port 8002 is reserved by Windows Hyper-V).
    |
    */
    'base_url' => env('PYTHON_ANALYTICS_URL', 'http://127.0.0.1:8200'),

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
