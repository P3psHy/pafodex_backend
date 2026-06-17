# Changements Backend Impactant Le Frontend

Ce document résume les changements de sécurité appliqués à l'API Symfony et ce que le frontend doit adapter.

## 1. Authentification API

L'API utilise maintenant exclusivement un token Bearer dans le header HTTP `Authorization`.

Avant, certaines routes acceptaient aussi le token dans l'URL avec `?apiToken=...`.
Ce fonctionnement est supprimé pour éviter que le token apparaisse dans les logs, l'historique navigateur ou les outils de monitoring.

### A Faire Cote Front

Envoyer le token comme ceci sur toutes les routes authentifiees :

```http
Authorization: Bearer <apiToken>
```

Exemple avec `fetch` :

```js
fetch('/api/me', {
  headers: {
    Authorization: `Bearer ${apiToken}`,
  },
});
```

Exemple avec un body JSON :

```js
fetch('/api/me', {
  method: 'PUT',
  headers: {
    'Content-Type': 'application/json',
    Authorization: `Bearer ${apiToken}`,
  },
  body: JSON.stringify({
    pseudo: 'NouveauPseudo',
  }),
});
```

## 2. Gestion Des Erreurs 401 Et 403

Le backend renvoie maintenant des erreurs plus strictes :

- `401 Unauthorized` : aucun token fourni, token invalide ou utilisateur non connecte.
- `403 Forbidden` : utilisateur connecte mais pas autorise a faire l'action.

### A Faire Cote Front

Pour un `401` :

- supprimer le token stocke cote front ;
- rediriger vers la page de connexion ;
- afficher un message du type "Session expiree, reconnecte-toi".

Pour un `403` :

- ne pas deconnecter l'utilisateur ;
- afficher un message du type "Tu n'as pas les droits pour cette action" ;
- cacher les actions reservees admin si possible.

## 3. Routes Publiques

Ces routes restent accessibles sans token :

```txt
POST /api/register
POST /api/login
GET  /api/cards
GET  /api/card/{id}
GET  /api/gametype
GET  /api/gametype/{id}
```

Les routes de documentation restent accessibles en environnement de developpement, mais sont masquees en production :

```txt
GET /api/docs
GET /api/docs/openapi
GET /api/docs/openapi.yaml
```

## 4. Routes Utilisateur Connecte

Ces routes demandent maintenant obligatoirement :

```http
Authorization: Bearer <apiToken>
```

```txt
POST   /api/logout
GET    /api/me
PUT    /api/me
DELETE /api/me
GET    /api/me/library
GET    /api/me/library/search
GET    /api/me/sets
POST   /api/me/sets
GET    /api/me/sets/{setId}
PUT    /api/me/sets/{setId}
DELETE /api/me/sets/{setId}
POST   /api/me/sets/{setId}/card
DELETE /api/me/sets/{setId}/card/{cardId}
POST   /api/cards/add-user-card
```

## 5. Routes Reservees Admin

Certaines routes globales de modification du catalogue sont maintenant reservees aux utilisateurs ayant `ROLE_ADMIN`.

```txt
POST   /api/cards
PUT    /api/card/{id}
DELETE /api/card/{id}
POST   /api/gametype
PUT    /api/gametype/{id}
DELETE /api/gametype/{id}
```

### A Faire Cote Front

Si l'utilisateur n'est pas admin :

- cacher les boutons de creation/modification/suppression du catalogue global ;
- gerer proprement les reponses `403`.

Important : actuellement, la reponse `/api/me` renvoie seulement :

```json
{
  "id": 1,
  "pseudo": "Pseudo",
  "mail": "user@example.com"
}
```

Elle ne renvoie pas encore les roles. Si le front doit cacher automatiquement les boutons admin, il faudra soit :

- ajouter les roles dans `/api/me` cote backend ;
- soit gerer cette information autrement cote front.

## 6. Changement Sur L'Ajout De Carte Utilisateur

La route suivante a change :

```txt
POST /api/cards/add-user-card
```

Avant, le frontend envoyait un `libraryId`.
Ce n'est plus accepte ni necessaire.

Le backend recupere maintenant la bibliotheque de l'utilisateur directement depuis le token.
Cela evite qu'un utilisateur puisse ajouter une carte dans la bibliotheque de quelqu'un d'autre.

### Ancien Payload

```json
{
  "name": "Carte Exemple",
  "extension": "Extension",
  "number": "001",
  "image": "https://example.com/image.png",
  "gameTypeId": 1,
  "libraryId": 3
}
```

### Nouveau Payload

```json
{
  "name": "Carte Exemple",
  "extension": "Extension",
  "number": "001",
  "image": "https://example.com/image.png",
  "gameTypeId": 1
}
```

Avec le header :

```http
Authorization: Bearer <apiToken>
```

## 7. Validation Plus Stricte A L'Inscription

La route suivante valide davantage les donnees :

```txt
POST /api/register
```

Regles appliquees :

- `pseudo` obligatoire ;
- `pseudo` maximum 50 caracteres ;
- `mail` obligatoire ;
- `mail` doit etre un email valide ;
- `password` obligatoire ;
- `passwordConfirm` obligatoire ;
- `password` et `passwordConfirm` doivent etre identiques ;
- mot de passe minimum 8 caracteres ;
- mot de passe avec au moins une lettre ;
- mot de passe avec au moins un chiffre.

### Exemple Valide

```json
{
  "pseudo": "Enzo",
  "mail": "enzo@example.com",
  "password": "Password123",
  "passwordConfirm": "Password123"
}
```

### A Faire Cote Front

Ajouter les memes validations cote formulaire pour eviter des allers-retours inutiles avec l'API.

## 8. Validation Plus Stricte Sur La Mise A Jour Profil

La route suivante applique aussi les nouvelles validations :

```txt
PUT /api/me
```

Champs possibles :

```json
{
  "pseudo": "NouveauPseudo",
  "mail": "new@example.com",
  "password": "NewPassword123",
  "passwordConfirm": "NewPassword123"
}
```

Regles :

- `pseudo` ne peut pas etre vide ;
- `pseudo` maximum 50 caracteres ;
- `mail` doit etre valide ;
- `mail` ne doit pas deja etre utilise ;
- si `password` est envoye, `passwordConfirm` est obligatoire ;
- si `passwordConfirm` est envoye, `password` est obligatoire ;
- le nouveau mot de passe doit respecter les memes regles que l'inscription.

## 9. JSON Invalide

Le backend verifie maintenant mieux les body JSON.

Si le frontend envoie un JSON invalide ou un body non compatible, l'API peut repondre :

```json
{
  "error": "Invalid JSON body"
}
```

Avec le status :

```txt
400 Bad Request
```

### A Faire Cote Front

Toujours envoyer :

```http
Content-Type: application/json
```

Et convertir le body avec :

```js
JSON.stringify(payload)
```

## 10. CORS

Le backend n'accepte plus toutes les origines avec `*`.

Origines actuellement autorisees :

```txt
http://localhost:3000
http://localhost:5173
```

### A Faire Cote Front

Si le frontend tourne sur un autre port ou un autre domaine, il faudra l'ajouter dans :

```txt
config/packages/nelmio_cors.yaml
```

Exemple :

```yaml
allow_origin:
    - 'http://localhost:3000'
    - 'http://localhost:5173'
    - 'https://ton-front.com'
```

## 11. Stockage Du Token Cote Front

Le backend renvoie toujours un `apiToken` apres :

```txt
POST /api/register
POST /api/login
```

Exemple :

```json
{
  "id": 1,
  "apiToken": "token..."
}
```

Le front doit stocker ce token et l'envoyer ensuite dans le header `Authorization`.

Recommandation :

- eviter de mettre le token dans l'URL ;
- eviter de l'afficher dans les logs ;
- le supprimer au logout ;
- le supprimer si l'API repond `401`.

## 12. Resume Des Adaptations Front

A modifier cote frontend :

- remplacer tous les `?apiToken=...` par le header `Authorization: Bearer ...` ;
- retirer `libraryId` du payload de `/api/cards/add-user-card` ;
- ajouter la gestion des erreurs `401` et `403` ;
- cacher ou proteger les actions admin ;
- ajouter les validations email, pseudo et mot de passe cote formulaire ;
- verifier que l'URL du frontend est autorisee par CORS ;
- toujours envoyer les body JSON avec `Content-Type: application/json`.

