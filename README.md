# Equilibre — application SaaS de gestion équine

Application web (PWA installable, utilisable hors ligne) pour gérer un cheval
comme une écurie complète : identité et généalogie, santé et traitements,
intervenants, calendrier, séances et bibliothèque d'exercices, alimentation,
suivi quotidien, demi-pensions et partage, écuries multi-membres, budget,
abonnements Stripe et super-administration.

> Ce n'est pas un outil de diagnostic vétérinaire : les traitements enregistrés
> sont ceux prescrits par des professionnels.

## Démarrage rapide (développement)

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate   # puis renseigner DB_*
php artisan migrate --seed                         # données de référence + offres
php artisan db:seed --class=DemoSeeder             # (facultatif) démonstration isolée
php artisan app:create-super-admin vous@exemple.fr # premier super-administrateur
php artisan serve
```

Comptes de démonstration (mot de passe `Demo2026!demo`) :
`proprietaire@demo.equilibre.test`, `cavaliere@demo.equilibre.test`, `gerant@demo.equilibre.test`.

## Tests

```bash
php artisan test            # 94 tests PHP (MySQL, base equine_test — voir phpunit.xml)
npm test                    # 13 tests du moteur hors ligne (IndexedDB simulé)
node tests/e2e/offline.mjs  # test de bout en bout dans Chromium (serveur + DemoSeeder requis)
```

## Documentation

| Document | Contenu |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Audit, architecture, modules, schéma de données, diagramme, risques |
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Installation sur un environnement vierge |
| [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md) | Mise en production (mutualisé ou VPS), cron, Stripe |
| [docs/SAUVEGARDE.md](docs/SAUVEGARDE.md) | Sauvegarde et restauration |
| [docs/INTEGRATIONS.md](docs/INTEGRATIONS.md) | Stripe, recherche d'identité, email : identifiants requis |
| [docs/HORS-LIGNE.md](docs/HORS-LIGNE.md) | PWA, IndexedDB, protocole de synchronisation, conflits, limites |
| [docs/SECURITE-RGPD.md](docs/SECURITE-RGPD.md) | Mesures de sécurité et conformité RGPD |
| [docs/ETAT-FONCTIONNALITES.md](docs/ETAT-FONCTIONNALITES.md) | Fonctionnalités terminées / à configurer / limites connues |

## Stack

PHP 8.3 · Laravel 13 · MySQL 8 / MariaDB 10.6+ · Blade + Alpine.js · Tailwind CSS 4 ·
Laravel Fortify (auth, 2FA) · Stripe Checkout/Billing · DomPDF · Service Worker + IndexedDB.
