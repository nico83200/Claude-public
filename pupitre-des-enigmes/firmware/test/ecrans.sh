#!/bin/sh
# Compile le simulateur AVEC le dessin de l'écran (LovyanGFX pour Linux), rejoue les
# parties et range les captures d'écran au format PNG.
#   pio pkg install        (une fois : télécharge LovyanGFX dans .pio/libdeps)
#   sh test/ecrans.sh [dossier de sortie]      (par défaut : docs/ecrans)
set -e
ICI=$(cd "$(dirname "$0")" && pwd)
RACINE=$(cd "$ICI/.." && pwd)
SORTIE=${1:-$RACINE/docs/ecrans}
TMP=${TMPDIR:-/tmp}/pupitre-ecrans
LG=$RACINE/.pio/libdeps/pupitre/LovyanGFX/src
[ -d "$LG" ] || { echo "LovyanGFX absent : lancez d'abord « pio pkg install »."; exit 1; }
mkdir -p "$TMP/lgfx" "$SORTIE"

# LovyanGFX pour Linux (compilé une seule fois).
if [ ! -f "$TMP/lgfx/liblgfx.a" ]; then
  echo "Compilation de LovyanGFX pour Linux…"
  for f in "$LG"/lgfx/v1/*.cpp "$LG"/lgfx/v1/misc/*.cpp "$LG"/lgfx/v1/platforms/framebuffer/*.cpp; do
    g++ -std=c++17 -O1 -w -ffunction-sections -fdata-sections -I"$LG" -c "$f" -o "$TMP/lgfx/$(basename "$f").o"
  done
  for f in "$LG"/lgfx/utility/*.c "$LG"/lgfx/Fonts/efont/lgfx_efont_ja.c; do
    gcc -O0 -w -fdata-sections -I"$LG" -c "$f" -o "$TMP/lgfx/$(basename "$f").o"
  done
  ar rcs "$TMP/lgfx/liblgfx.a" "$TMP"/lgfx/*.o
fi

g++ -std=c++17 -O1 -DAVEC_ECRAN -I"$ICI/simulateur" -I"$RACINE/lib/moteur/src" -I"$RACINE/src" -I"$LG" \
    "$RACINE"/lib/moteur/src/*.cpp "$RACINE/src/dessin.cpp" "$RACINE/src/polices.cpp" "$ICI/simulateur/sim.cpp" \
    "$TMP/lgfx/liblgfx.a" -Wl,--gc-sections -lpthread -o "$TMP/sim-ecran"
python3 "$RACINE/../outils/preparer_sd.py" --test --sortie "$TMP/sd" > /dev/null
for partie in "$ICI"/parties/*.txt; do
  "$TMP/sim-ecran" "$TMP/sd" "$partie" --captures "$TMP" | grep "📷" || true
done
python3 - "$TMP" "$SORTIE" <<'PY'
import sys, glob, os
from PIL import Image
for f in sorted(glob.glob(os.path.join(sys.argv[1], "*.ppm"))):
    Image.open(f).save(os.path.join(sys.argv[2], os.path.basename(f)[:-4] + ".png"), optimize=True)
    os.remove(f)
PY
echo "Captures rangées dans $SORTIE"
