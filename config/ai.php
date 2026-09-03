<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | The default provider to use for agent interactions, text generation, etc.
    | Can be overridden on a per-agent or per-call basis.
    |
    */

    'default' => env('AI_DEFAULT_PROVIDER', 'deepseek'),
    'default_model' => env('AI_DEFAULT_MODEL', 'deepseek-chat'),

    'fallback_provider' => env('AI_FALLBACK_PROVIDER', 'openrouter'),
    'fallback_model' => env('AI_FALLBACK_MODEL', 'openrouter/free'),

    'max_tokens' => (int) env('AI_MAX_TOKENS', 320),
    'chat_max_tokens' => (int) env('AI_CHAT_MAX_TOKENS', 128),

    /*
    |--------------------------------------------------------------------------
    | Memory / Conversation Context Limits
    |--------------------------------------------------------------------------
    |
    | Controls how many previous messages are loaded into the agent context
    | and maximum character length per message to prevent context blowup.
    |
    */

    'memory' => [
        'max_messages' => (int) env('AI_MEMORY_MAX_MESSAGES', 10),
        'max_message_chars' => (int) env('AI_MEMORY_MAX_CHARS', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Supported providers for the Laravel AI SDK.
    |
    */

    'providers' => [
        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
            'url' => env('OPENROUTER_URL', 'https://openrouter.ai/api/v1'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
            'url' => env('DEEPSEEK_URL', 'https://api.deepseek.com'),
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'store' => env('OPENAI_STORE', true),
        ],

        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        ],

        'openai-compatible' => [
            'driver' => 'openai-compatible',
            'url' => env('OPENAI_COMPATIBLE_URL'),
            'key' => env('OPENAI_COMPATIBLE_API_KEY'),
        ],
    ],

];
