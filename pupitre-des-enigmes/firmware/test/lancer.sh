#!/bin/sh
# Compile le simulateur, prépare une carte SD de test et rejoue toutes les parties.
#   sh test/lancer.sh            (depuis le dossier firmware)
#   sh test/lancer.sh --bavard   (affiche l'écran et les répliques)
set -e
ICI=$(cd "$(dirname "$0")" && pwd)
TMP=${TMPDIR:-/tmp}/pupitre-sim
mkdir -p "$TMP"
g++ -std=c++17 -O1 -Wall -Wextra -I"$ICI/simulateur" -I"$ICI/../lib/moteur/src" \
    "$ICI"/../lib/moteur/src/*.cpp "$ICI/simulateur/sim.cpp" -o "$TMP/sim"
python3 "$ICI/../../outils/verifier_scripts.py"
python3 "$ICI/../../outils/preparer_sd.py" --test --sortie "$TMP/sd" > /dev/null
echecs=0
for partie in "$ICI"/parties/*.txt; do
  printf '%-28s ' "$(basename "$partie")"
  if "$TMP/sim" "$TMP/sd" "$partie" "$@" > "$TMP/sortie.txt"; then
    tail -1 "$TMP/sortie.txt"
  else
    echo "ÉCHEC"; grep -E "ÉCHEC|⚠" "$TMP/sortie.txt" || true
    echecs=$((echecs + 1))
  fi
done
[ "$echecs" -eq 0 ] && echo "Toutes les parties sont passées." || { echo "$echecs partie(s) en échec."; exit 1; }
