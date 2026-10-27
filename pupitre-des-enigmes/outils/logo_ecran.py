#!/usr/bin/env python3
"""Prépare le logo affiché au démarrage du pupitre : firmware/src/logo.h.

    python3 outils/logo_ecran.py [console/logo.jpg]

L'image est recadrée au format de l'écran (3:2), autour de son centre, réduite à
480 × 320 pixels et enregistrée en JPEG dans le programme : elle s'affiche en plein écran
avant même que la carte SD soit lue."""
import io
import sys
from pathlib import Path

from PIL import Image

JEU = Path(__file__).resolve().parents[1]
LARGEUR, HAUTEUR = 480, 320


def main():
    source = Path(sys.argv[1]) if len(sys.argv) > 1 else JEU / "console" / "logo.jpg"
    image = Image.open(source).convert("RGB")

    # Le cadrage : la plus grande zone 3:2 centrée, puis la taille de l'écran.
    l, h = image.size
    if l * HAUTEUR > h * LARGEUR:
        garde = h * LARGEUR // HAUTEUR
        image = image.crop(((l - garde) // 2, 0, (l + garde) // 2, h))
    else:
        garde = l * HAUTEUR // LARGEUR
        image = image.crop((0, (h - garde) // 2, l, (h + garde) // 2))
    image = image.resize((LARGEUR, HAUTEUR), Image.LANCZOS)

    tampon = io.BytesIO()
    image.save(tampon, "JPEG", quality=85, optimize=True)  # LovyanGFX : JPEG de base, pas progressif
    octets = tampon.getvalue()

    lignes = [", ".join(f"0x{b:02x}" for b in octets[i:i + 16]) for i in range(0, len(octets), 16)]
    cible = JEU / "firmware" / "src" / "logo.h"
    cible.write_text(
        "// Le logo du démarrage, produit par outils/logo_ecran.py (ne pas modifier à la main).\n"
        "#pragma once\n\n"
        "#include <cstdint>\n\n"
        "namespace logo {\n\n"
        f"constexpr int LARGEUR = {LARGEUR}, HAUTEUR = {HAUTEUR};\n\n"
        "const uint8_t JPEG[] = {\n  " + ",\n  ".join(lignes) + "\n};\n\n"
        "}  // namespace logo\n")
    print(f"✓ {cible.relative_to(JEU)} : {len(octets)} octets")


if __name__ == "__main__":
    main()
