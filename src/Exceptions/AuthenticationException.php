<?php

namespace Paynecta\LaravelSdk\Exceptions;

class AuthenticationException extends PaynectaException
{
    public function __construct(
        string $message = 'Authentication failed',
        ?string $errorCode = null,
        mixed $responseBody = null
    ) {
        parent::__construct($message, 401, $errorCode, $responseBody);
    }
}