# Installation (environnement vierge)

## Méthode recommandée : installeur web

1. Envoyez le contenu du zip `jackcie-x.y.z.zip` sur l'hébergement (FTP ou gestionnaire de fichiers).
2. Faites pointer le domaine vers le dossier `public/`. Si ce n'est pas possible, laissez le domaine
   sur la racine : le fichier `.htaccess` fourni redirige vers `public/` et bloque l'accès aux fichiers sensibles.
3. Créez une base MySQL 8+ / MariaDB 10.6+ et son utilisateur depuis l'interface de l'hébergeur
   (ou laissez l'installeur la créer si l'utilisateur en a le droit).
4. Ouvrez votre domaine : toutes les pages redirigent vers **`/install`**, qui enchaîne :
   - **Prérequis** : version de PHP, extensions, droits d'écriture (`storage/`, `bootstrap/cache/`, `.env`) ;
   - **Base de données** : test de connexion avec message d'erreur explicite, création facultative de la base,
     création des tables et des données de référence (une base existante n'est jamais vidée) ;
   - **Application** : nom, adresse, environnement, et facultativement SMTP et clés Stripe ;
   - **Super‑administrateur** : nom, email, mot de passe robuste (12 caractères, majuscules, chiffres, symboles) ;
   - **Terminé** : ligne cron à copier, adresse du webhook Stripe, rappel d'activer la double authentification.
5. L'installeur se ferme définitivement (fichier `storage/app/installed.json`) : `/install` renvoie ensuite une 404.

Le fichier `.env` et la clé de chiffrement sont créés automatiquement. Si le serveur ne peut pas écrire `.env`,
l'installation aboutit quand même et la dernière page affiche les lignes à copier dans `.env`.

**Sécurité** : faites l'installation juste après l'envoi des fichiers. Pour empêcher quiconque de lancer
l'installeur avant vous, créez un `.env` contenant `INSTALL_KEY=une-cle-longue` : elle sera demandée à la première étape.

Pour réinstaller (environnement de test uniquement) : supprimer `storage/app/installed.json`.

## Méthode en ligne de commande (développeurs)

## Prérequis

- PHP 8.3+ avec extensions : `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`, `intl`, `zip`, `exif` (orientation des photos, facultative)
- MySQL 8.0+ ou MariaDB 10.6+ (InnoDB, utf8mb4)
- Composer 2
- Node.js 20+ **uniquement sur le poste qui compile les assets** (pas sur le serveur)

## Étapes

```bash
git clone <dépôt> jackcie && cd jackcie
composer install
npm ci && npm run build          # génère public/build
cp .env.example .env
php artisan key:generate
```

Créer la base :

```sql
CREATE DATABASE jackcie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'jackcie'@'localhost' IDENTIFIED BY 'motdepasse-solide';
GRANT ALL ON jackcie.* TO 'jackcie'@'localhost';
```

Renseigner `DB_*` dans `.env`, puis :

```bash
php artisan migrate --seed       # tables + données de référence (rôles, catégories, races, exercices, offres)
php artisan app:create-super-admin admin@votre-domaine.fr --name="Administrateur"   # ferme l'installeur web
php artisan serve                # http://localhost:8000
```

## Premier super-administrateur

Hors installeur web, la création se fait **en ligne de commande** (aucun formulaire public ensuite) ;
la commande ferme aussi l'installeur web :

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

Comptes `@demo.jackcie.test`, organisations marquées `is_demo`. Le seeder refuse
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
