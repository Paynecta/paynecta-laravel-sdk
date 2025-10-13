<?php

namespace Paynecta\LaravelSdk\Exceptions;

class RateLimitException extends PaynectaException
{
    public function __construct(
        string $message = 'Rate limit exceeded',
        ?string $errorCode = null,
        mixed $responseBody = null
    ) {
        parent::__construct($message, 429, $errorCode, $responseBody);
    }
}