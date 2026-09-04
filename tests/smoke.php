<?php

declare(strict_types=1);

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'Zelovance\\Sdk\\Tests\\' => $root . '/tests/',
        'Zelovance\\Sdk\\' => $root . '/src/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $file = $directory . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require $file;
        }
    }
});

use Zelovance\Sdk\Exceptions\ValidationException;
use Zelovance\Sdk\Exceptions\ApiException;
use Zelovance\Sdk\Exceptions\AuthorizationException;
use Zelovance\Sdk\Exceptions\IdempotencyConflictException;
use Zelovance\Sdk\Exceptions\RateLimitException;
use Zelovance\Sdk\Http\Response;
use Zelovance\Sdk\Invoices\InvoiceBuilder;
use Zelovance\Sdk\Tests\FakeTransport;
use Zelovance\Sdk\Webhooks\SignatureVerifier;
use Zelovance\Sdk\ZelovanceClient;

function assertTrue(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$contactUuid = 'b6b6b6b6-1111-4a1a-9d3e-0f1c2b3a4d5e';
$invoiceUuid = '6f2e4b0a-2222-4a1a-9d3e-0f1c2b3a4d5e';
$productUuid = 'a3d80ae2-02d5-45b2-8a4d-16a6e11f7654';

$transport = new FakeTransport(
    new Response(200, ['content-type' => 'application/json'], json_encode([
        'data' => [['uuid' => $contactUuid, 'company_name' => 'Client test']],
        'links' => ['next' => null],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 1],
    ], JSON_THROW_ON_ERROR)),
    new Response(200, ['content-type' => 'application/json'], json_encode([
        'data' => ['uuid' => $contactUuid, 'company_name' => 'Client test'],
    ], JSON_THROW_ON_ERROR)),
    new Response(201, ['content-type' => 'application/json'], json_encode([
        'data' => [
            'uuid' => $productUuid,
            'type' => 'service',
            'code' => 'ACCOMP-DEV',
            'name' => 'Accompagnement au développement',
            'description' => 'Prestation facturée à la journée.',
            'unit_price' => 750,
            'currency' => 'EUR',
            'unit' => 'jour',
            'vat_rate' => 20,
            'is_active' => true,
            'is_catalog' => true,
        ],
    ], JSON_THROW_ON_ERROR)),
    new Response(201, ['content-type' => 'application/json'], json_encode([
        'data' => [
            'uuid' => $invoiceUuid,
            'status' => 'draft',
        ],
    ], JSON_THROW_ON_ERROR)),
    new Response(200, ['content-type' => 'application/json'], json_encode([
        'data' => [
            'uuid' => $invoiceUuid,
            'status' => 'draft',
        ],
    ], JSON_THROW_ON_ERROR)),
    new Response(422, ['content-type' => 'application/json'], json_encode([
        'message' => 'Données invalides.',
        'errors' => ['date' => ['La date est obligatoire.']],
    ], JSON_THROW_ON_ERROR)),
    new Response(409, ['content-type' => 'application/json', 'x-request-id' => 'request-409'], json_encode([
        'message' => 'Cette clé d’idempotence a déjà été utilisée avec un contenu différent.',
        'code' => 'idempotency_conflict',
    ], JSON_THROW_ON_ERROR)),
    new Response(429, ['content-type' => 'application/json', 'retry-after' => '60'], json_encode([
        'message' => 'Trop de requêtes.',
    ], JSON_THROW_ON_ERROR)),
    new Response(403, ['content-type' => 'application/json'], json_encode([
        'message' => 'Ability product:create requise.',
    ], JSON_THROW_ON_ERROR)),
);

$client = new ZelovanceClient('test-secret', 'https://dev.zelovance.com', $transport);
assertTrue(ZelovanceClient::VERSION === '0.2.0-beta.1', 'La version du client est incorrecte.');
$contacts = $client->contacts()->list();
assertTrue($contacts->count() === 1, 'La pagination des contacts est incorrecte.');
assertTrue($contacts->items()[0]['uuid'] === $contactUuid, 'Le contact retourné est incorrect.');
assertTrue(str_contains($transport->requests[0]['headers']['Authorization'], 'Bearer test-secret'), 'Le header Bearer est absent.');

$contact = $client->contacts()->find($contactUuid);
assertTrue($contact['uuid'] === $contactUuid, 'L’enveloppe data du contact n’est pas retirée.');

$productPayload = [
    'name' => 'Accompagnement au développement',
    'code' => 'ACCOMP-DEV',
    'type' => 'service',
    'description' => 'Prestation facturée à la journée.',
    'unit_price' => 750,
    'unit' => 'jour',
    'vat_rate' => 20,
];

$product = $client->products()->create($productPayload);
assertTrue($product['uuid'] === $productUuid, 'L’UUID de l’article créé est incorrect.');
assertTrue($product['currency'] === 'EUR', 'L’enveloppe data de l’article n’est pas retirée.');
assertTrue($transport->requests[2]['method'] === 'POST', 'La méthode de création d’article est incorrecte.');
assertTrue($transport->requests[2]['url'] === 'https://dev.zelovance.com/api/v1/products', 'L’URL de création d’article est incorrecte.');
assertTrue(
    json_decode((string) $transport->requests[2]['body'], true, 512, JSON_THROW_ON_ERROR) === $productPayload,
    'Le payload de création d’article est incorrect.',
);

$draft = InvoiceBuilder::forContactUuid('order-1', $contactUuid)
    ->date('2026-08-03')
    ->dueDate('2026-09-02')
    ->addSection('Développement', 'Phase principale')
    ->addItem(
        name: 'Prestation',
        quantity: 1,
        unitPrice: 500,
        vatRate: 20,
        unit: '',
        description: 'Conception et développement.',
        productUuid: $productUuid,
    )
    ->addNote('Information', 'Les travaux suivent le planning validé.');

$invoice = $client->invoices()->create($draft);
assertTrue($invoice['uuid'] === $invoiceUuid, 'La facture retournée est incorrecte.');

$sentPayload = json_decode((string) $transport->requests[3]['body'], true, 512, JSON_THROW_ON_ERROR);
assertTrue($sentPayload['lines'][0]['line_type'] === 'section', 'Le titre de section n\'est pas envoyé.');
assertTrue($sentPayload['lines'][0]['description'] === 'Phase principale', 'La description de section n’est pas envoyée.');
assertTrue($sentPayload['lines'][1]['line_type'] === 'item', 'La ligne facturée n\'est pas envoyée.');
assertTrue($sentPayload['lines'][1]['unit'] === 'none', 'L’unité vide n’est pas normalisée vers none.');
assertTrue($sentPayload['lines'][1]['description'] === 'Conception et développement.', 'La description de ligne n’est pas envoyée.');
assertTrue($sentPayload['lines'][1]['product_uuid'] === $productUuid, 'L’UUID de l’article n’est pas transmis à la ligne de facture.');
assertTrue($sentPayload['lines'][2]['line_type'] === 'note', 'La note de facture n’est pas envoyée.');
assertTrue($sentPayload['lines'][2]['description'] === 'Les travaux suivent le planning validé.', 'La description de note n’est pas envoyée.');

$foundInvoice = $client->invoices()->find($invoiceUuid);
assertTrue($foundInvoice['uuid'] === $invoiceUuid, 'L’enveloppe data de la facture n’est pas retirée.');

try {
    $client->get('/api/v1/test-validation');
    throw new RuntimeException('ValidationException attendue.');
} catch (ValidationException $exception) {
    assertTrue(isset($exception->errors()['date']), 'Les erreurs 422 ne sont pas exposées.');
}

try {
    $client->get('/api/v1/test-idempotency-conflict');
    throw new RuntimeException('IdempotencyConflictException attendue.');
} catch (IdempotencyConflictException $exception) {
    assertTrue($exception instanceof ApiException, 'Le conflit d’idempotence doit rester une ApiException.');
    assertTrue($exception->statusCode() === 409, 'Le statut 409 du conflit d’idempotence n’est pas conservé.');
    assertTrue(str_contains($exception->getMessage(), 'Cette clé d’idempotence'), 'Le message du conflit d’idempotence n’est pas conservé.');
    assertTrue(($exception->responseData()['code'] ?? null) === 'idempotency_conflict', 'Le code du conflit d’idempotence n’est pas exposé.');
    assertTrue(($exception->responseHeaders()['x-request-id'] ?? null) === 'request-409', 'Les en-têtes du conflit d’idempotence ne sont pas conservés.');
}

try {
    $client->get('/api/v1/test-rate-limit');
    throw new RuntimeException('RateLimitException attendue.');
} catch (RateLimitException $exception) {
    assertTrue($exception->statusCode() === 429, 'Le mapping 429 a régressé.');
    assertTrue($exception->retryAfter() === 60, 'Le délai du mapping 429 a régressé.');
}

try {
    $client->get('/api/v1/test-authorization');
    throw new RuntimeException('AuthorizationException attendue.');
} catch (AuthorizationException $exception) {
    assertTrue($exception->statusCode() === 403, 'Le mapping 403 a régressé.');
}

$body = '{"event":"invoice.created"}';
$secret = 'webhook-secret';
$signature = hash_hmac('sha256', $body, $secret);
assertTrue(SignatureVerifier::verify($body, $signature, $secret), 'La signature HMAC valide est refusée.');
assertTrue(! SignatureVerifier::verify($body, 'invalid', $secret), 'La signature HMAC invalide est acceptée.');

echo "Tous les tests smoke sont passés.\n";
