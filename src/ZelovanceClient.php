<?php

declare(strict_types=1);

namespace Zelovance\Sdk;

use InvalidArgumentException;
use JsonException;
use Zelovance\Sdk\Contracts\TransportInterface;
use Zelovance\Sdk\Exceptions\ApiException;
use Zelovance\Sdk\Exceptions\AuthenticationException;
use Zelovance\Sdk\Exceptions\AuthorizationException;
use Zelovance\Sdk\Exceptions\IdempotencyConflictException;
use Zelovance\Sdk\Exceptions\MethodNotAllowedException;
use Zelovance\Sdk\Exceptions\NotFoundException;
use Zelovance\Sdk\Exceptions\RateLimitException;
use Zelovance\Sdk\Exceptions\UnexpectedResponseException;
use Zelovance\Sdk\Exceptions\ValidationException;
use Zelovance\Sdk\Http\NativeCurlTransport;
use Zelovance\Sdk\Http\Response;
use Zelovance\Sdk\Resources\ContactsResource;
use Zelovance\Sdk\Resources\InvoicesResource;
use Zelovance\Sdk\Resources\ProductsResource;

final class ZelovanceClient
{
    public const VERSION = '0.2.0-beta.3';

    private readonly TransportInterface $transport;
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly int $timeout;
    private readonly string $userAgent;

    private ?ContactsResource $contactsResource = null;
    private ?InvoicesResource $invoicesResource = null;
    private ?ProductsResource $productsResource = null;

    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://dev.zelovance.com',
        ?TransportInterface $transport = null,
        int $timeout = 30,
        ?string $userAgent = null,
    ) {
        $apiKey = trim($apiKey);
        $baseUrl = rtrim(trim($baseUrl), '/');

        if ($apiKey === '') {
            throw new InvalidArgumentException('La clé API Zelovance ne peut pas être vide.');
        }

        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('L\'URL de base Zelovance est invalide.');
        }

        if ($timeout < 1) {
            throw new InvalidArgumentException('Le timeout doit être supérieur ou égal à une seconde.');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->transport = $transport ?? new NativeCurlTransport();
        $this->timeout = $timeout;
        $this->userAgent = $userAgent ?? 'Zelovance-PHP-SDK/' . self::VERSION;
    }

    public function contacts(): ContactsResource
    {
        return $this->contactsResource ??= new ContactsResource($this);
    }

    public function invoices(): InvoicesResource
    {
        return $this->invoicesResource ??= new InvoicesResource($this);
    }

    public function products(): ProductsResource
    {
        return $this->productsResource ??= new ProductsResource($this);
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload): array
    {
        return $this->request('POST', $path, [], $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function patch(string $path, array $payload): array
    {
        return $this->request('PATCH', $path, [], $payload);
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $query = [], ?array $payload = null): array
    {
        $url = $this->buildUrl($path, $query);
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
        ];

        $body = null;

        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';

            try {
                $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('Le payload ne peut pas être encodé en JSON.', 0, $exception);
            }
        }

        $response = $this->transport->request($method, $url, $headers, $body, $this->timeout);

        return $this->decodeResponse($response);
    }

    /** @param array<string, scalar|null> $query */
    private function buildUrl(string $path, array $query): string
    {
        $path = '/' . ltrim($path, '/');
        $url = $this->baseUrl . $path;

        $filtered = array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($filtered !== []) {
            $url .= '?' . http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    /** @return array<string, mixed> */
    private function decodeResponse(Response $response): array
    {
        $data = [];

        if (trim($response->body) !== '') {
            try {
                $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
                $data = is_array($decoded) ? $decoded : [];
            } catch (JsonException $exception) {
                if ($response->statusCode >= 200 && $response->statusCode < 300) {
                    throw new UnexpectedResponseException(
                        'Zelovance a retourné une réponse JSON invalide.',
                        $response->statusCode,
                        ['raw_body' => $response->body],
                        $response->headers,
                    );
                }
            }
        }

        if ($response->statusCode >= 200 && $response->statusCode < 300) {
            return $data;
        }

        $message = isset($data['message']) && is_string($data['message'])
            ? $data['message']
            : sprintf('Erreur API Zelovance HTTP %d.', $response->statusCode);

        throw $this->mapException($response, $message, $data);
    }

    /** @param array<string, mixed> $data */
    private function mapException(Response $response, string $message, array $data): ApiException
    {
        $class = match ($response->statusCode) {
            401 => AuthenticationException::class,
            403 => AuthorizationException::class,
            404 => NotFoundException::class,
            405 => MethodNotAllowedException::class,
            409 => IdempotencyConflictException::class,
            422 => ValidationException::class,
            429 => RateLimitException::class,
            default => UnexpectedResponseException::class,
        };

        return new $class($message, $response->statusCode, $data, $response->headers);
    }
}
