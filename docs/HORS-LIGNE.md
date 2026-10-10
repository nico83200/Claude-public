# Fonctionnement hors ligne

## Composants

| Élément | Fichier | Rôle |
|---|---|---|
| Manifeste | `public/manifest.webmanifest`, `public/icons/*` | Installation (écran d'accueil, plein écran, raccourcis) |
| Service Worker | `public/sw.js` | Cache des ressources compilées ; navigation réseau d'abord, repli sur la coquille « mode écurie » ; les pages privées ne sont **jamais** mises en cache |
| Coquille | `/hors-ligne` (`resources/views/offline/app.blade.php`) | Application autonome qui lit IndexedDB |
| Stockage | `resources/js/offline/db.js` | IndexedDB, **une base par utilisateur** (`equilibre-u{id}`) |
| Moteur | `resources/js/offline/engine.js` | File d'opérations, push/pull, expiration, purge |
| Serveur | `app/Services/Sync/SyncService.php`, `/sync/*` | Revalidation des droits, idempotence, fusion, conflits |

## Données disponibles hors ligne

Uniquement les chevaux **choisis** (« Disponible hors ligne » sur la fiche, 10 max. par défaut)
et encore autorisés : fiche essentielle, consignes et précautions, traitements en cours,
plan alimentaire, agenda (−7 / +30 jours), séances récentes et prévues, bibliothèque d'exercices.
Pas de documents, de dépenses ni de photos (données limitées au nécessaire).

## Actions hors ligne

Créer / préparer une séance, ajouter des exercices, cocher les exercices, répétitions,
durées, difficultés, notes, bilan, commentaires, suivi quotidien, observations,
notes du cheval (champs listés dans `Horse::OFFLINE_EDITABLE`).

Chaque action écrit **dans la même transaction IndexedDB** la modification locale et
l'opération en file : une fermeture brutale ne laisse pas d'état incohérent.

## Protocole

1. Retour du réseau (évènement `online`, minuterie, bouton) → `GET /sync/session` si le jeton CSRF a expiré.
2. `POST /sync/push` : opérations dans l'ordre (`seq`), chacune avec `op_uuid` unique.
   Le serveur, pour chacune : revérifie l'authentification, les droits **actuels** et la licence,
   valide, applique dans une transaction, enregistre le résultat dans `sync_operations`.
   Un `op_uuid` déjà vu renvoie le résultat précédent (`duplicate`) ; une création dont l'`uuid` existe déjà aussi.
3. Résultats : `applied`/`duplicate` → retirée de la file ; `rejected` → déplacée dans « saisies refusées »
   (contenu consultable, jamais supprimé silencieusement) ; `conflict` → conflit enregistré côté serveur ;
   `error` → conservée, nouvelle tentative.
4. `POST /sync/pull` : instantané complet des chevaux autorisés. Tout cheval ou séance qui n'y figure
   plus est **purgé** localement (révocation). Les séances ayant des opérations en attente gardent leur version locale.
5. Mise à jour de `last_sync` et `valid_until`. L'état « synchronisé » n'est affiché qu'après confirmation serveur.

## Conflits — stratégie par type

| Donnée | Stratégie |
|---|---|
| Séances créées hors ligne | Créations indépendantes fusionnées (uuid client) |
| Exercices réalisés | Fusion à trois voies par champ (valeur de base envoyée) ; doublons détectés par uuid |
| Commentaires, journaux, observations | Ajouts conservés (append-only) |
| Fiche cheval (champs autorisés) | Fusion à trois voies : champs non concurrents appliqués, champs concurrents → conflit |
| Bilan d'une séance déjà clôturée différemment | Conflit, décision explicite |
| Soins / traitements | Non modifiables hors ligne ; en ligne, verrou optimiste (`version`) avec refus explicite |
| Droits d'accès, abonnements | Toujours l'état serveur (opérations refusées) |

Les conflits sont listés dans le mode écurie (« Synchronisation ») avec les deux valeurs ;
l'utilisateur choisit « garder ma saisie » (droits revérifiés) ou « garder le serveur ».

## Sécurité

- Aucun secret dans IndexedDB : authentification par cookie de session HttpOnly + CSRF.
- Données locales expirées après `OFFLINE_DATA_TTL_HOURS` (168 h par défaut) sans synchronisation : purge automatique (la file d'attente est conservée).
- Purge complète à la déconnexion (avertissement s'il reste des saisies non synchronisées) ; purge des bases d'un autre compte au changement d'utilisateur.
- Purge à distance : Paramètres > Appareils (effective à la prochaine connexion de l'appareil).
- Code PIN local facultatif (PBKDF2) : verrouillage après 15 min d'inactivité.

**Limite assumée** : une copie déjà présente sur un appareil resté hors ligne ne peut pas
être effacée instantanément après une révocation ; elle l'est à sa reconnexion ou à expiration.
Les opérations envoyées après révocation sont refusées.

## Compatibilité

Testé automatiquement dans Chromium (Android/ordinateur) : `tests/e2e/offline.mjs` (installation
du Service Worker, coupure réseau, création de séance, rechargement, resynchronisation, purge).
À vérifier manuellement sur Safari iOS ≥ 16.4 (PWA ajoutée à l'écran d'accueil ; iOS peut purger
le stockage d'une PWA inutilisée plusieurs semaines) et Firefox Android.
En navigation privée, IndexedDB peut être indisponible : l'application l'indique.
