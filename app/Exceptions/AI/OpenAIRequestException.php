<?php

namespace App\Exceptions\AI;

use RuntimeException;

class OpenAIRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly array $responseBody = [],
    ) {
        parent::__construct($message, $statusCode);
    }
}