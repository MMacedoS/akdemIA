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
