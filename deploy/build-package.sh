#!/usr/bin/env bash
# Construit le paquet de déploiement : tapikard-<version>.zip (à lancer depuis la racine du dépôt).
set -euo pipefail
cd "$(dirname "$0")/.."
OUT="${1:-dist}"
VERSION="$(node -p "require('./package.json').version")-$(git rev-parse --short HEAD 2>/dev/null || echo local)"
PKG="$OUT/tapikard"

npm ci
rm -rf .next
NEXT_OUTPUT=standalone npx next build
rm -rf "$PKG" && mkdir -p "$PKG"
cp -r .next/standalone/. "$PKG/"
rm -rf "$PKG/data" "$PKG/.env"* "$PKG/AGENTS.md"     # jamais de base ni de secrets dans le paquet
mkdir -p "$PKG/.next" && cp -r .next/static "$PKG/.next/static"
[ -d public ] && cp -r public "$PKG/public"
cp .env.example "$PKG/tapikard.env.example"
cp deploy/INSTALL.md deploy/tapikard.service deploy/nginx-tapikard.conf "$PKG/"
mkdir -p "$PKG/outils" && cp tools/sun_verify.py "$PKG/outils/"
cp docs/encodage-ntag424.md "$PKG/outils/"
echo "$VERSION" > "$PKG/VERSION"
(cd "$OUT" && rm -f "tapikard-$VERSION.zip" && zip -qr "tapikard-$VERSION.zip" tapikard)
echo "Paquet : $OUT/tapikard-$VERSION.zip"
