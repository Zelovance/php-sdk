<?php

declare(strict_types=1);

namespace Zelovance\Sdk\Pagination;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class PaginatedResult implements IteratorAggregate, Countable
{
    /** @var list<array<string, mixed>> */
    private array $items;

    /** @var array<string, mixed> */
    private array $links;

    /** @var array<string, mixed> */
    private array $meta;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $data = $payload['data'] ?? [];
        $links = $payload['links'] ?? [];
        $meta = $payload['meta'] ?? [];

        $this->items = is_array($data) ? array_values($data) : [];
        $this->links = is_array($links) ? $links : [];
        $this->meta = is_array($meta) ? $meta : [];
    }

    /** @return list<array<string, mixed>> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return array<string, mixed> */
    public function links(): array
    {
        return $this->links;
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return $this->meta;
    }

    public function currentPage(): int
    {
        return (int) ($this->meta['current_page'] ?? 1);
    }

    public function lastPage(): int
    {
        return (int) ($this->meta['last_page'] ?? $this->currentPage());
    }

    public function perPage(): ?int
    {
        return isset($this->meta['per_page']) ? (int) $this->meta['per_page'] : null;
    }

    public function total(): ?int
    {
        return isset($this->meta['total']) ? (int) $this->meta['total'] : null;
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage() < $this->lastPage();
    }

    public function nextPage(): ?int
    {
        return $this->hasNextPage() ? $this->currentPage() + 1 : null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
