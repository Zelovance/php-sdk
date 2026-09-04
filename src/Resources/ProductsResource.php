<?php

declare(strict_types=1);

namespace Zelovance\Sdk\Resources;

use InvalidArgumentException;
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

    /**
     * Crée un article du catalogue de l'organisation courante.
     *
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    public function create(array $product): array
    {
        $this->validateCreatePayload($product);

        $response = $this->client->post('/api/v1/products', $product);
        $data = $response['data'] ?? null;

        return is_array($data) ? $data : $response;
    }

    /** @param array<string, mixed> $product */
    private function validateCreatePayload(array $product): void
    {
        foreach ($product as $field => $_) {
            if (! in_array($field, self::FIELDS, true)) {
                throw new InvalidArgumentException(sprintf('Le champ produit %s n’est pas pris en charge.', $field));
            }
        }

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
