<?php

declare(strict_types=1);

namespace Zelovance\Sdk\Exceptions;

final class ValidationException extends ApiException
{
    /** @return array<string, list<string>> */
    public function errors(): array
    {
        $errors = $this->responseData()['errors'] ?? [];

        return is_array($errors) ? $errors : [];
    }
}
