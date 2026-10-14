#!/usr/bin/env bash
# Construit le zip de distribution de Jack&Cie, prêt à téléverser sur un hébergement
# PHP/MySQL : dépendances de production et ressources compilées incluses, sans .env.
# Usage : scripts/build-release.sh [version]   → dist/jackcie-<version>.zip
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT=$(pwd)
VERSION=${1:-$(date +%Y.%m.%d)}
STAGE=$(mktemp -d)/jackcie
OUT="$ROOT/dist/jackcie-$VERSION.zip"
export COMPOSER_ALLOW_SUPERUSER=1

echo "→ Compilation des ressources"
npm run build >/dev/null

echo "→ Copie des fichiers suivis par Git (état de travail courant)"
mkdir -p "$STAGE"
git ls-files -co --exclude-standard | grep -vE '^(site/|tests/|\.github/|scripts/|dist/|phpunit\.xml|vite\.config\.js|package(-lock)?\.json|\.editorconfig|\.gitattributes|\.npmrc)' \
  | while read -r f; do [ -e "$f" ] && mkdir -p "$STAGE/$(dirname "$f")" && cp -p "$f" "$STAGE/$f"; done
cp -R public/build "$STAGE/public/build"

echo "$VERSION" > "$STAGE/VERSION"

echo "→ Dépendances PHP de production"
(cd "$STAGE" && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet)

echo "→ Nettoyage"
# Dépôts Git embarqués (paquets installés depuis les sources) et fichiers inutiles en production
find "$STAGE/vendor" -type d \( -name .git -o -name .github \) -prune -exec rm -rf {} +
find "$STAGE/vendor" -mindepth 3 -maxdepth 3 -type d \( -iname tests -o -iname test -o -iname docs -o -iname doc -o -iname examples \) -prune -exec rm -rf {} +
# Outils de développement et fichiers de démonstration inutiles en production
# Polices PDF monospaces non utilisées par les exports (le générique « monospace » de dompdf reste Courier)
# Polices serif : seuls le romain et le gras servent (titres des exports) ; l'italique se rabat sur le romain.
rm -f "$STAGE"/vendor/dompdf/dompdf/lib/fonts/DejaVuSansMono* "$STAGE"/vendor/dompdf/dompdf/lib/fonts/DejaVuSerif-*Italic* "$STAGE/vendor/monolog/monolog/logo.png" "$STAGE/vendor/league/commonmark/commonmark-banner.png"
php -r '$f=$argv[1]; $j=json_decode(file_get_contents($f), true); unset($j["dejavu sans mono"]); $j["dejavu serif"]["italic"] = "DejaVuSerif"; $j["dejavu serif"]["bold_italic"] = "DejaVuSerif-Bold"; file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));' "$STAGE/vendor/dompdf/dompdf/lib/fonts/installed-fonts.dist.json"
rm -rf "$STAGE/vendor/laravel/framework/bin" "$STAGE/vendor/nunomaduro/termwind/art" "$STAGE/vendor/anthropic-ai/sdk/scripts" "$STAGE/vendor/anthropic-ai/sdk/fixtures"
find "$STAGE/vendor" -type f \( -iname '*.md' -o -name 'phpunit.xml*' -o -name '.php-cs-fixer*' -o -name 'psalm.xml' -o -name 'phpstan.neon*' \) ! -iname 'LICENSE*' -delete
rm -f "$STAGE/.env" "$STAGE/storage/app/installed.json" "$STAGE/public/hot"
find "$STAGE/storage" -type f ! -name '.gitignore' -delete
rm -rf "$STAGE/bootstrap/cache/"*.php
mkdir -p "$STAGE/storage/framework/"{cache/data,sessions,views} "$STAGE/storage/logs" "$STAGE/storage/app/private" "$STAGE/storage/app/public"

echo "→ Protection pour un domaine pointant sur la racine"
cat > "$STAGE/.htaccess" <<'HT'
# Jack&Cie : le site doit être servi depuis public/. Si votre hébergeur ne permet pas
# de pointer le domaine sur public/, ce fichier y redirige et bloque les fichiers sensibles.
Options -Indexes
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(\.env.*|\.git.*|composer\.(json|lock)|artisan|app|bootstrap|config|database|lang|resources|routes|storage|vendor|docs)(/|$) - [F,L]
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
HT

cat > "$STAGE/LISEZ-MOI.txt" <<TXT
Jack&Cie $VERSION — Toute la vie de votre cheval, réunie.

INSTALLATION
1. Téléversez et décompressez ce dossier sur votre hébergement (PHP 8.3+, MySQL 8+ ou MariaDB 10.6+).
2. Faites pointer votre domaine vers le dossier « public ».
   (Si c'est impossible, laissez-le sur ce dossier : le fichier .htaccess redirige et protège.)
3. Créez une base de données MySQL et son utilisateur chez votre hébergeur.
4. Ouvrez votre domaine dans le navigateur : l'installeur se lance automatiquement
   (prérequis, base de données, application, compte super-administrateur).
5. Ajoutez la tâche cron indiquée à la fin de l'installation.

Sécurité : lancez l'installation juste après l'envoi des fichiers, ou créez d'abord
un fichier .env contenant INSTALL_KEY=une-cle-longue (elle sera demandée).

Documentation complète : dossier docs/ (INSTALLATION.md, DEPLOIEMENT.md, SAUVEGARDE.md…).
TXT

echo "→ Archive"
mkdir -p "$ROOT/dist"
rm -f "$OUT"
(cd "$(dirname "$STAGE")" && zip -9 -qr -X "$OUT" jackcie)
rm -rf "$(dirname "$STAGE")"
(cd "$ROOT/dist" && sha256sum "$(basename "$OUT")" > "$(basename "$OUT").sha256")
echo "✓ $OUT ($(du -h "$OUT" | cut -f1)) — SHA-256 : $(cut -d' ' -f1 "$OUT.sha256")"
