<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Python Retrieval Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Python FastAPI retrieval service which encapsulates
    | embedding models, vector search, and Typesense storage.
    |
    */

    'base_url' => env('PYTHON_RETRIEVAL_URL', 'http://127.0.0.1:8001'),
    'api_key'  => env('PYTHON_RETRIEVAL_API_KEY', ''),
    'timeout'  => (int) env('PYTHON_RETRIEVAL_TIMEOUT', 15),
    'top_k'    => (int) env('PYTHON_RETRIEVAL_TOP_K', 5),

];
