<?php

declare(strict_types=1);

$autoloaders = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

foreach ($autoloaders as $autoloader) {
    if (is_file($autoloader)) {
        require $autoloader;
        break;
    }
}

if (! class_exists(\Zelovance\Sdk\ZelovanceClient::class)) {
    fwrite(STDERR, "Autoloader Composer introuvable. Lancez l'exemple depuis un projet où le SDK est installé.\n");
    exit(1);
}

use Zelovance\Sdk\Exceptions\ValidationException;
use Zelovance\Sdk\Invoices\InvoiceBuilder;
use Zelovance\Sdk\ZelovanceClient;

$apiKey = getenv('ZELOVANCE_API_KEY');
$contactUuid = getenv('ZELOVANCE_CONTACT_UUID');
$baseUrl = getenv('ZELOVANCE_BASE_URL') ?: 'https://dev.zelovance.com';

if (! is_string($apiKey) || $apiKey === '' || ! is_string($contactUuid) || $contactUuid === '') {
    fwrite(STDERR, "Définissez ZELOVANCE_API_KEY et ZELOVANCE_CONTACT_UUID.\n");
    exit(1);
}

$zelovance = new ZelovanceClient($apiKey, $baseUrl);

$draft = InvoiceBuilder::forContactUuid(
    idempotencyKey: 'sdk-test-' . date('Ymd-His'),
    contactUuid: $contactUuid,
)
    ->date(date('Y-m-d'))
    ->dueDate(date('Y-m-d', strtotime('+30 days')))
    ->operationType('services')
    ->paymentMethod('virement')
    ->addSection('Conception et préparation')
    ->addItem(
        name: 'Étude et conception',
        quantity: 2,
        unitPrice: 500,
        vatRate: 20,
        unit: 'jour',
    )
    ->addSection('Développement')
    ->addItem(
        name: 'Développement du module',
        quantity: 5,
        unitPrice: 500,
        vatRate: 20,
        unit: 'jour',
    )
    ->addNote(
        'Informations complémentaires',
        'Facture créée avec le SDK PHP Zelovance.',
    );

try {
    $invoice = $zelovance->invoices()->create($draft);
    echo json_encode($invoice, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (ValidationException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, json_encode($exception->errors(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
