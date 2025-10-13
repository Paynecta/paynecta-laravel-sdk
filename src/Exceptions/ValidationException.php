<?php

namespace Paynecta\LaravelSdk\Exceptions;

class ValidationException extends PaynectaException
{
    public function __construct(
        string $message = 'Validation failed',
        ?string $errorCode = null,
        mixed $responseBody = null
    ) {
        parent::__construct($message, 400, $errorCode, $responseBody);
    }
}