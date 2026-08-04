<?php

declare(strict_types=1);

namespace Zelovance\Sdk\Invoices;

use DateTimeInterface;
use InvalidArgumentException;

final class InvoiceBuilder
{
    /** @var array<string, mixed> */
    private array $payload;

    private function __construct(string $idempotencyKey, string $contactField, string $contactValue)
    {
        $idempotencyKey = trim($idempotencyKey);
        $contactValue = trim($contactValue);

        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('idempotency_key ne peut pas être vide.');
        }

        if ($contactValue === '') {
            throw new InvalidArgumentException($contactField . ' ne peut pas être vide.');
        }

        $this->payload = [
            'idempotency_key' => $idempotencyKey,
            $contactField => $contactValue,
            'type' => 'standard',
            'lines' => [],
        ];
    }

    public static function forContactUuid(string $idempotencyKey, string $contactUuid): self
    {
        return new self($idempotencyKey, 'contact_uuid', $contactUuid);
    }

    public static function forExternalContactId(string $idempotencyKey, string $externalId): self
    {
        return new self($idempotencyKey, 'contact_external_id', $externalId);
    }

    public function type(string $type): self
    {
        $this->payload['type'] = $type;

        return $this;
    }

    public function date(DateTimeInterface|string $date): self
    {
        $this->payload['date'] = $this->formatDate($date);

        return $this;
    }

    public function dueDate(DateTimeInterface|string $date): self
    {
        $this->payload['due_date'] = $this->formatDate($date);

        return $this;
    }

    public function customerReference(?string $reference): self
    {
        return $this->setNullableString('customer_reference', $reference);
    }

    public function purchaseOrderNumber(?string $number): self
    {
        return $this->setNullableString('purchase_order_number', $number);
    }

    public function operationType(?string $type): self
    {
        return $this->setNullableString('operation_type', $type);
    }

    public function operationDate(DateTimeInterface|string|null $date): self
    {
        return $this->setNullableDate('operation_date', $date);
    }

    public function billingPeriod(DateTimeInterface|string $start, DateTimeInterface|string $end): self
    {
        $this->payload['billing_period_start'] = $this->formatDate($start);
        $this->payload['billing_period_end'] = $this->formatDate($end);

        return $this;
    }

    public function notes(?string $notes): self
    {
        return $this->setNullableString('notes', $notes);
    }

    public function footer(?string $footer): self
    {
        return $this->setNullableString('footer', $footer);
    }

    public function paymentInstructions(?string $instructions): self
    {
        return $this->setNullableString('payment_instructions', $instructions);
    }

    public function projectUuid(?string $uuid): self
    {
        return $this->setNullableString('project_uuid', $uuid);
    }

    public function addSection(string $name, ?string $description = null): self
    {
        return $this->addTextLine('section', $name, $description);
    }

    public function addNote(string $name, ?string $description = null): self
    {
        return $this->addTextLine('note', $name, $description);
    }

    public function addItem(
        string $name,
        int|float|string $quantity,
        int|float|string $unitPrice,
        int|float|string $vatRate = 20,
        string $unit = 'unit',
        ?string $description = null,
        ?string $discountType = null,
        int|float|string|null $discountValue = null,
        ?string $productUuid = null,
        DateTimeInterface|string|null $operationDate = null,
        DateTimeInterface|string|null $periodStart = null,
        DateTimeInterface|string|null $periodEnd = null,
    ): self {
        $line = [
            'line_type' => 'item',
            'name' => $this->requiredString($name, 'name'),
            'quantity' => $quantity,
            'unit' => trim($unit) === '' ? 'none' : trim($unit),
            'unit_price' => $unitPrice,
            'vat_rate' => $vatRate,
        ];

        $this->putOptional($line, 'description', $description);
        $this->putOptional($line, 'discount_type', $discountType);
        $this->putOptional($line, 'discount_value', $discountValue);
        $this->putOptional($line, 'product_uuid', $productUuid);
        $this->putOptionalDate($line, 'operation_date', $operationDate);
        $this->putOptionalDate($line, 'period_start', $periodStart);
        $this->putOptionalDate($line, 'period_end', $periodEnd);

        $this->payload['lines'][] = $line;

        return $this;
    }

    /**
     * Ajoute une ligne brute lorsque le contrat API expose un nouveau champ avant le SDK.
     *
     * @param array<string, mixed> $line
     */
    public function addRawLine(array $line): self
    {
        $this->payload['lines'][] = $line;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }

    private function addTextLine(string $type, string $name, ?string $description): self
    {
        $line = [
            'line_type' => $type,
            'name' => $this->requiredString($name, 'name'),
        ];

        $this->putOptional($line, 'description', $description);
        $this->payload['lines'][] = $line;

        return $this;
    }

    private function setNullableString(string $key, ?string $value): self
    {
        if ($value === null || trim($value) === '') {
            unset($this->payload[$key]);
        } else {
            $this->payload[$key] = trim($value);
        }

        return $this;
    }

    private function setNullableDate(string $key, DateTimeInterface|string|null $value): self
    {
        if ($value === null || $value === '') {
            unset($this->payload[$key]);
        } else {
            $this->payload[$key] = $this->formatDate($value);
        }

        return $this;
    }

    private function formatDate(DateTimeInterface|string $date): string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }

    private function requiredString(string $value, string $field): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException($field . ' ne peut pas être vide.');
        }

        return $value;
    }

    /** @param array<string, mixed> $target */
    private function putOptional(array &$target, string $key, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $target[$key] = is_string($value) ? trim($value) : $value;
        }
    }

    /** @param array<string, mixed> $target */
    private function putOptionalDate(array &$target, string $key, DateTimeInterface|string|null $value): void
    {
        if ($value !== null && $value !== '') {
            $target[$key] = $this->formatDate($value);
        }
    }
}
