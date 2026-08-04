<?php

declare(strict_types=1);

namespace Zelovance\Sdk\Support;

use InvalidArgumentException;

final class Uuid
{
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public static function assertValid(string $uuid, string $field = 'uuid'): void
    {
        if (! preg_match(self::PATTERN, $uuid)) {
            throw new InvalidArgumentException(sprintf('%s doit être un UUID valide.', $field));
        }
    }
}
