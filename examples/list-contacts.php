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

use Zelovance\Sdk\Exceptions\ZelovanceException;
use Zelovance\Sdk\ZelovanceClient;

$apiKey = getenv('ZELOVANCE_API_KEY');
$baseUrl = getenv('ZELOVANCE_BASE_URL') ?: 'https://dev.zelovance.com';

if (! is_string($apiKey) || $apiKey === '') {
    fwrite(STDERR, "Définissez ZELOVANCE_API_KEY.\n");
    exit(1);
}

$zelovance = new ZelovanceClient($apiKey, $baseUrl);

try {
    foreach ($zelovance->contacts()->all() as $contact) {
        printf(
            "%s | %s | %s\n",
            $contact['uuid'] ?? '-',
            $contact['company_name'] ?? trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')),
            $contact['email'] ?? '-',
        );
    }
} catch (ZelovanceException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
