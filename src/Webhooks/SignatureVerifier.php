<?php

declare(strict_types=1);

namespace Zelovance\Sdk\Webhooks;

final class SignatureVerifier
{
    public static function verify(string $rawBody, string $receivedSignature, string $secret): bool
    {
        $receivedSignature = strtolower(trim($receivedSignature));

        if ($receivedSignature === '' || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $receivedSignature);
    }
}
