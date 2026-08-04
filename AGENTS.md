# Instructions permanentes pour les agents

## Périmètre

- Ce dépôt contient le SDK PHP officiel de l’API Zelovance.
- Préserver la compatibilité avec PHP 8.2 et le contrat documenté par l’application Zelovance.
- Ne jamais ajouter de clé API, secret de webhook, identifiant réel ou fichier `.env`.

## Langue et typographie

- Tout texte français destiné aux utilisateurs, développeurs ou agents doit être rédigé en UTF-8 avec les accents français corrects.
- Cette règle s’applique aux messages d’erreur, documentations, commentaires, exemples, prompts et comptes rendus.
- Relire les textes ajoutés pour détecter les accents manquants et les erreurs d’encodage comme `Ã©`, `Ã¨` ou `�`.

## Qualité

- Exécuter `composer test` après toute modification fonctionnelle.
- Ajouter un test de non-régression pour chaque correction.
- Maintenir `README.md` et `CHANGELOG.md` lorsque le contrat public évolue.
- Conserver les identifiants publics et ne jamais exposer les identifiants numériques internes de Zelovance.
