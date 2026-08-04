<?php

declare(strict_types=1);

namespace Zelovance\Sdk\Exceptions;

final class RateLimitException extends ApiException
{
    public function retryAfter(): ?int
    {
        $value = $this->responseHeaders()['retry-after'] ?? null;

        if ($value === null || ! ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }
}
