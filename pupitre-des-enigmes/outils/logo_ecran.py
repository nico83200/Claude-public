#!/usr/bin/env python3
"""Prépare le logo affiché au démarrage du pupitre : firmware/src/logo.h.

    python3 outils/logo_ecran.py [console/logo.png]

Le logo est recadré sur son cercle, réduit à 300 × 300 pixels (l'écran fait 480 × 320) et
enregistré en JPEG dans le programme : il s'affiche avant même que la carte SD soit lue.
La couleur du coin de l'image sert de fond au reste de l'écran."""
import io
import sys
from pathlib import Path

import numpy as np
from PIL import Image

JEU = Path(__file__).resolve().parents[1]
COTE = 300


def main():
    source = Path(sys.argv[1]) if len(sys.argv) > 1 else JEU / "console" / "logo.png"
    image = Image.open(source).convert("RGB")
    fond = image.getpixel((4, 4))

    # Le cadrage : tout ce qui se détache du fond, avec une petite marge.
    a = np.asarray(image).astype(int)
    ecart = np.abs(a - np.array(fond)).sum(axis=2) > 60
    ys, xs = np.where(ecart)
    cx, cy = (xs.min() + xs.max()) / 2, (ys.min() + ys.max()) / 2
    demi = max(xs.max() - xs.min(), ys.max() - ys.min()) / 2 * 1.03
    image = image.crop((round(cx - demi), round(cy - demi), round(cx + demi), round(cy + demi)))
    image = image.resize((COTE, COTE), Image.LANCZOS)

    tampon = io.BytesIO()
    image.save(tampon, "JPEG", quality=90, optimize=True)  # LovyanGFX : JPEG de base, pas progressif
    octets = tampon.getvalue()

    lignes = [", ".join(f"0x{b:02x}" for b in octets[i:i + 16]) for i in range(0, len(octets), 16)]
    cible = JEU / "firmware" / "src" / "logo.h"
    cible.write_text(
        "// Le logo du démarrage, produit par outils/logo_ecran.py (ne pas modifier à la main).\n"
        "#pragma once\n\n"
        "#include <cstdint>\n\n"
        "namespace logo {\n\n"
        f"constexpr int COTE = {COTE};\n"
        f"constexpr uint32_t FOND = 0x{fond[0]:02x}{fond[1]:02x}{fond[2]:02x};\n\n"
        "const uint8_t JPEG[] = {\n  " + ",\n  ".join(lignes) + "\n};\n\n"
        "}  // namespace logo\n")
    print(f"✓ {cible.relative_to(JEU)} : {len(octets)} octets")


if __name__ == "__main__":
    main()
