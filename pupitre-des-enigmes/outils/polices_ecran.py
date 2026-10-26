#!/usr/bin/env python3
"""Fabrique les polices lissées de l'écran du pupitre (format VLW de LovyanGFX).

    python3 outils/polices_ecran.py

Rend la police Inter (https://rsms.me/inter, licence SIL OFL) à plusieurs tailles,
en niveaux de gris (antialiasing), et écrit firmware/src/polices.cpp : des
tableaux en mémoire flash, chargés au démarrage par polices::charger().

Il faut Pillow avec raqm (pour les chiffres à largeur fixe), fontTools
(pip install fonttools) et les polices Inter installées (paquet « fonts-inter »
sous Debian/Ubuntu). Le fichier produit est
versionné : on ne relance cet outil que pour changer de police ou de taille.
"""
import glob
import json
import re
import sys
from pathlib import Path

from fontTools.ttLib import TTFont
from PIL import Image, ImageDraw, ImageFont, features

RACINE = Path(__file__).resolve().parent.parent
SORTIE = RACINE / "firmware" / "src" / "polices.cpp"
INTER = Path("/usr/share/fonts/opentype/inter")

# Le jeu de caractères de base : ASCII, Latin-1, et la ponctuation française.
BASE = [chr(c) for c in range(0x20, 0x7F)] + [chr(c) for c in range(0xA0, 0x100)]
BASE += list("ŒœŸ‘’‚“”„…–—•€→←↑↓✓✗●○▶◀★◆■□√⚠₂‰‹›")
CODES = list(" 0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_:·+-/%'°.?")

# nom, fichier, taille en pixels, jeu de caractères
POLICES = [
    ("texte", "Inter-Regular.otf", 18, "base"),
    ("texteGras", "Inter-SemiBold.otf", 18, "base"),
    ("compact", "Inter-Regular.otf", 15, "base"),
    ("petit", "Inter-SemiBold.otf", 13, "base"),
    ("titre", "InterDisplay-Bold.otf", 26, "base"),
    ("grand", "InterDisplay-Bold.otf", 36, "base"),
    ("chiffres", "InterDisplay-SemiBold.otf", 54, "codes"),
]


def caracteres_du_jeu():
    """Tous les caractères qui apparaissent dans les répliques et les scripts."""
    texte = ""
    for motif in ("mondes/*/audio/mission-*.json", "console/audio/repliques.json", "mondes/*/pupitre/*.json"):
        for f in glob.glob(str(RACINE / motif)):
            texte += json.dumps(json.load(open(f, encoding="utf-8")), ensure_ascii=False)
    for f in glob.glob(str(RACINE / "firmware" / "lib" / "moteur" / "src" / "*.cpp")):
        texte += "".join(re.findall(r'"((?:[^"\\]|\\.)*)"', Path(f).read_text(encoding="utf-8")))
    return set(texte)


def vlw(fichier, taille, jeu):
    police = ImageFont.truetype(str(INTER / fichier), taille, layout_engine=ImageFont.Layout.RAQM)
    entetes, bitmaps = [], []
    for ch in jeu:
        # Chiffres à largeur fixe (tnum) : le chrono ne tremble pas.
        opt = {"features": ["tnum"]} if ch.isdigit() else {}
        g, h, d, b = police.getbbox(ch, anchor="ls", **opt)
        largeur, hauteur = max(0, d - g), max(0, b - h)
        if largeur <= 0 or hauteur <= 0:
            largeur = hauteur = 0
            data = b""
        else:
            image = Image.new("L", (largeur, hauteur), 0)
            ImageDraw.Draw(image).text((-g, -h), ch, font=police, fill=255, anchor="ls", **opt)
            data = image.tobytes()
        avance = round(police.getlength(ch, **opt))
        entetes.append((ord(ch), hauteur, largeur, avance, -h, g, 0))
        bitmaps.append(data)
    haut_d = -police.getbbox("d", anchor="ls")[1]
    bas_p = police.getbbox("p", anchor="ls")[3]
    sortie = bytearray()

    def mot(v):
        sortie.extend((v & 0xFFFFFFFF).to_bytes(4, "big"))

    for v in (len(jeu), 11, taille, 0, haut_d, bas_p):
        mot(v)
    for e in entetes:
        for v in e:
            mot(v)
    for data in bitmaps:
        sortie.extend(data)
    return bytes(sortie)


def main():
    if not features.check("raqm"):
        sys.exit("Pillow doit être compilé avec raqm (chiffres à largeur fixe).")
    jeu_base = sorted(set(BASE) | {c for c in caracteres_du_jeu() if c.isprintable()})
    morceaux = ['// Fichier produit par outils/polices_ecran.py : ne pas modifier à la main.',
                '// Police Inter (Rasmus Andersson), licence SIL Open Font License 1.1.',
                '#include "polices.h"', "", "#ifndef PROGMEM", "#define PROGMEM", "#endif", "",
                "namespace {"]
    noms = []
    for nom, fichier, taille, jeu in POLICES:
        cmap = TTFont(str(INTER / fichier)).getBestCmap()
        # Triés par code : LovyanGFX cherche les glyphes par dichotomie.
        caracteres = sorted({c for c in (jeu_base if jeu == "base" else CODES) if ord(c) in cmap})
        # Les caractères absents de la police (emoji…) sont écartés ; dessin.cpp les remplace.
        manquants = [c for c in (jeu_base if jeu == "base" else CODES) if c not in caracteres]
        donnees = vlw(fichier, taille, caracteres)
        print(f"{nom:10} {fichier:28} {taille:3} px  {len(caracteres):4} glyphes  {len(donnees) / 1024:6.1f} Ko"
              + (f"  (absents : {''.join(manquants)})" if manquants else ""))
        lignes = [", ".join(f"0x{o:02x}" for o in donnees[i:i + 24]) for i in range(0, len(donnees), 24)]
        morceaux.append(f"const uint8_t {nom.upper()}[] PROGMEM = {{\n" + ",\n".join(lignes) + "\n};")
        noms.append(nom)
    morceaux.append("")
    morceaux += [f"lgfx::PointerWrapper source_{n};" for n in noms]
    morceaux += ["}  // namespace", "", "namespace polices {", ""]
    morceaux += [f"lgfx::VLWfont {n};" for n in noms]
    morceaux += ["", "void charger() {", "  static bool fait = false;", "  if (fait) return;", "  fait = true;"]
    for n in noms:
        morceaux += [f"  source_{n}.set({n.upper()}, sizeof({n.upper()}));", f"  {n}.loadFont(&source_{n});"]
    morceaux += ["}", "", "}  // namespace polices", ""]
    SORTIE.write_text("\n".join(morceaux), encoding="utf-8")
    print(f"→ {SORTIE.relative_to(RACINE)} ({SORTIE.stat().st_size / 1024 / 1024:.1f} Mo de source)")


if __name__ == "__main__":
    main()
