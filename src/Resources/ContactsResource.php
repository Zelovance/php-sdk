<?php

declare(strict_types=1);

namespace Zelovance\Sdk\Resources;

use Generator;
use Zelovance\Sdk\Pagination\PaginatedResult;
use Zelovance\Sdk\Support\Uuid;
use Zelovance\Sdk\ZelovanceClient;

final readonly class ContactsResource
{
    public function __construct(private ZelovanceClient $client)
    {
    }

    /**
     * Paramètres de requête transmis tels quels à l'API, par exemple ['page' => 2].
     *
     * @param array<string, scalar|null> $query
     */
    public function list(array $query = []): PaginatedResult
    {
        return new PaginatedResult(
            $this->client->get('/api/v1/contacts', $query),
        );
    }

    /**
     * Parcourt automatiquement toutes les pages disponibles.
     *
     * @param array<string, scalar|null> $query
     * @return Generator<int, array<string, mixed>>
     */
    public function all(array $query = []): Generator
    {
        $page = isset($query['page']) ? max(1, (int) $query['page']) : 1;

        do {
            $result = $this->list([...$query, 'page' => $page]);

            foreach ($result as $contact) {
                yield $contact;
            }

            $page = $result->nextPage() ?? 0;
        } while ($page > 0);
    }

    /** @return array<string, mixed> */
    public function find(string $uuid): array
    {
        Uuid::assertValid($uuid, 'contact_uuid');

        $response = $this->client->get('/api/v1/contacts/' . rawurlencode($uuid));
        $data = $response['data'] ?? null;

        return is_array($data) ? $data : $response;
    }
}
