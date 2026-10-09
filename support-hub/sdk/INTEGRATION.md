# Brancher une application sur le centre d'assistance NLapps

Guide destiné à un développeur ou à une IA de développement. Il décrit le protocole exact de l'API du centre d'assistance NLapps (`api.php`), utilisable depuis n'importe quel langage. Une implémentation de référence en PHP est fournie dans ce dossier : `NlappsSupport.php` (client de l'API), `examples/relay.php` (relais serveur), `nlapps-chat.js` (widget de discussion).

> **À copier dans la demande faite à l'IA :**
> « Intègre à mon application le chat d'assistance NLapps en respectant strictement le document INTEGRATION.md ci-joint : appels de serveur à serveur avec l'en-tête X-Api-Key, la clé ne doit jamais atteindre le navigateur ; le navigateur parle uniquement à un relais de mon application ; conversation ouverte avec `open`, messages envoyés avec `send`, nouveaux messages récupérés toutes les 4 secondes avec `poll` en passant `after` ; gestion des statuts open / pending / closed, de la note de satisfaction et des images. Identifiant de l'application : `<mon-appli>`. »

---

## 1. Principe

```
Navigateur de l'utilisateur  ──►  Serveur de votre application (relais)  ──►  Centre d'assistance NLapps (api.php)
        (widget de chat)            connaît la clé, la conversation                console des conseillers
```

- **La clé API reste sur le serveur** de l'application. Le navigateur n'appelle jamais `api.php` directement : il appelle une route de votre application (le « relais »), qui vérifie que l'utilisateur est connecté puis appelle le centre d'assistance.
- **Une clé = un client** (une installation de votre application chez un client). Elle est créée dans la console : *Applications → [votre application] → Parc clients → Nouveau client*. Elle commence par `nlh_` et n'est affichée qu'une fois.
- La même clé sert aussi à la **licence**, aux **mises à jour**, à la **FAQ partagée** et aux **tutoriels vidéo** (section 6). Le chat ne demande que les sections 2 à 5.

## 2. Appels HTTP

- Adresse : `https://<domaine>/assistance/api.php?a=<action>` (exemple : `https://nlapps.fr/assistance/api.php?a=open`).
- En-tête obligatoire sur chaque appel : `X-Api-Key: nlh_…`
- `POST` : corps **JSON** (`Content-Type: application/json`). `GET` : paramètres dans l'URL.
- Réponses en JSON UTF-8, sauf `file`, `download` et `video` (contenu binaire).
- Délai conseillé : 8 secondes (120 secondes pour les téléchargements).

| Code | Signification | Que faire |
|---|---|---|
| 200 | OK | — |
| 400 | Action inconnue | Corriger le paramètre `a`. |
| 401 | Clé invalide ou client désactivé | Ne pas réessayer en boucle ; afficher « assistance indisponible » ; prévenir l'administrateur. |
| 402 | Licence non à jour (`download`, `video`) | Ne pas télécharger. |
| 404 | Conversation introuvable (mauvais `id`/`token`, ou clé changée) | Oublier la conversation stockée et en ouvrir une nouvelle au prochain message. |
| 422 | Message vide, image refusée | Afficher le message d'erreur (`error`). |

Toute erreur renvoie `{"error": "texte lisible en français"}`.

## 3. Disponibilité de l'équipe

`GET api.php?a=status`

```json
{ "online": true, "operator": "NLapps", "away_message": "Nous ne sommes pas disponibles pour le moment : laissez votre message…" }
```

Avant d'ouvrir une conversation, afficher si un conseiller est disponible. Si `online` vaut `false`, afficher `away_message` : l'utilisateur peut quand même écrire, la réponse viendra plus tard.

## 4. La conversation

### 4.1 Ouvrir — `POST api.php?a=open`

À appeler au **premier message** de l'utilisateur vers un conseiller.

```json
{
  "user": { "name": "Claire Dubois", "email": "claire@client.fr", "role": "Secrétaire", "center": "Centre Les Tilleuls" },
  "message": "Je n'arrive pas à valider ma commande.",
  "context": "Application : mon-appli 2.3.0 · page : Panier · navigateur : Safari iOS",
  "transcript": [
    { "from": "user", "text": "comment valider une commande ?" },
    { "from": "bot",  "text": "Ouvrez le panier puis…" }
  ]
}
```

- `message` : obligatoire, 4 000 caractères maximum.
- `user` : affiché au conseiller. `email` sert à envoyer la transcription à la clôture. Tous les champs sont facultatifs mais vivement conseillés.
- `context` : 2 000 caractères maximum, visible uniquement par le conseiller (version, page, centre, réglages utiles au dépannage). **Jamais de mot de passe ni de donnée de santé.**
- `transcript` : facultatif, les 12 derniers échanges avec votre chatbot, s'il y en a un. `from` vaut `user` ou `bot`.

Réponse :

```json
{ "id": 42, "token": "9f2c…32 caractères hexadécimaux…", "status": { "online": true, "operator": "NLapps", "away_message": "…" },
  "messages": [ { "id": 101, "from": "user", "text": "Je n'arrive pas…", "at": "2026-10-08 14:05:12", "file": null, "author": null } ] }
```

`messages` contient aussi les échanges du chatbot transmis (`from` = `user_bot` / `bot`) : à filtrer comme pour `poll`.

**Conserver `id` et `token` côté serveur**, en session ou en base, liés à l'utilisateur. Le `token` est le secret de la conversation : il ne doit pas aller au navigateur. Une seule conversation ouverte à la fois par utilisateur.

### 4.2 Envoyer un message — `POST api.php?a=send`

```json
{ "id": 42, "token": "9f2c…", "text": "Voici le numéro de commande : 1234" }
```
→ `{ "ok": true, "id": 102 }`

### 4.3 Recevoir les réponses — `GET api.php?a=poll&id=42&token=9f2c…&after=102`

`after` = identifiant du dernier message déjà affiché (0 au début). Seuls les messages plus récents sont renvoyés.

```json
{
  "status": "open",
  "rated": false,
  "availability": { "online": true, "operator": "NLapps", "away_message": "…" },
  "messages": [
    { "id": 103, "from": "agent", "text": "Bonjour Claire, je regarde.", "at": "2026-10-08 14:06:40", "file": null, "author": "Julie Martin" }
  ]
}
```

- **Fréquence** : toutes les 4 secondes quand la fenêtre de chat est ouverte, toutes les 30 à 60 secondes en arrière-plan (pour afficher une pastille « nouvelle réponse »). Arrêter quand `status` vaut `closed`.
- Champ `from` d'un message :

| `from` | Origine | À afficher à l'utilisateur ? |
|---|---|---|
| `user` | l'utilisateur | oui, à droite |
| `agent` | un conseiller (`author` = son nom, peut être vide) | oui, à gauche, avec le nom |
| `system` | événement (clôture, note…) | oui, centré, discret |
| `bot`, `user_bot` | échange avec le chatbot transmis à l'ouverture | **non** (réservé au conseiller) |

- `at` : date et heure du serveur d'assistance (`AAAA-MM-JJ HH:MM:SS`, heure de Paris).
- `file` : nom d'une image jointe, à afficher via `file` (§ 4.5).
- `status` :

| `status` | Signification | Comportement |
|---|---|---|
| `open` | en cours, le conseiller doit répondre | normal |
| `pending` | le conseiller attend une réponse de l'utilisateur | normal (on peut afficher « en attente de votre réponse ») |
| `closed` | conversation résolue | arrêter le suivi, proposer la note (§ 4.6), oublier `id`/`token` ; le prochain message ouvrira une nouvelle conversation |

### 4.4 Terminer — `POST api.php?a=close`

```json
{ "id": 42, "token": "9f2c…" }
```
→ `{ "ok": true }` (bouton « Terminer la conversation » côté utilisateur).

### 4.5 Images (captures d'écran)

- Envoyer : `POST api.php?a=attach` avec `{ "id": 42, "token": "9f2c…", "data": "<image en base64, sans préfixe data:>", "text": "Capture de l'écran" }`. Formats JPEG, PNG ou WebP, 4 Mo maximum, sinon erreur 422. Réponse : `{ "ok": true, "id": 104 }`.
- Afficher : `GET api.php?a=file&id=42&token=9f2c…&f=<nom du fichier>` renvoie l'image (binaire, bon `Content-Type`). Le relais doit la retransmettre au navigateur : jamais d'URL contenant la clé ou le jeton dans la page.

### 4.6 Note de satisfaction — `POST api.php?a=rate`

Quand `status` passe à `closed` et que `rated` vaut `false`, proposer une note de 1 à 5 étoiles et un commentaire facultatif :

```json
{ "id": 42, "token": "9f2c…", "rating": 5, "comment": "Rapide et efficace" }
```

## 5. Le relais (côté serveur de votre application)

Le navigateur appelle **une seule route** de votre application, par exemple `POST /support/chat`, avec `{ "action": "send" | "poll" | "close" | "attach" | "rate", … }`. Le relais :

1. vérifie que l'utilisateur est connecté (session de votre application) ;
2. récupère la conversation stockée (`id`, `token`) de cet utilisateur ;
3. appelle l'action correspondante de l'API avec la clé ;
4. renvoie au navigateur les messages **filtrés** (`user`, `agent`, `system` uniquement), le `status`, la disponibilité, **sans jamais** le `token` ni la clé ;
5. au statut `closed` ou sur une erreur 404, efface la conversation stockée.

Voir `examples/relay.php` pour un relais complet de 60 lignes.

Côté navigateur, le parcours recommandé (celui de Centriva) : une bulle « Aide » → le chatbot ou la FAQ de l'application répond d'abord → bouton « Parler à un conseiller » → ouverture de la conversation (`open`, avec la transcription du chatbot) → suivi toutes les 4 secondes → à la clôture, note et transcription envoyée par e-mail par le centre d'assistance. Le widget `nlapps-chat.js` fournit cette interface prête à l'emploi.

## 6. Licence, mises à jour, FAQ et vidéos (facultatif pour le chat)

`POST api.php?a=check`, toutes les 10 minutes (tâche planifiée) :

```json
{ "app": "mon-appli", "version": "2.3.0", "url": "https://achats.client.fr/", "php": "8.2", "stats": { "users": 12 }, "faq_hash": "", "videos_hash": "" }
```

- `app` : **identifiant technique** de l'application, tel que défini dans la console (*Gérer les applications*). Il range le client dans le bon sous-menu.
- Réponse :
  - `licence` = `{ status: active|grace|expired|suspended, plan, paid_until, grace_until, days_left, ai, message, contact }` : couper l'accès à l'application si `status` vaut `expired` ou `suspended` ;
  - `latest` = dernière version publiée `{ version, notes, date, size, sha256, downloadable }` ;
  - `faq` et `videos` = `{ hash, items? }` : les `items` ne sont renvoyés que si le `hash` envoyé a changé ;
  - `status` = disponibilité de l'équipe.
- `GET api.php?a=download&v=2.4.0` : paquet de mise à jour (zip). Vérifier son empreinte SHA-256 avec `latest.sha256`.
- `GET api.php?a=video&id=<uid>` : fichier d'un tutoriel vidéo (MP4), à télécharger une fois puis à servir localement.
- `GET api.php?a=faq` : FAQ partagée seule.

## 7. Règles à respecter (liste de contrôle)

- [ ] La clé `nlh_…` est dans la configuration serveur (variable d'environnement ou fichier hors du web), jamais dans le code envoyé au navigateur ni dans une URL.
- [ ] Le `token` de conversation reste côté serveur.
- [ ] Les messages `bot` et `user_bot` ne sont pas montrés à l'utilisateur.
- [ ] Le suivi utilise `after` (pas de rechargement complet) et s'arrête à `closed`.
- [ ] Erreur 404 : la conversation stockée est oubliée. Erreur 401 : pas de nouvelle tentative en boucle.
- [ ] Le texte des messages est échappé à l'affichage (pas de HTML interprété).
- [ ] `context` ne contient ni mot de passe ni donnée personnelle sensible.
- [ ] Délai réseau limité (8 s) : une coupure du centre d'assistance ne bloque jamais l'application.

## 8. Test rapide en ligne de commande

```bash
KEY=nlh_votre_cle; API=https://nlapps.fr/assistance/api.php
curl -s -H "X-Api-Key: $KEY" "$API?a=status"
curl -s -H "X-Api-Key: $KEY" -H 'Content-Type: application/json' \
     -d '{"user":{"name":"Test"},"message":"Bonjour, ceci est un test"}' "$API?a=open"
# → noter id et token, puis :
curl -s -H "X-Api-Key: $KEY" "$API?a=poll&id=ID&token=TOKEN&after=0"
```

La conversation apparaît aussitôt dans la console, rubrique *Conversations*, avec le nom de l'application.
