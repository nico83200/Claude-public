#!/usr/bin/env bash
# Génère les paquets d'installation / de mise à jour dans dist/ :
#   voyagestudio-X.Y.Z.zip              complet (avec vendor/ pour l'assistant IA) — 1re installation par FTP ou mise à jour
#   voyagestudio-X.Y.Z-sans-vendor.zip  léger — mise à jour quand les dépendances n'ont pas changé
# Usage : scripts/build.sh   (nécessite PHP, Composer et python3)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(tr -d '[:space:]' < "$ROOT/VERSION")"
BUILD="$(mktemp -d)"
APP="$BUILD/voyagestudio"
trap 'rm -rf "$BUILD"' EXIT

echo "→ VoyageStudio v$VERSION"
mkdir -p "$APP/data"
for f in index.php api.php install.php .htaccess VERSION README.md config.sample.php composer.json composer.lock; do
  cp "$ROOT/$f" "$APP/"
done
cp -R "$ROOT/app" "$ROOT/assets" "$APP/"
cp "$ROOT/data/.htaccess" "$APP/data/"

echo "→ Dépendances PHP (production)"
(cd "$APP" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet)
# Allège vendor/ : dépôts git, tests, exemples, documentation
find "$APP/vendor" -type d \( -name .git -o -name tests -o -name Tests -o -name examples -o -name fixtures -o -name docs -o -name .github \) -prune -exec rm -rf {} +
find "$APP/vendor" -type f \( -name '*.md' ! -iname 'LICENSE*' -o -name 'phpunit*' -o -name 'phpstan*' -o -name '.gitattributes' -o -name '.gitignore' \) -delete
rm -f "$APP/composer.json" "$APP/composer.lock"
printf '<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n' > "$APP/vendor/.htaccess"

mkdir -p "$ROOT/dist"
python3 - "$BUILD" "$ROOT/dist/voyagestudio-$VERSION.zip" "$ROOT/dist/voyagestudio-$VERSION-sans-vendor.zip" <<'PY'
import os, sys, zipfile
build, full, light = sys.argv[1:4]
for target, skip_vendor in ((full, False), (light, True)):
    with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for base, dirs, files in os.walk(build):
            dirs.sort()
            rel_base = os.path.relpath(base, build)
            if skip_vendor and rel_base.split(os.sep)[1:2] == ['vendor']:
                continue
            for f in sorted(files):
                p = os.path.join(base, f)
                z.write(p, os.path.relpath(p, build))
    print(f"   {os.path.basename(target)} : {os.path.getsize(target) / 1048576:.1f} Mo")
PY
echo "✔ Paquets dans dist/"
