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

    'default' => env('LLM_PROVIDER', env('AI_DEFAULT_PROVIDER', 'deepseek')),
    'default_model' => env('LLM_MODEL', env('AI_DEFAULT_MODEL', 'deepseek-chat')),

    'fallback_provider' => env('AI_FALLBACK_PROVIDER', 'openrouter'),
    'fallback_model' => env('AI_FALLBACK_MODEL', 'openrouter/free'),

    'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),
    'chat_max_tokens' => (int) env('AI_CHAT_MAX_TOKENS', 1024),

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
        'max_messages' => (int) env('AI_MEMORY_MAX_MESSAGES', 16),
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
            'key' => env('LLM_API_KEY', env('OPENROUTER_API_KEY')),
            'url' => env('LLM_BASE_URL', env('OPENROUTER_URL', 'https://openrouter.ai/api/v1')),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('LLM_API_KEY', env('DEEPSEEK_API_KEY')),
            'url' => env('LLM_BASE_URL', env('DEEPSEEK_URL', 'https://api.deepseek.com')),
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

    /*
    |--------------------------------------------------------------------------
    | Engine Release & Operational Rollout Controls (v2.4-RC1)
    |--------------------------------------------------------------------------
    |
    | Sealed versioning and canary rollout switches for controlled customer release.
    |
    */

    'engine_version' => env('AI_ENGINE_VERSION', 'v2.4-RC1'),

    // Rollout Modes: 'disabled' (all AI off), 'limited_canary' (explicit workspaces only), 'all' (unrestricted)
    'rollout_mode' => env('AI_ROLLOUT_MODE', env('APP_ENV') === 'testing' ? 'all' : 'limited_canary'),

    // Comma-separated list of canary workspace IDs, e.g. "1,2,5"
    'canary_workspaces' => array_values(array_filter(array_map('trim', explode(',', (string) env('AI_CANARY_WORKSPACES', ''))))),

    // Maximum queries allowed per canary workspace per calendar day
    'canary_daily_limit' => (int) env('AI_CANARY_DAILY_LIMIT', 100),

    // Maximum active in-flight requests allowed simultaneously per workspace (0 = unlimited)
    'canary_max_concurrent_per_workspace' => (int) env('AI_CANARY_MAX_CONCURRENT_PER_WORKSPACE', 5),

    // Maximum active in-flight requests allowed simultaneously across all workspaces on this host (0 = unlimited)
    'canary_max_concurrent_global' => (int) env('AI_CANARY_MAX_CONCURRENT_GLOBAL', 10),

    /*
    |--------------------------------------------------------------------------
    | Frozen Retrieval & Reranker Hyperparameters
    |--------------------------------------------------------------------------
    |
    | Rigorously audited parameters from Gold Benchmark & Holdout Staging.
    |
    */

    'retrieval' => [
        'top_k' => (int) env('AI_RETRIEVAL_TOP_K', 5),
        'hybrid_alpha' => (float) env('AI_RETRIEVAL_HYBRID_ALPHA', 0.65),
        'reranker_threshold' => (float) env('AI_RETRIEVAL_RERANKER_THRESHOLD', 0.55),
        'exact_match_boost' => (bool) env('AI_RETRIEVAL_EXACT_BOOST', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cryptographic Benchmark Manifest (Integrity Sealed)
    |--------------------------------------------------------------------------
    |
    | Prevents silent drift or modification of evaluation baselines.
    |
    */

    'benchmarks' => [
        'gold_v1' => [
            'name'   => 'AI Knowledge Engine Gold Benchmark v1',
            'file'   => 'tests/Datasets/ai_knowledge_engine_gold_benchmark_v1.json',
            'cases'  => 600,
            'sha256' => '373b6ada17348e8d49169f4342d92667e886be6fc7a0d7c494cc9da6484fb5b7',
        ],
        'holdout_v1' => [
            'name'   => 'Hidden Holdout Staging Evaluation v1',
            'file'   => 'tests/Datasets/hidden_holdout_evaluation_v1.json',
            'cases'  => 120,
            'sha256' => '21abd3067fbef60b0eb787b8b012228e7f811108d875940fae41a32c40d43224',
        ],
    ],

];
