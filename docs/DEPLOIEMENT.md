# Déploiement

## Option A — hébergement mutualisé avec le zip et l'installeur web (le plus simple)

1. Téléversez et décompressez `jackcie-x.y.z.zip` (dépendances PHP et ressources compilées déjà incluses,
   aucun Composer ni Node.js nécessaire sur le serveur).
2. Domaine → dossier `public/` (ou racine : le `.htaccess` fourni redirige et protège).
3. Ouvrez le domaine et suivez l'installeur (`/install`) — voir INSTALLATION.md.
4. Ajoutez la tâche cron affichée en fin d'installation. HTTPS obligatoire en production.

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
