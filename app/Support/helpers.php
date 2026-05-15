<?php

use App\Support\FormPatterns;

if (! function_exists('format_document_br')) {
    function format_document_br(?string $value, string $fallback = 'Nao informado'): string
    {
        return FormPatterns::formatDocument($value) ?? $fallback;
    }
}

if (! function_exists('format_phone_br')) {
    function format_phone_br(?string $value, string $fallback = 'Nao informado'): string
    {
        return FormPatterns::formatPhone($value) ?? $fallback;
    }
}

if (! function_exists('format_date_br')) {
    function format_date_br(mixed $value, string $fallback = 'Nao informado', string $format = 'd/m/Y'): string
    {
        return FormPatterns::formatDate($value, $format) ?? $fallback;
    }
}

if (! function_exists('landing_root_domain')) {
    function landing_root_domain(): ?string
    {
        $configured = trim((string) config('app.landing_root_domain', ''));

        if ($configured !== '') {
            return $configured;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($appHost) || $appHost === '' || $appHost === 'localhost' || filter_var($appHost, FILTER_VALIDATE_IP)) {
            return null;
        }

        return $appHost;
    }
}

if (! function_exists('api_route')) {
    function api_route(string $name, array $parameters = []): string
    {
        $root = rtrim((string) config('app.api_url', 'https://api.academai.com.br'), '/');
        $path = route($name, $parameters, false);

        return $root . '/' . ltrim($path, '/');
    }
}

if (! function_exists('reserved_public_subdomains')) {
    function reserved_public_subdomains(): array
    {
        return ['www', 'api'];
    }
}
