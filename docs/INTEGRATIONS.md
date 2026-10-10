# Intégrations externes

## Stripe (paiements) — à configurer

| Variable | Où la trouver |
|---|---|
| `STRIPE_KEY`, `STRIPE_SECRET` | Dashboard Stripe > Développeurs > Clés API |
| `STRIPE_WEBHOOK_SECRET` | Dashboard > Webhooks > point de terminaison > « Secret de signature » |

- Souscription via **Stripe Checkout** (mode `subscription`), gestion des moyens de paiement et factures via le **portail client Stripe** : aucune donnée de carte ne transite par l'application.
- Activation uniquement par webhook signé ; idempotence par identifiant d'événement ; événements hors ordre ignorés ; échecs journalisés, retentés par Stripe (HTTP 500), alerte aux super-administrateurs au 3ᵉ échec, retraitement manuel dans Admin > Facturation.
- Remboursements : effectués depuis le dashboard Stripe (politique commerciale à définir) ; l'application enregistre `charge.refunded`.
- TVA : non calculée par l'application. Activer **Stripe Tax** si nécessaire (le Checkout collecte adresse et n° de TVA).
- Sans clés : l'application fonctionne (offre gratuite, licences manuelles) et l'écran d'abonnement indique que le paiement n'est pas configuré.

## Recherche d'identité des chevaux

Architecture à adaptateurs (`app/Services/HorseSearch`) : chaque source implémente
`HorseIdentitySource` (clé, libellé, fiabilité `official|reference|suggested`, recherche).
Ajouter une source = une classe + son enregistrement dans `HorseSearchService::sources()`.
Délai d'attente (`HORSE_SEARCH_TIMEOUT`), cache (`HORSE_SEARCH_CACHE_MINUTES`), limitation
à 10 recherches/minute/utilisateur, échec d'une source sans bloquer les autres.

| Source | État | Notes |
|---|---|---|
| **Wikidata** | Intégrée, sans clé | API publique `wbsearchentities` + `wbgetentities`, données CC0. Couvre surtout les chevaux notables (sport, courses, étalons). Respecter la politique de Wikimedia : renseigner `HORSE_SEARCH_USER_AGENT` avec un contact réel. Wikimedia applique des limites de débit par IP (constaté en 429 depuis l'IP partagée de l'environnement de développement : l'intégration est donc testée avec des réponses simulées). |
| **Brave Search API** | Intégrée, désactivée sans clé | `BRAVE_SEARCH_API_KEY` (https://brave.com/search/api/). Renvoie des pages candidates marquées « simple suggestion » ; **aucune extraction automatique** des pages tierces (respect des conditions d'utilisation des sites). |
| **IFCE / SIRE** (registre officiel français) | **Non intégrée** | Pas d'API publique librement accessible constatée. L'IFCE propose des services de données aux partenaires sous convention : à demander à l'IFCE ; un adaptateur `official` pourra alors être ajouté. Le site public « Info chevaux » ne doit pas être aspiré sans autorisation. |
| Google Custom Search / Bing | Non intégrées | Bing Search API retirée par Microsoft (2025) ; Google Custom Search possible via un nouvel adaptateur si une clé est disponible. |

Chaque information importée est enregistrée dans `horse_external_sources`
(source, URL, date, valeur, fiabilité, statut de validation) ; rien n'est importé sans
choix explicite du cheval puis des champs.

## Email

`MAIL_MAILER=smtp` + `MAIL_HOST/PORT/USERNAME/PASSWORD/FROM_*` (tout fournisseur SMTP :
Brevo, Mailgun, Postmark, SMTP de l'hébergeur…). Tant que `MAIL_MAILER=log`, les emails
(vérification, réinitialisation, invitations, notifications) sont écrits dans `storage/logs`.
**La vérification d'email et les invitations nécessitent un service email configuré en production.**
Les envois passent par la file (`QUEUE_CONNECTION=database`), traitée par le planificateur.

## Synchronisation de calendrier externe

Non incluse dans cette version ; la colonne `calendar_events.external_uid` (unique) est prévue
pour un import/export iCal sans doublon.
