<?php

declare(strict_types=1);

namespace Zelovance\Sdk\Contracts;

use Zelovance\Sdk\Http\Response;

interface TransportInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        int $timeout = 30,
    ): Response;
}
