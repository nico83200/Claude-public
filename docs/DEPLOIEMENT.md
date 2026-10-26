# Déploiement

## Option A — hébergement mutualisé avec le zip et l'installeur web (le plus simple)

1. Téléversez et décompressez `jackcie-x.y.z.zip` (dépendances PHP et ressources compilées déjà incluses,
   aucun Composer ni Node.js nécessaire sur le serveur).
2. Domaine → dossier `public/` (ou racine : le `.htaccess` fourni redirige et protège).
3. Ouvrez le domaine et suivez l'installeur (`/install`) — voir INSTALLATION.md.
4. Ajoutez la tâche cron affichée en fin d'installation (toutes les minutes) : `cd <dossier> && php artisan schedule:run`, ou, si l'hébergeur demande le chemin d'un fichier PHP (OVH…), `<dossier>/cron.php`. Le cron doit utiliser PHP 8.3 (certains hébergeurs, dont Hostinger, utilisent une version plus ancienne par défaut pour les tâches cron : `cron.php` se relance alors avec PHP 8.3 s'il le trouve, sinon utiliser la commande affichée, ex. `/opt/alt/php83/usr/bin/php <dossier>/cron.php`). Vérification : Administration › Notifications. HTTPS obligatoire en production.

## Option A bis — hébergement mutualisé, installation manuelle

1. Compiler localement : `composer install --no-dev --optimize-autoloader && npm ci && npm run build`.
2. Envoyer le projet complet (avec `vendor/` et `public/build/`) hors de la racine web.
3. Faire pointer le domaine (ou un lien symbolique) vers le dossier **`public/`** uniquement.
   Si impossible : placer le contenu de `public/` à la racine web et adapter les chemins de `index.php`.
4. Créer `.env` à partir de `.env.example` (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`).
5. En SSH ou via l'outil de commandes de l'hébergeur :
   ```bash
   php artisan migrate --force --seed
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   php artisan app:create-super-admin …
   ```
6. **Cron** (obligatoire — rappels, licences, file de messages, purge) :
   ```
   * * * * * cd /chemin/jackcie && php artisan schedule:run >> /dev/null 2>&1
   ```
   La file (emails) est traitée chaque minute par `queue:work --stop-when-empty`.
7. HTTPS obligatoire (Service Worker, cookies sécurisés `SESSION_SECURE_COOKIE=true`).
8. Droits d'écriture du serveur web sur `storage/` et `bootstrap/cache/`.

## Option B — VPS

Identique, avec Nginx/Apache + PHP-FPM, et éventuellement un worker permanent
(`php artisan queue:work --tries=3` sous systemd/supervisor) à la place de l'entrée planifiée.

## Stripe

1. Tableau de bord Stripe → Développeurs → clés API : `STRIPE_KEY`, `STRIPE_SECRET`.
2. Webhooks → ajouter l'URL `https://votre-domaine/stripe/webhook`, événements :
   `checkout.session.completed`, `customer.subscription.created`, `customer.subscription.updated`,
   `customer.subscription.deleted`, `customer.subscription.paused`, `customer.subscription.resumed`,
   `invoice.paid`, `invoice.payment_succeeded`, `invoice.payment_failed`, `invoice.voided`,
   `invoice.marked_uncollectible`, `charge.refunded` → copier le secret de signature dans `STRIPE_WEBHOOK_SECRET`.
3. Paramètres → Portail client : activer (moyens de paiement, factures, résiliation).
4. Administration de l'application → Offres → cocher « Créer automatiquement le produit et les prix
   dans Stripe », ou coller des identifiants `price_…` existants.
5. Tester en mode test (`sk_test_…`) avec `stripe listen --forward-to https://…/stripe/webhook`.

## Mise à jour

```bash
php artisan down
git pull && composer install --no-dev -o   # + nouveaux assets compilés
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Après une évolution du Service Worker, incrémenter `VERSION` dans `public/sw.js`.

## Variables d'environnement

Voir `.env.example` (commentée). Aucun secret ne doit être committé ni exposé côté navigateur ;
`STRIPE_KEY` (publique) n'est pas utilisée côté client (redirection Checkout côté serveur).


## Mettre à jour depuis l'administration (sans SSH)

**Administration › Mises à jour** :

1. Choisir le fichier `jackcie-x.y.z.zip` : il est envoyé par morceaux (les limites d'envoi de l'hébergeur ne gênent pas), puis vérifié : structure du paquet, numéro de version (une version plus ancienne est refusée), absence de chemins dangereux. Comparer l'empreinte SHA-256 affichée avec le fichier `jackcie-x.y.z.zip.sha256` fourni.
2. **Installer** : le site passe en maintenance, le code actuel et la base sont sauvegardés (`storage/app/updates/`, 3 dernières sauvegardes conservées et téléchargeables), les fichiers sont remplacés — **jamais `.env` ni `storage/`** —, puis une seconde requête (nouveau code chargé) met à jour la base et remet le site en ligne.
3. En cas d'échec, le code précédent est remis automatiquement. Bouton « Restaurer » pour revenir à la version précédente ; la base se restaure avec la sauvegarde SQL (voir SAUVEGARDE.md).

Prérequis : extension PHP `zip`, dossiers de l'application modifiables par le serveur web, espace disque ≈ 4 × la taille du paquet, **cron du planificateur actif** (il termine automatiquement une mise à jour interrompue, même pendant la maintenance : `php artisan app:finalize-update`).
Si le site restait bloqué en maintenance (« Service indisponible ») :
- depuis la 2.12.12, la page de maintenance termine elle-même l'installation au bout de 2 minutes, puis se recharge ;
- avec le cron (ou `cron.php`) en place, l'installation est terminée dans les 3 minutes ;
- sans accès SSH : gestionnaire de fichiers → `storage/framework/down` → copier la valeur de `"secret"` → ouvrir `https://<domaine>/<secret>` (accès administrateur pendant la maintenance) → Administration › Mises à jour → la mise à jour → « Terminer l'installation » ;
- en SSH : `php artisan app:finalize-update --now`, ou `php artisan up`.

## Reprise des observations dans le suivi du jour (2.52.1, complétée en 2.52.2)

La mise à jour 2.52.1 recopie une fois, automatiquement (migration `2026_01_01_006600`), chaque observation déjà signalée
dans le suivi du jour où elle l'a été ; depuis la 2.52.2 (migration `2026_01_01_006700`), ses nouvelles et sa clôture aussi, chacune à son propre jour : suivi créé s'il manque, complété sinon, sans doublon. Pour la relancer à la main
ou voir d'abord ce qu'elle ferait :

```bash
php artisan attention:backfill-daily-logs --dry-run   # simulation, rien n'est enregistré
php artisan attention:backfill-daily-logs             # reprise (relancée, elle ne recopie rien deux fois)
```

