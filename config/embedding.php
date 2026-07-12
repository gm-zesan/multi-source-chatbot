<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Embedding Service Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the Python FastAPI embedding service.
    | Used by App\Services\NLP\Embedding\EmbeddingService.
    |
    | All values are sourced from environment variables — no hardcoded secrets.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    */
    'model'      => env('CHATBOT_EMBEDDING_MODEL', 'paraphrase-multilingual-mpnet-base-v2'),
    'dimensions' => (int) env('CHATBOT_EMBEDDING_DIMENSIONS', 768),

    /*
    |--------------------------------------------------------------------------
    | Connection
    |--------------------------------------------------------------------------
    */
    'base_url'   => env('CHATBOT_EMBEDDING_URL', 'http://127.0.0.1:8001'),
    'api_key'    => env('CHATBOT_EMBEDDING_API_KEY', ''),
    'timeout'    => (int) env('CHATBOT_EMBEDDING_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Retry
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => (int) env('CHATBOT_EMBEDDING_RETRY_ATTEMPTS', 3),
        'delay_ms'     => (int) env('CHATBOT_EMBEDDING_RETRY_DELAY_MS', 500),
    ],

];
