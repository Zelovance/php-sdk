<?php

declare(strict_types=1);

namespace Zelovance\Sdk\Http;

use CurlHandle;
use Zelovance\Sdk\Contracts\TransportInterface;
use Zelovance\Sdk\Exceptions\TransportException;

final class NativeCurlTransport implements TransportInterface
{
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        int $timeout = 30,
    ): Response {
        $handle = curl_init();

        if (! $handle instanceof CurlHandle) {
            throw new TransportException('Impossible d\'initialiser cURL.');
        }

        $responseHeaders = [];
        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 10),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_USERAGENT => $headers['User-Agent'] ?? 'Zelovance-PHP-SDK/0.1.0',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $trimmed = trim($line);

                if ($trimmed === '' || ! str_contains($trimmed, ':')) {
                    return $length;
                }

                [$name, $value] = explode(':', $trimmed, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);

                return $length;
            },
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($handle);

        if ($responseBody === false) {
            $message = curl_error($handle);
            $code = curl_errno($handle);
            curl_close($handle);

            throw new TransportException(
                sprintf('Échec de la requête HTTP cURL (%d) : %s', $code, $message),
                $code,
            );
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new Response($statusCode, $responseHeaders, (string) $responseBody);
    }
}
