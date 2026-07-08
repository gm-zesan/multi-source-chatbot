<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Embedding Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the Python FastAPI embedding service.
    | Used by App\Services\NLP\Embedding\EmbeddingService.
    |
    */
    'embedding' => [
        'model'      => env('CHATBOT_EMBEDDING_MODEL', 'sentence-transformers/all-MiniLM-L6-v2'),
        'dimensions' => env('CHATBOT_EMBEDDING_DIMENSIONS', 384),
        'base_url'   => env('CHATBOT_EMBEDDING_URL', 'http://127.0.0.1:8000'),
        'timeout'    => (int) env('CHATBOT_EMBEDDING_TIMEOUT', 30),
        'retry'      => [
            'max_attempts' => (int) env('CHATBOT_EMBEDDING_RETRY_ATTEMPTS', 3),
            'delay_ms'     => (int) env('CHATBOT_EMBEDDING_RETRY_DELAY_MS', 500),
        ],
    ],

];
