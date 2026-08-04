<?php

declare(strict_types=1);

namespace Zelovance\Sdk\Exceptions;

class ApiException extends ZelovanceException
{
    /**
     * @param array<string, mixed> $responseData
     * @param array<string, string> $responseHeaders
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly array $responseData = [],
        private readonly array $responseHeaders = [],
    ) {
        parent::__construct($message, $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, mixed> */
    public function responseData(): array
    {
        return $this->responseData;
    }

    /** @return array<string, string> */
    public function responseHeaders(): array
    {
        return $this->responseHeaders;
    }
}
