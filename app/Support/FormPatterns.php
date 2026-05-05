<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class FormPatterns
{
    public static function name(bool $required = true, int $max = 255): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'string',
            'max:' . $max,
        ]));
    }

    public static function email(?int $ignoreId = null, string $table = 'users', string $column = 'email', bool $required = true): array
    {
        $uniqueRule = Rule::unique($table, $column);

        if ($ignoreId !== null) {
            $uniqueRule = $uniqueRule->ignore($ignoreId);
        }

        return [
            $required ? 'required' : 'nullable',
            'string',
            'email:rfc',
            'max:255',
            $uniqueRule,
        ];
    }

    public static function looseEmail(bool $required = true, int $max = 255): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'email:rfc',
            'max:' . $max,
        ];
    }

    public static function phone(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:20',
            self::phoneRule(),
        ];
    }

    public static function document(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:18',
            self::documentRule(),
        ];
    }

    public static function date(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'date',
        ];
    }

    public static function decimal(bool $required = false, float|int $min = 0, float|int $max = 999999.99): array
    {
        return [
            $required ? 'required' : 'nullable',
            'numeric',
            'min:' . $min,
            'max:' . $max,
        ];
    }

    public static function integer(bool $required = false, int $min = 0, ?int $max = null): array
    {
        $rules = [
            $required ? 'required' : 'nullable',
            'integer',
            'min:' . $min,
        ];

        if ($max !== null) {
            $rules[] = 'max:' . $max;
        }

        return $rules;
    }

    public static function slug(bool $required = true, int $max = 120): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:' . $max,
            'regex:/^[a-z0-9-]+$/',
        ];
    }

    public static function normalizeEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        return $normalized !== '' ? $normalized : null;
    }

    public static function digitsOnly(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    public static function formatDocument(?string $value): ?string
    {
        $digits = self::digitsOnly($value);

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?: $digits;
        }

        if (strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits) ?: $digits;
        }

        return trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    public static function formatPhone(?string $value): ?string
    {
        $digits = self::digitsOnly($value);

        if (strlen($digits) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits) ?: $digits;
        }

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits) ?: $digits;
        }

        return trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    public static function formatDate(mixed $value, string $format = 'd/m/Y'): ?string
    {
        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return $value->format($format);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->format($format);
            } catch (\Throwable) {
                return trim($value);
            }
        }

        return null;
    }

    private static function phoneRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || trim((string) $value) === '') {
                return;
            }

            $digits = self::digitsOnly((string) $value);

            if (! in_array(strlen($digits), [10, 11], true)) {
                $fail('O campo ' . $attribute . ' deve conter um telefone valido.');
            }
        };
    }

    private static function documentRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || trim((string) $value) === '') {
                return;
            }

            $digits = self::digitsOnly((string) $value);

            if (strlen($digits) === 11 && self::isValidCpf($digits)) {
                return;
            }

            if (strlen($digits) === 14 && self::isValidCnpj($digits)) {
                return;
            }

            $fail('O campo ' . $attribute . ' deve conter um CPF ou CNPJ valido.');
        };
    }

    private static function isValidCpf(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($position = 9; $position < 11; $position++) {
            $sum = 0;

            for ($index = 0; $index < $position; $index++) {
                $sum += ((int) $cpf[$index]) * (($position + 1) - $index);
            }

            $digit = ((10 * $sum) % 11) % 10;

            if ((int) $cpf[$position] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private static function isValidCnpj(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        $firstWeights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $secondWeights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $firstDigit = self::calculateCnpjDigit($cnpj, $firstWeights);
        $secondDigit = self::calculateCnpjDigit($cnpj, $secondWeights);

        return (int) $cnpj[12] === $firstDigit && (int) $cnpj[13] === $secondDigit;
    }

    private static function calculateCnpjDigit(string $cnpj, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += ((int) $cnpj[$index]) * $weight;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
