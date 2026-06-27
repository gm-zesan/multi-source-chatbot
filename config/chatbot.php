<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Embedding Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for generating 384-dimension vector embeddings using the
    | sentence-transformers/all-MiniLM-L6-v2 model.
    |
    | The Python script at 'script' path is called by VectorEmbeddingService
    | to generate embeddings. Results are cached using the default cache driver.
    |
    | Example usage:
    |   $vector = app(VectorEmbeddingService::class)->generateEmbedding('customers');
    |
    */
    'embedding' => [
        'model'      => env('CHATBOT_EMBEDDING_MODEL', 'sentence-transformers/all-MiniLM-L6-v2'),
        'dimensions' => env('CHATBOT_EMBEDDING_DIMENSIONS', 384),
        'python'     => env('CHATBOT_PYTHON_PATH', 'python'),
        'script'     => storage_path('scripts/generate_embeddings.py'),
        'timeout'    => env('CHATBOT_EMBEDDING_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Controls how embedding vectors are cached to avoid repeated Python calls.
    | Uses Laravel's cache driver (Redis recommended for production).
    |
    | cache_ttl: 86400 seconds = 24 hours
    |   Individual embedding vectors are cached for 24 hours. The cache is
    |   invalidated when embeddings are regenerated.
    |
    | cache_prefix: All embedding cache keys use this prefix for namespacing.
    |
    */
    'vector' => [
        'cache_ttl'           => env('CHATBOT_VECTOR_CACHE_TTL', 86400),
        'cache_prefix'        => 'embedding:',
        'candidates_cache_ttl' => env('CHATBOT_CANDIDATES_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Router Thresholds
    |--------------------------------------------------------------------------
    |
    | Confidence thresholds for each routing level in the ContextRouter.
    | Each level uses cosine similarity scores (0.0 to 1.0).
    |
    | database: 0.65 minimum similarity to match a database source.
    |   A query like "show me customer info" must score ≥ 0.65 against
    |   the db_01 database embedding to route there.
    |
    | table: 0.55 minimum similarity to match a table.
    |   Lower than database since table names are shorter and have less context.
    |   "list all products" must score ≥ 0.55 against products table.
    |
    | fallback: 0.45 minimum for keyword-based fallback.
    |   If semantic confidence is below this, the router returns no match.
    |
    | composite_weights: How each level contributes to the final score.
    |   table: 50% (most important)
    |   database: 30%
    |   query_type: 20%
    |
    */
    'router' => [
        'thresholds' => [
            'database'  => env('CHATBOT_ROUTER_DB_THRESHOLD', 0.30),
            'table'     => env('CHATBOT_ROUTER_TABLE_THRESHOLD', 0.20),
            'fallback'  => env('CHATBOT_ROUTER_FALLBACK_THRESHOLD', 0.12),
        ],

        'composite_weights' => [
            'table'      => env('CHATBOT_WEIGHT_TABLE', 0.50),
            'database'   => env('CHATBOT_WEIGHT_DATABASE', 0.30),
            'query_type' => env('CHATBOT_WEIGHT_QUERY_TYPE', 0.20),
        ],

        'top_n_candidates' => env('CHATBOT_TOP_N_CANDIDATES', 5),

        'enable_fallback' => env('CHATBOT_ENABLE_FALLBACK', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Database Descriptions
    |--------------------------------------------------------------------------
    |
    | Semantic descriptions for each database connection used to generate
    | database-level embeddings. The description text is fed to the embedding
    | model so that user queries semantically match the most relevant source.
    |
    | Each source has:
    |   - description: Rich text describing the database contents and purpose.
    |   - tables: List of table names in this database.
    |   - label: Short display label (optional, defaults to description).
    |
    | Example routing:
    |   "show customer contact info" → matches db_01 (customers)
    |   "total sales last quarter"  → matches db_03 (sales)
    |   "employee salary details"   → matches db_04 (employees)
    |
    */
    'sources' => [
        'db_01' => [
            'label'       => 'Customers Database',
            'description' => 'Customer relationship management database containing customer profiles, contact information (name, email, phone), city locations, and demographics for sales targeting and customer service.',
            'tables'      => ['customers'],
        ],

        'db_02' => [
            'label'       => 'Products Database',
            'description' => 'Product inventory and catalog database with product details including product names, categories (Electronics, Furniture), pricing, and stock levels for inventory management.',
            'tables'      => ['products'],
        ],

        'db_03' => [
            'label'       => 'Sales Database',
            'description' => 'Sales transaction and order management database tracking customer purchases, order amounts, order dates, and sales performance analytics for revenue reporting.',
            'tables'      => ['sales'],
        ],

        'db_04' => [
            'label'       => 'Employees Database',
            'description' => 'Human resources and employee management database with staff records, department assignments, salary information, and joining dates for payroll and HR reporting.',
            'tables'      => ['employees'],
        ],

        'db_05' => [
            'label'       => 'Suppliers Database',
            'description' => 'Vendor and supplier management database with company profiles, contact persons, phone numbers, and city locations for supply chain and procurement operations.',
            'tables'      => ['suppliers'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Type Descriptions
    |--------------------------------------------------------------------------
    |
    | Semantic descriptions for query intent classification.
    | These are embedded and used by ContextRouter to determine whether
    | a query is a simple SELECT, a COUNT, an aggregate function, or
    | a filtered search with WHERE conditions.
    |
    */
    'query_types' => [
        [
            'name'        => 'select',
            'description' => 'Retrieve, show, display, list, get, fetch, or view records from a database table. Simple data retrieval without aggregation.',
        ],
        [
            'name'        => 'count',
            'description' => 'Count the number of records, count total rows, get the count of items in a table. Used for record counting operations.',
        ],
        [
            'name'        => 'aggregate',
            'description' => 'Calculate sum, total, average, avg, minimum, min, maximum, or max of a numeric column. Used for mathematical aggregation of values.',
        ],
        [
            'name'        => 'filtered_search',
            'description' => 'Search for records with specific conditions, where clauses, filters, or criteria. Used when querying data that matches certain conditions.',
        ],
    ],
];
