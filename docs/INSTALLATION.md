# Installation (environnement vierge)

## Prérequis

- PHP 8.3+ avec extensions : `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`, `intl`, `zip`, `exif` (orientation des photos, facultative)
- MySQL 8.0+ ou MariaDB 10.6+ (InnoDB, utf8mb4)
- Composer 2
- Node.js 20+ **uniquement sur le poste qui compile les assets** (pas sur le serveur)

## Étapes

```bash
git clone <dépôt> equilibre && cd equilibre
composer install
npm ci && npm run build          # génère public/build
cp .env.example .env
php artisan key:generate
```

Créer la base :

```sql
CREATE DATABASE equilibre CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'equilibre'@'localhost' IDENTIFIED BY 'motdepasse-solide';
GRANT ALL ON equilibre.* TO 'equilibre'@'localhost';
```

Renseigner `DB_*` dans `.env`, puis :

```bash
php artisan migrate --seed       # tables + données de référence (rôles, catégories, races, exercices, offres)
php artisan app:create-super-admin admin@votre-domaine.fr --name="Administrateur"
php artisan serve                # http://localhost:8000
```

## Premier super-administrateur

La création n'est possible **qu'en ligne de commande** (aucun formulaire public) :

```bash
php artisan app:create-super-admin email@exemple.fr --name="Nom"      # nouveau compte (mot de passe saisi de façon masquée, 12 car. min., complexe)
php artisan app:create-super-admin email@exemple.fr --promote         # promouvoir un compte existant
```

Après connexion, activer la double authentification (Paramètres > Sécurité) :
elle est **obligatoire** pour accéder à `/admin` hors environnement local/test.
L'accès à `/admin` demande en plus une reconfirmation du mot de passe.

## Données de démonstration

```bash
php artisan db:seed --class=DemoSeeder
```

Comptes `@demo.equilibre.test`, organisations marquées `is_demo`. Le seeder refuse
de s'exécuter si `APP_ENV=production`.

## Tests

`phpunit.xml` cible la base MySQL `equine_test` (utilisateur `equine` / `secret`) ;
adapter ces valeurs à votre poste.

```bash
php artisan test
npm test
# test navigateur hors ligne (Chromium/Playwright) :
php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
php artisan serve --port=8000 &
node tests/e2e/offline.mjs     # CHROMIUM_PATH=/chemin/vers/chrome si besoin
```
