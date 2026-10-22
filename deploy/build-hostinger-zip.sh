#!/usr/bin/env bash
# Zip du code source pour Hostinger (Web App Node.js) : Hostinger lance npm install + npm run build.
set -euo pipefail
cd "$(dirname "$0")/.."
OUT="${1:-dist}"
VERSION="$(node -p "require('./package.json').version")-$(git rev-parse --short HEAD 2>/dev/null || echo local)"
NAME="tapikard-hostinger-$VERSION.zip"
mkdir -p "$OUT"
rm -f "$OUT/$NAME"
cp deploy/HOSTINGER.md ./LISEZ-MOI-HOSTINGER.md
zip -qr "$OUT/$NAME" \
  package.json package-lock.json next.config.ts tsconfig.json .env.example README.md LISEZ-MOI-HOSTINGER.md \
  app components lib tests \
  -x "*.DS_Store"
rm ./LISEZ-MOI-HOSTINGER.md
echo "$OUT/$NAME"
