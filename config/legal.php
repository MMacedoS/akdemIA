<?php

return [
    'company_name' => env('LEGAL_COMPANY_NAME', env('APP_NAME', 'AcademAI')),
    'contact_email' => env('LEGAL_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', 'contato@academai.com.br')),
    'terms' => [
        'version' => env('LEGAL_TERMS_VERSION', '2026-05-09'),
        'updated_at' => env('LEGAL_TERMS_UPDATED_AT', '2026-05-09'),
    ],
    'privacy_policy' => [
        'version' => env('LEGAL_PRIVACY_POLICY_VERSION', '2026-05-09'),
        'updated_at' => env('LEGAL_PRIVACY_POLICY_UPDATED_AT', '2026-05-09'),
    ],
];
