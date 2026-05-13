<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'openai' => [
        'api_key' => env('KEY_AI_GPT_MODEL'),
        'model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'responses_model' => env('OPENAI_RESPONSES_MODEL', env('OPENAI_CHAT_MODEL', 'gpt-4o-mini')),
        'recommendations_model' => env('OPENAI_RECOMMENDATIONS_MODEL', env('OPENAI_RESPONSES_MODEL', env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'))),
        'timeout' => (int) env('OPENAI_TIMEOUT', 90),
        'connect_timeout' => (int) env('OPENAI_CONNECT_TIMEOUT', 20),
        'retry_times' => (int) env('OPENAI_RETRY_TIMES', 3),
        'retry_sleep_ms' => (int) env('OPENAI_RETRY_SLEEP_MS', 1200),
        'workout_cache_ttl' => (int) env('OPENAI_WORKOUT_CACHE_TTL', 3600),
        'prompt_log_path' => env('OPENAI_PROMPT_LOG_PATH', 'logs/ai-prompts.log'),
        'vector_store' => [
            'enabled' => (bool) env('OPENAI_VECTOR_STORE_ENABLED', true),
            'scope' => env('OPENAI_VECTOR_STORE_SCOPE', 'global'),
            'catalog_type' => env('OPENAI_VECTOR_STORE_CATALOG_TYPE', 'workout_exercises'),
            'name_prefix' => env('OPENAI_VECTOR_STORE_NAME_PREFIX', 'akdemia-workouts'),
            'file_purpose' => env('OPENAI_VECTOR_STORE_FILE_PURPOSE', 'assistants'),
            'max_search_results' => (int) env('OPENAI_VECTOR_STORE_MAX_SEARCH_RESULTS', 24),
            'minimum_candidates' => (int) env('OPENAI_VECTOR_STORE_MINIMUM_CANDIDATES', 12),
        ],
    ],

    'payment' => [
        'provider_name' => env('PAYMENT_PROVIDER_NAME', 'mercadopago'),
        'api_base_url' => env('PAYMENT_API_BASE_URL'),
        'api_token' => env('PAYMENT_API_TOKEN'),
        'pix_key' => env('PIX_KEY'),
    ],

    'mercadopago' => [
        'base_url' => env('MERCADO_PAGO_BASE_URL', 'https://api.mercadopago.com'),
        'token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
    ],
    'pix' => [
        'key' => env('PIX_KEY'),
    ],

    'cloudflare' => [
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'zone_name' => env('CLOUDFLARE_ZONE_NAME', env('APP_LANDING_ROOT_DOMAIN', 'academai.com.br')),
        'record_type' => env('CLOUDFLARE_RECORD_TYPE', 'CNAME'),
        'record_target' => env('CLOUDFLARE_RECORD_TARGET'),
        'proxied' => (bool) env('CLOUDFLARE_PROXIED', true),
    ],

    'workoutx' => [
        'enabled' => (bool) env('WORKOUTX_ENABLED', false),
        'api_base_url' => env('WORKOUTX_API_BASE_URL', 'https://api.workoutxapp.com/v1'),
        'api_key' => env('WORKOUTX_API_KEY'),
        'auth_mode' => env('WORKOUTX_AUTH_MODE', 'header'),
        'request_timeout' => (int) env('WORKOUTX_REQUEST_TIMEOUT', 20),
        'default_limit' => (int) env('WORKOUTX_DEFAULT_LIMIT', 10),
        'sync_page_delay_seconds' => (int) env('WORKOUTX_SYNC_PAGE_DELAY_SECONDS', 120),
        'allow_fallback' => (bool) env('WORKOUTX_ALLOW_FALLBACK', false),
    ],

    'internal_catalog' => [
        'api_key' => env('INTERNAL_CATALOG_API_KEY'),
        'ai_bucket_limit' => (int) env('INTERNAL_CATALOG_AI_BUCKET_LIMIT', 12),
        'ai_prompt_bucket_limit' => (int) env('INTERNAL_CATALOG_AI_PROMPT_BUCKET_LIMIT', 30),
        'endpoint_limit' => (int) env('INTERNAL_CATALOG_ENDPOINT_LIMIT', 100),
        'storage_path' => env('INTERNAL_CATALOG_STORAGE_PATH', 'ai/openai-workout-catalog.json'),
        'vector_store_storage_path' => env('INTERNAL_CATALOG_VECTOR_STORE_STORAGE_PATH', 'ai/openai-workout-catalog.jsonl'),
    ],

];
