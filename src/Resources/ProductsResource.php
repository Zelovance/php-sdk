<?php

declare(strict_types=1);

namespace Zelovance\Sdk\Resources;

use Generator;
use InvalidArgumentException;
use Zelovance\Sdk\Pagination\PaginatedResult;
use Zelovance\Sdk\Support\Uuid;
use Zelovance\Sdk\ZelovanceClient;

final readonly class ProductsResource
{
    private const TYPES = ['product', 'service'];

    private const FIELDS = [
        'name',
        'code',
        'type',
        'description',
        'unit_price',
        'unit',
        'vat_rate',
    ];

    public function __construct(private ZelovanceClient $client)
    {
    }

    /** @param array<string, scalar|null> $query */
    public function list(array $query = []): PaginatedResult
    {
        return new PaginatedResult(
            $this->client->get('/api/v1/products', $query),
        );
    }

    /**
     * @param array<string, scalar|null> $query
     * @return Generator<int, array<string, mixed>>
     */
    public function all(array $query = []): Generator
    {
        $page = isset($query['page']) ? max(1, (int) $query['page']) : 1;

        do {
            $result = $this->list([...$query, 'page' => $page]);

            foreach ($result as $product) {
                yield $product;
            }

            $page = $result->nextPage() ?? 0;
        } while ($page > 0);
    }

    /** @return array<string, mixed> */
    public function find(string $uuid): array
    {
        Uuid::assertValid($uuid, 'product_uuid');

        return $this->resourceData(
            $this->client->get('/api/v1/products/' . rawurlencode($uuid)),
        );
    }

    /**
     * Crée un article du catalogue de l'organisation courante.
     *
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    public function create(array $product): array
    {
        $this->validateCreatePayload($product);

        return $this->resourceData(
            $this->client->post('/api/v1/products', $product),
        );
    }

    /**
     * Modifie partiellement un article visible du catalogue.
     *
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    public function update(string $uuid, array $changes): array
    {
        Uuid::assertValid($uuid, 'product_uuid');
        $this->validateUpdatePayload($changes);

        return $this->resourceData(
            $this->client->patch('/api/v1/products/' . rawurlencode($uuid), $changes),
        );
    }

    /** @param array<string, mixed> $product */
    private function validateCreatePayload(array $product): void
    {
        $this->validateFields($product);

        $this->validateRequiredString($product, 'name', 255);
        $this->validateOptionalString($product, 'code', 255);
        $this->validateOptionalString($product, 'description');
        $this->validateRequiredString($product, 'unit', 50);

        $type = $product['type'] ?? null;

        if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('type doit valoir product ou service.');
        }

        $this->validateNumberInRange($product, 'unit_price', 0, null);
        $this->validateNumberInRange($product, 'vat_rate', 0, 100);
    }

    /** @param array<string, mixed> $changes */
    private function validateUpdatePayload(array $changes): void
    {
        if ($changes === []) {
            throw new InvalidArgumentException('La mise à jour d’un article ne peut pas être vide.');
        }

        $this->validateFields($changes);

        if (array_key_exists('name', $changes)) {
            $this->validateRequiredString($changes, 'name', 255);
        }

        if (array_key_exists('code', $changes)) {
            $this->validateOptionalString($changes, 'code', 255);
        }

        if (array_key_exists('description', $changes)) {
            $this->validateOptionalString($changes, 'description');
        }

        if (array_key_exists('unit', $changes)) {
            $this->validateRequiredString($changes, 'unit', 50);
        }

        if (array_key_exists('type', $changes)) {
            $type = $changes['type'];

            if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
                throw new InvalidArgumentException('type doit valoir product ou service.');
            }
        }

        if (array_key_exists('unit_price', $changes)) {
            $this->validateNumberInRange($changes, 'unit_price', 0, null);
        }

        if (array_key_exists('vat_rate', $changes)) {
            $this->validateNumberInRange($changes, 'vat_rate', 0, 100);
        }
    }

    /** @param array<string, mixed> $product */
    private function validateFields(array $product): void
    {
        foreach ($product as $field => $_) {
            if (! in_array($field, self::FIELDS, true)) {
                throw new InvalidArgumentException(sprintf('Le champ produit %s n’est pas pris en charge.', $field));
            }
        }
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function resourceData(array $response): array
    {
        $data = $response['data'] ?? null;

        return is_array($data) ? $data : $response;
    }

    /** @param array<string, mixed> $product */
    private function validateRequiredString(array $product, string $field, int $maxLength): void
    {
        $value = $product[$field] ?? null;

        if (! is_string($value) || trim($value) === '' || $this->stringLength($value) > $maxLength) {
            throw new InvalidArgumentException(sprintf('%s doit être une chaîne obligatoire de %d caractères maximum.', $field, $maxLength));
        }
    }

    /** @param array<string, mixed> $product */
    private function validateOptionalString(array $product, string $field, ?int $maxLength = null): void
    {
        if (! array_key_exists($field, $product) || $product[$field] === null) {
            return;
        }

        $value = $product[$field];

        if (! is_string($value) || ($maxLength !== null && $this->stringLength($value) > $maxLength)) {
            $suffix = $maxLength === null ? '' : sprintf(' de %d caractères maximum', $maxLength);

            throw new InvalidArgumentException(sprintf('%s doit être une chaîne facultative%s.', $field, $suffix));
        }
    }

    /** @param array<string, mixed> $product */
    private function validateNumberInRange(array $product, string $field, float $min, ?float $max): void
    {
        $value = $product[$field] ?? null;

        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException(sprintf('%s doit être un nombre.', $field));
        }

        $number = (float) $value;

        if (! is_finite($number) || $number < $min || ($max !== null && $number > $max)) {
            $range = $max === null ? sprintf('supérieur ou égal à %s', $min) : sprintf('compris entre %s et %s', $min, $max);

            throw new InvalidArgumentException(sprintf('%s doit être %s.', $field, $range));
        }
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        $length = preg_match_all('/./u', $value);

        return $length === false ? strlen($value) : $length;
    }
}
