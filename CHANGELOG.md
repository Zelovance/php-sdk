# Changelog

## Non publié

- ajout de `IdempotencyConflictException` pour les conflits d’idempotence HTTP 409 lors de la création d’une facture ;
- une unité vide passée à `InvoiceBuilder::addItem()` crée désormais une ligne sans unité affichée ;
- `create()` et `find()` retournent directement la ressource contenue dans l’enveloppe Laravel `data`.

## 0.1.0 - 2026-08-03

- première version testable du SDK PHP Zelovance ;
- lecture des contacts ;
- lecture et création des factures ;
- prise en charge des lignes `section`, `note` et `item` ;
- pagination, exceptions API et vérification HMAC des webhooks.
