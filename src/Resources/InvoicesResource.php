<?php

declare(strict_types=1);

namespace Zelovance\Sdk\Resources;

use Generator;
use InvalidArgumentException;
use Zelovance\Sdk\Invoices\InvoiceBuilder;
use Zelovance\Sdk\Pagination\PaginatedResult;
use Zelovance\Sdk\Support\Uuid;
use Zelovance\Sdk\ZelovanceClient;

final readonly class InvoicesResource
{
    private const TYPES = ['standard', 'advance_payment', 'progress', 'final'];
    private const LINE_TYPES = ['item', 'section', 'note'];

    public function __construct(private ZelovanceClient $client)
    {
    }

    /** @param array<string, scalar|null> $query */
    public function list(array $query = []): PaginatedResult
    {
        return new PaginatedResult(
            $this->client->get('/api/v1/invoices', $query),
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

            foreach ($result as $invoice) {
                yield $invoice;
            }

            $page = $result->nextPage() ?? 0;
        } while ($page > 0);
    }

    /** @return array<string, mixed> */
    public function find(string $uuid): array
    {
        Uuid::assertValid($uuid, 'invoice_uuid');

        return $this->resourceData(
            $this->client->get('/api/v1/invoices/' . rawurlencode($uuid)),
        );
    }

    /**
     * @param array<string, mixed>|InvoiceBuilder $invoice
     * @return array<string, mixed>
     */
    public function create(array|InvoiceBuilder $invoice): array
    {
        $payload = $invoice instanceof InvoiceBuilder ? $invoice->toArray() : $invoice;
        $this->validateCreatePayload($payload);

        return $this->resourceData(
            $this->client->post('/api/v1/invoices', $payload),
        );
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

    /** @param array<string, mixed> $payload */
    private function validateCreatePayload(array $payload): void
    {
        $idempotencyKey = $payload['idempotency_key'] ?? null;

        if (! is_string($idempotencyKey) || trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('idempotency_key est obligatoire.');
        }

        $hasUuid = isset($payload['contact_uuid']) && trim((string) $payload['contact_uuid']) !== '';
        $hasExternalId = isset($payload['contact_external_id']) && trim((string) $payload['contact_external_id']) !== '';

        if ($hasUuid === $hasExternalId) {
            throw new InvalidArgumentException('Envoyez exactement contact_uuid ou contact_external_id, jamais les deux.');
        }

        $type = $payload['type'] ?? null;

        if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('type doit valoir standard, advance_payment, progress ou final.');
        }

        foreach (['date', 'due_date'] as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field]) || trim($payload[$field]) === '') {
                throw new InvalidArgumentException($field . ' est obligatoire.');
            }
        }

        $lines = $payload['lines'] ?? null;

        if (! is_array($lines) || $lines === []) {
            throw new InvalidArgumentException('La facture doit contenir au moins une ligne.');
        }

        foreach ($lines as $index => $line) {
            if (! is_array($line)) {
                throw new InvalidArgumentException(sprintf('La ligne %d doit être un tableau.', $index));
            }

            $lineType = $line['line_type'] ?? 'item';

            if (! is_string($lineType) || ! in_array($lineType, self::LINE_TYPES, true)) {
                throw new InvalidArgumentException(sprintf('line_type invalide à la ligne %d.', $index));
            }

            if (! isset($line['name']) || ! is_string($line['name']) || trim($line['name']) === '') {
                throw new InvalidArgumentException(sprintf('name est obligatoire à la ligne %d.', $index));
            }

            if ($lineType === 'item') {
                foreach (['quantity', 'unit_price'] as $field) {
                    if (! array_key_exists($field, $line)) {
                        throw new InvalidArgumentException(sprintf('%s est obligatoire pour la ligne item %d.', $field, $index));
                    }
                }
            }
        }
    }
}
