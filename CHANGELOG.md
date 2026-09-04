# Changelog

## 0.2.0-beta.2 - 2026-09-04

- ajout de la lecture paginée, du parcours complet et de la consultation d’articles ;
- ajout de la modification partielle d’un article avec `PATCH /api/v1/products/{uuid}` ;
- validation locale des UUID, du payload de modification vide et des champs non publics ;
- compatibilité source conservée avec `v0.2.0-beta.1` pour `products()->create()` ;
- préversion réservée au bêta-test sur `https://dev.zelovance.com`, sans annonce de disponibilité en production.

## 0.2.0-beta.1 - 2026-09-04

- ajout de `ProductsResource` et de `ZelovanceClient::products()` ;
- création d’articles avec `POST /api/v1/products` ;
- prise en charge de l’enveloppe Laravel `data` pour l’article créé ;
- préversion réservée au bêta-test sur `https://dev.zelovance.com`.

## 0.1.0 - 2026-08-04

- première version testable du SDK PHP Zelovance ;
- lecture des contacts ;
- lecture et création des factures ;
- prise en charge des lignes `section`, `note` et `item` ;
- prise en charge des lignes sans unité affichée ;
- gestion dédiée des conflits d’idempotence HTTP 409 ;
- extraction automatique des ressources contenues dans l’enveloppe Laravel `data` ;
- pagination, exceptions API et vérification HMAC des webhooks.
