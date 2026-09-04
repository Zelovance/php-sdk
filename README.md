# Zelovance PHP SDK

SDK PHP autonome pour l’API publique Zelovance `/api/v1`.

## Fonctionnalités de la version 0.2.0-beta.2

- authentification par clé API technique Bearer ;
- liste et consultation des contacts ;
- liste et consultation des factures ;
- création de factures brouillon ;
- création, lecture et modification partielle d’articles du catalogue ;
- lignes `item`, titres `section` et notes `note` ;
- pagination Laravel ;
- exceptions dédiées pour les réponses 401, 403, 404, 405, 409, 422 et 429 ;
- vérification des signatures HMAC des webhooks ;
- transport HTTP natif cURL, sans dépendance tierce.

Les contacts restent volontairement en lecture seule. L’émission, la modification et la suppression des factures ne sont pas exposées par le SDK, conformément au contrat public actuel.

> Cette préversion est destinée au bêta-test avec `https://dev.zelovance.com`. Les endpoints Articles ne sont pas annoncés comme disponibles en production.

## Prérequis

- PHP 8.2 ou supérieur ;
- extension `curl` ;
- extension `json` ;
- Composer 2.

## Installation locale avec Composer

Décompressez l’archive dans votre application :

```bash
mkdir -p packages
unzip zelovance-php-sdk-0.2.0-beta.2.zip -d packages/zelovance-php-sdk
```

Déclarez ensuite le dépôt local et installez le package :

```bash
composer config repositories.zelovance path ./packages/zelovance-php-sdk
composer require zelovance/php-sdk:0.2.0-beta.2
```

Depuis `v0.2.0-beta.1`, la mise à niveau vers `v0.2.0-beta.2` est compatible : `products()->create()` conserve sa signature et les méthodes de lecture et de modification sont ajoutées.

Pour éviter un lien symbolique vers le dossier du package :

```bash
composer config repositories.zelovance.options.symlink false
composer update zelovance/php-sdk
```

## Configuration

Ne stockez pas la clé API dans le code source.

```dotenv
ZELOVANCE_API_KEY=votre-cle-api
ZELOVANCE_BASE_URL=https://dev.zelovance.com
```

Dans Laravel, placez ces valeurs dans `.env`, puis exposez-les par un fichier de configuration. Dans un script PHP classique, chargez-les avec votre gestionnaire d’environnement habituel.

## Initialisation

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Zelovance\Sdk\ZelovanceClient;

$zelovance = new ZelovanceClient(
    apiKey: $_ENV['ZELOVANCE_API_KEY'],
    baseUrl: $_ENV['ZELOVANCE_BASE_URL'] ?? 'https://dev.zelovance.com',
);
```

## Lister les contacts

```php
$firstPage = $zelovance->contacts()->list();

foreach ($firstPage as $contact) {
    echo $contact['uuid'] . PHP_EOL;
    echo ($contact['company_name'] ?? $contact['last_name'] ?? '-') . PHP_EOL;
}

printf(
    "Page %d sur %d, total : %s\n",
    $firstPage->currentPage(),
    $firstPage->lastPage(),
    $firstPage->total() ?? 'inconnu',
);
```

Parcourir automatiquement toutes les pages :

```php
foreach ($zelovance->contacts()->all() as $contact) {
    echo $contact['uuid'] . PHP_EOL;
}
```

Consulter un contact :

```php
$contact = $zelovance->contacts()->find(
    'b6b6b6b6-1111-4a1a-9d3e-0f1c2b3a4d5e'
);
```

## Créer une facture avec titres de section

```php
use Zelovance\Sdk\Invoices\InvoiceBuilder;

$draft = InvoiceBuilder::forContactUuid(
    idempotencyKey: 'commande-98213',
    contactUuid: 'b6b6b6b6-1111-4a1a-9d3e-0f1c2b3a4d5e',
)
    ->date('2026-08-03')
    ->dueDate('2026-09-02')
    ->operationType('services')
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
        'Les travaux suivent le planning validé.',
    );

$invoice = $zelovance->invoices()->create($draft);

echo $invoice['uuid'];
```

## Créer, lire et modifier un article en bêta-test

Ces exemples utilisent l’environnement de développement. La clé API technique doit disposer de `product:create`, `product:read` ou `product:update` selon l’opération. L’article obtenu peut ensuite être référencé dans une ligne de facture avec son UUID.

```php
$product = $zelovance->products()->create([
    'name' => 'Accompagnement au développement',
    'code' => 'ACCOMP-DEV',
    'type' => 'service',
    'description' => 'Prestation facturée à la journée.',
    'unit_price' => 750,
    'unit' => 'jour',
    'vat_rate' => 20,
]);

$productUuid = $product['uuid'];

$draft = InvoiceBuilder::forContactUuid(
    idempotencyKey: 'commande-98215',
    contactUuid: 'b6b6b6b6-1111-4a1a-9d3e-0f1c2b3a4d5e',
)
    ->date('2026-08-03')
    ->dueDate('2026-09-02')
    ->addItem(
        name: 'Accompagnement au développement',
        quantity: 1,
        unitPrice: 750,
        vatRate: 20,
        unit: 'jour',
        productUuid: $productUuid,
    );

$invoice = $zelovance->invoices()->create($draft);
```

Lister ou parcourir les articles visibles du catalogue :

```php
$firstPage = $zelovance->products()->list(['page' => 1]);

foreach ($zelovance->products()->all() as $catalogProduct) {
    echo $catalogProduct['uuid'] . PHP_EOL;
}

$product = $zelovance->products()->find($productUuid);
```

Modifier un article par `PATCH` :

```php
$product = $zelovance->products()->update($productUuid, [
    'name' => 'Accompagnement au développement — forfait',
    'unit_price' => 800,
]);
```

Les seuls champs acceptés à la création et en modification sont `name`, `code`, `type`, `description`, `unit_price`, `unit` et `vat_rate`. Une modification doit contenir au moins un de ces champs. Les statuts de catalogue et d’activation, la devise, le type de vente, les catégories et les champs internes restent gérés par l’API et sont refusés localement par le SDK.

Pour ne pas afficher d’unité sur une ligne, utilisez `unit: ''` ou `unit: 'none'`.
Le builder normalise une chaîne vide vers la valeur API `none`.

Le SDK accepte aussi directement le tableau conforme au contrat API :

```php
$invoice = $zelovance->invoices()->create([
    'idempotency_key' => 'commande-98214',
    'contact_external_id' => 'crm-42',
    'type' => 'standard',
    'date' => '2026-08-03',
    'due_date' => '2026-09-02',
    'lines' => [
        [
            'line_type' => 'section',
            'name' => 'Développement',
        ],
        [
            'line_type' => 'item',
            'name' => 'Développement du module',
            'quantity' => 5,
            'unit' => 'jour',
            'unit_price' => 500,
            'vat_rate' => 20,
        ],
    ],
]);
```

## Lire les factures

```php
$invoices = $zelovance->invoices()->list(['page' => 1]);

$invoice = $zelovance->invoices()->find(
    '6f2e4b0a-2222-4a1a-9d3e-0f1c2b3a4d5e'
);
```

## Gestion des erreurs

```php
use Zelovance\Sdk\Exceptions\AuthenticationException;
use Zelovance\Sdk\Exceptions\IdempotencyConflictException;
use Zelovance\Sdk\Exceptions\RateLimitException;
use Zelovance\Sdk\Exceptions\ValidationException;

try {
    $invoice = $zelovance->invoices()->create($draft);
} catch (ValidationException $exception) {
    foreach ($exception->errors() as $field => $messages) {
        echo $field . ': ' . implode(', ', $messages) . PHP_EOL;
    }
} catch (AuthenticationException $exception) {
    // Clé absente, invalide, révoquée ou expirée.
} catch (IdempotencyConflictException $exception) {
    // La même clé a été utilisée avec un contenu différent.
    // Créez une nouvelle clé avant de renvoyer la facture modifiée.
} catch (RateLimitException $exception) {
    $retryAfter = $exception->retryAfter();
}
```

Lors de la création d’une facture, une même `idempotency_key` ne doit être réutilisée qu’avec exactement le même contenu. Un rejeu identique renvoie la facture existante. Si le contenu est modifié, l’API répond avec HTTP 409 et le SDK lève `IdempotencyConflictException` ; utilisez alors une nouvelle clé d’idempotence.

## Vérifier un webhook

```php
use Zelovance\Sdk\Webhooks\SignatureVerifier;

$rawBody = file_get_contents('php://input') ?: '';
$received = $_SERVER['HTTP_X_ZELOVANCE_SIGNATURE'] ?? '';

if (! SignatureVerifier::verify(
    rawBody: $rawBody,
    receivedSignature: $received,
    secret: $_ENV['ZELOVANCE_WEBHOOK_SECRET'],
)) {
    http_response_code(401);
    exit('Signature invalide');
}
```

La signature est vérifiée sur le corps JSON brut, avant son décodage.

## Tests locaux du package

```bash
php tests/smoke.php
```

Après installation avec Composer :

```bash
composer test
```

## Limites de cette première version

- aucune création ou modification de contact ;
- aucune modification, suppression ou émission de facture ;
- aucune suppression, archivage ou catégorisation d’article ;
- les résultats sont retournés sous forme de tableaux PHP afin de conserver le contrat JSON sans perte ;
- les paramètres de filtrage des listes sont transmis tels quels, mais seuls ceux réellement pris en charge par l’API Zelovance auront un effet.

## Licence

Ce SDK est distribué sous licence MIT. Consultez [LICENSE](LICENSE).
