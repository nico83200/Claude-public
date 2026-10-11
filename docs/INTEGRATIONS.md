# Intégrations externes

## Assistant vocal (Anthropic + ElevenLabs)

Voir [ASSISTANT.md](ASSISTANT.md). Clés dans **Administration › Assistant IA** (ou `ANTHROPIC_API_KEY`, `ELEVENLABS_API_KEY`).
Sans clé Anthropic l'assistant est inactif ; sans clé ElevenLabs seule la saisie au clavier est possible.

## Réglages depuis l'administration

**Administration › Intégrations** permet au super-administrateur de saisir les réglages email (SMTP) et Stripe
sans toucher au serveur. Ils priment sur le `.env`, qui reste la valeur de repli.

- Mots de passe et clés secrètes chiffrés en base avec `APP_KEY` (jamais en clair dans le cache ni le journal d'audit),
  jamais réaffichés (seulement `••••` + 4 derniers caractères). Champ vide = valeur conservée ; case « Effacer » = retour au `.env`.
- **Si `APP_KEY` change, les secrets enregistrés deviennent illisibles** et doivent être ressaisis.
- Boutons « Envoyer un email de test » et « Tester la connexion » Stripe ; URL du webhook et liste des événements affichées.
- Clés contrôlées (préfixes `pk_`, `sk_`/`rk_`, `whsec_`, même mode test/live). Chaque modification est tracée dans le journal.

## Codes promo et accès offerts

**Administration › Codes promo** :

- **Réduction** (pourcentage ou montant, sur la première facture / N mois / toute la durée, offres concernées,
  validité, nombre d'utilisations, réservé aux nouveaux clients) : crée un *coupon* et un *code promotionnel* dans Stripe,
  qui applique la remise. L'utilisateur saisit le code sur la page Abonnement au moment de souscrire ; il est vérifié
  puis pré-appliqué au Stripe Checkout. Sans code saisi, la page Stripe accepte toujours la saisie d'un code.
  L'utilisation est enregistrée à la confirmation du paiement (webhook `checkout.session.completed`).
- **Accès gratuit pour une durée** : le code (« J'ai un code d'accès » sur la page Abonnement) crée une licence
  de l'offre choisie pendant N jours, sans carte bancaire ; une utilisation par espace, refusé si un abonnement payant
  est en cours ; expiration automatique par `licenses:refresh` (passage en lecture seule, données conservées).
- Désactiver un code le désactive aussi dans Stripe ; les avantages déjà accordés sont conservés.
- Accès offert sans code : fiche organisation › « Offrir un accès » avec une durée en jours.
- Saisie de code limitée à 10 essais par minute.

## Stripe (paiements) — à configurer

Depuis **Administration › Intégrations** (recommandé) ou dans le `.env` :

| Variable | Où la trouver |
|---|---|
| `STRIPE_KEY`, `STRIPE_SECRET` | Dashboard Stripe > Développeurs > Clés API |
| `STRIPE_WEBHOOK_SECRET` | Dashboard > Webhooks > point de terminaison > « Secret de signature » |

- Souscription via **Stripe Checkout** (mode `subscription`), gestion des moyens de paiement et factures via le **portail client Stripe** : aucune donnée de carte ne transite par l'application.
- Activation par webhook signé, ou dès le retour de Checkout : la session est relue auprès de l'API Stripe (appartenance à l'espace et statut `complete` vérifiés). Un abonnement inconnu cité par `checkout.session.completed` ou une facture est relu chez Stripe : l'activation ne dépend pas de la réception des événements `customer.subscription.*` ; idempotence par identifiant d'événement ; événements hors ordre ignorés ; échecs journalisés, retentés par Stripe (HTTP 500), alerte aux super-administrateurs au 3ᵉ échec, retraitement manuel dans Admin > Facturation.
- Remboursements : effectués depuis le dashboard Stripe (politique commerciale à définir) ; l'application enregistre `charge.refunded`.
- TVA : non calculée par l'application. Activer **Stripe Tax** si nécessaire (le Checkout collecte adresse et n° de TVA).
- Sans clés : l'application fonctionne (offre gratuite, licences manuelles) et l'écran d'abonnement indique que le paiement n'est pas configuré.

## Email

Depuis **Administration › Intégrations** (recommandé, avec email de test) ou dans le `.env` :
`MAIL_MAILER=smtp` + `MAIL_HOST/PORT/USERNAME/PASSWORD/FROM_*` (tout fournisseur SMTP :
Brevo, Mailgun, Postmark, SMTP de l'hébergeur…). Tant que `MAIL_MAILER=log`, les emails
(vérification, réinitialisation, invitations, notifications) sont écrits dans `storage/logs`.
**La vérification d'email et les invitations nécessitent un service email configuré en production.**
Les envois passent par la file (`QUEUE_CONNECTION=database`), traitée par le planificateur.

## Synchronisation de calendrier externe

Non incluse dans cette version ; la colonne `calendar_events.external_uid` (unique) est prévue
pour un import/export iCal sans doublon.
