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

use Zelovance\Sdk\Webhooks\SignatureVerifier;

$rawBody = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_ZELOVANCE_SIGNATURE'] ?? '';
$secret = getenv('ZELOVANCE_WEBHOOK_SECRET') ?: '';

if (! SignatureVerifier::verify($rawBody, $signature, $secret)) {
    http_response_code(401);
    exit('Signature Zelovance invalide');
}

$event = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

// Traitez ici $event['event'], $event['uuid'], etc.
http_response_code(204);
