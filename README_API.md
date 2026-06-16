API d'authentification (local testing)

1) Pré-requis
- PHP 8.2+
- Composer
- Base de données configurée dans .env

2) Installer dépendances et exécuter migrations

```bash
composer install
php bin/console doctrine:migrations:migrate
```

3) Lancer le serveur local

```bash
symfony server:start
# ou
php -S 127.0.0.1:8000 -t public
```

4) Endpoints
- POST /api/register
  - Payload JSON: { "pseudo": "enzo", "mail": "enzo@example.com", "password": "secret", "passwordConfirm": "secret" }
  - Réponse: 201 Created { "id": <id>, "apiToken": "..." }

- POST /api/login
  - Payload JSON: { "mail": "enzo@example.com", "password": "secret" }
  - Réponse: 200 OK { "id": <id>, "apiToken": "..." }

- POST /api/logout
  - Header: `Authorization: Bearer <apiToken>` (ou body `apiToken`)
  - Réponse: 200 OK { "success": true }

- GET /api/me
  - Header: `Authorization: Bearer <apiToken>` (ou query `?apiToken=...`)
  - Réponse: 200 OK { "id": ..., "pseudo": "...", "mail": "..." }

5) Tester avec Postman
- Créez une requête POST vers `http://127.0.0.1:8000/api/register` avec header `Content-Type: application/json` et le JSON de l'exemple.
- Ensuite, POST vers `http://127.0.0.1:8000/api/login` pour récupérer un `apiToken`.

6) Utiliser le token pour les appels futurs
- Ajoutez l'en-tête HTTP `Authorization: Bearer <apiToken>` pour les endpoints qui vérifieraient le token (actuellement il n'y a pas d'authenticator dédié, prévoir création d'un `ApiTokenAuthenticator`).

7) Remarques
- Cette implémentation stocke un `apiToken` en clair en base. Pour production, envisagez JWT ou un authenticator dédié et chiffrement approprié.
