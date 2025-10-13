<?php

namespace Paynecta\LaravelSdk\Exceptions;

use Exception;

class PaynectaException extends Exception
{
    protected ?string $errorCode = null;
    protected mixed $responseBody = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?string $errorCode = null,
        mixed $responseBody = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->errorCode = $errorCode;
        $this->responseBody = $responseBody;
    }

    /**
     * Get the error code from the API response
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get the full response body from the API
     */
    public function getResponseBody(): mixed
    {
        return $this->responseBody;
    }

    /**
     * Check if this exception has an error code
     */
    public function hasErrorCode(): bool
    {
        return !empty($this->errorCode);
    }
}