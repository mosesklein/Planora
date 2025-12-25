<?php

namespace App\Exceptions;

use RuntimeException;

class OsmrException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $statusCode,
        private mixed $responseBody = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): mixed
    {
        return $this->responseBody;
    }
}
