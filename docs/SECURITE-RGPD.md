# Sécurité et RGPD

## Mesures techniques

| Exigence | Mise en œuvre |
|---|---|
| Authentification | Fortify : mots de passe hachés (bcrypt), 10 car. min. lettres + chiffres, vérification d'email, réinitialisation par jeton, 2FA TOTP + codes de secours |
| Tentatives de connexion | 5/min par email+IP ; 2FA 5/min |
| Comptes suspendus | Connexion refusée, sessions supprimées, déconnexion immédiate (middleware) |
| CSRF | Jeton sur tous les formulaires et appels `/sync` ; seul `/stripe/webhook` est exclu (signature Stripe) |
| Injections SQL | Eloquent / requêtes paramétrées ; recherche `LIKE` échappée |
| Validation | Côté serveur pour chaque formulaire et chaque opération synchronisée |
| Contrôle d'accès | `HorseAccess` + Gates à chaque lecture, écriture, export, téléchargement et synchronisation ; routes imbriquées scopées (`/chevaux/{a}/soins/{b}` refuse un soin d'un autre cheval) |
| Assignation de masse | Champs sensibles (`is_super_admin`, `organization_id`, `version`…) protégés |
| Fichiers | Disque privé `storage/app/private`, servis par contrôleur après autorisation ; types et tailles limités ; noms aléatoires |
| Sessions | En base, chiffrées, cookie `Secure`/`HttpOnly`/`SameSite=Lax` en production |
| HTTPS | Forcé en production (`URL::forceScheme`) ; `TRUSTED_PROXIES` derrière un proxy |
| Erreurs | Pages génériques sans détail technique (`APP_DEBUG=false`) ; erreurs consultables par l'admin |
| Journalisation | `audit_logs` : partages, révocations, soins, exports, téléchargements de documents, administration, facturation |
| Administration | Rôle non attribuable par formulaire, 2FA obligatoire, reconfirmation du mot de passe, aucune lecture implicite des données métier |
| Notifications | Destinataires filtrés par droits ; liens de notification internes uniquement |

## RGPD

| Droit / obligation | Fonctionnalité |
|---|---|
| Information | Pages Confidentialité / Conditions (modèles à valider juridiquement ; identité, DPO, sous-traitants paramétrables dans Admin > Paramètres) |
| Consentement | Acceptation des CGU/politique horodatée à l'inscription (`terms_accepted_at`). Aucun cookie publicitaire ni traceur tiers → pas de bandeau requis |
| Accès / portabilité | Paramètres > Mes données > export JSON (mot de passe reconfirmé) ; exports PDF/CSV par cheval |
| Rectification | Modification directe du profil et des fiches ; demande formelle via le formulaire |
| Effacement | Demande → Admin > RGPD → anonymisation (compte, profil, accès, espace personnel), purge définitive à J+30 (`data:purge`) |
| Conservation | Suppressions logiques purgées après 30 j ; journaux d'audit 3 ans ; opérations de synchro 180 j ; notifications lues 1 an ; données d'un abonnement terminé conservées en lecture seule ≥ 90 j (`BILLING_RECOVERY_DAYS`) |
| Sous-traitants | Hébergeur, Stripe, fournisseur email, (Brave Search si activé) — à lister dans les paramètres |
| Procédure | Demandes visibles dans Admin > RGPD, à traiter sous un mois ; chaque action est journalisée |
| Données hors ligne | Minimisées, expirantes, purgées à la déconnexion (voir HORS-LIGNE.md) |

## À faire avant la mise en production

- Faire valider CGU/CGV et politique de confidentialité, renseigner raison sociale / DPO / sous-traitants.
- Tenir le registre des traitements.
- Mettre en place les sauvegardes chiffrées hors site et un test de restauration.
- Renseigner un `HORSE_SEARCH_USER_AGENT` avec un contact réel.
