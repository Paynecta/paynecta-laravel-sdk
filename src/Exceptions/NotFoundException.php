<?php

namespace Paynecta\LaravelSdk\Exceptions;

class NotFoundException extends PaynectaException
{
    public function __construct(
        string $message = 'Resource not found',
        ?string $errorCode = null,
        mixed $responseBody = null
    ) {
        parent::__construct($message, 404, $errorCode, $responseBody);
    }
}