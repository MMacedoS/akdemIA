<?php

namespace App\Exceptions\AI;

use Illuminate\Validation\ValidationException;
use RuntimeException;

class WorkoutValidationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $issues = [],
    ) {
        parent::__construct($message);
    }

    public static function fromValidationException(ValidationException $exception): self
    {
        return new self($exception->getMessage(), $exception->errors());
    }
}