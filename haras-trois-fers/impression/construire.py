#!/usr/bin/env python3
"""Fabrique les PDF imprimables du jeu dans ../pdf/.

    pip install markdown
    python3 construire.py

Le rendu utilise Chromium via Playwright (node rendre.js).
"""
import re
import subprocess
from pathlib import Path

import markdown

ICI = Path(__file__).resolve().parent
JEU = ICI.parent
SORTIE = JEU / "pdf"

GABARIT_DEROULE = """<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>{titre}</title>
<link rel="stylesheet" href="commun.css">
<style>
  @page {{ size: A4 landscape; margin: 12mm 14mm 14mm; }}
  body {{ font-size: 9.8pt; }}
  .confidentiel {{ display: flex; justify-content: space-between; align-items: center; background: var(--rouge); color: #fff;
                   padding: 5px 12px; border-radius: 4px; font-size: 8.5pt; letter-spacing: .12em; text-transform: uppercase; margin-bottom: 10px; }}
  h1 {{ font-size: 24pt; }}
  h2 {{ break-after: avoid; }}
  h3 {{ break-after: avoid; }}
  table {{ font-size: 8.6pt; break-inside: avoid; }}
  td, th {{ padding: 3px 5px; }}
  pre {{ font-size: 7.4pt; display: inline-block; min-width: 46%; vertical-align: top; margin-right: 1.5%; }}
</style></head>
<body>
<div class="confidentiel"><span>Les Mystères du Haras des Trois Fers</span><span>Confidentiel · maître du jeu · contient les solutions</span></div>
{corps}
</body></html>
"""


def deroule(source, cible):
    texte = source.read_text(encoding="utf-8")
    # Le dernier chapitre est une note de développement, pas utile au maître du jeu.
    texte = texte.split("\n## 6.")[0]
    # Les liens vers les autres fichiers du dépôt n'ont pas de sens sur papier.
    texte = re.sub(r"\[([^\]]+)\]\([^)]+\.(?:md|json)\)", r"\1", texte)
    corps = markdown.markdown(texte, extensions=["tables", "fenced_code"])
    # Les écrans et le déroulé commencent chacun sur une nouvelle page.
    corps = re.sub(r"<h2>([34]\.)", r'<h2 class="saut">\1', corps)
    titre = re.search(r"^# (.+)$", texte, re.M).group(1)
    cible.write_text(GABARIT_DEROULE.format(titre=titre, corps=corps), encoding="utf-8")


def rendre(html, pdf, paysage=False, pied=""):
    commande = ["node", str(ICI / "rendre.js"), str(html), str(pdf), "paysage" if paysage else "portrait"]
    if pied:
        commande.append(pied)
    subprocess.run(commande, check=True)
    print(f"✓ {pdf.relative_to(JEU)}")


def main():
    SORTIE.mkdir(exist_ok=True)
    temporaire = ICI / "_deroule-mission-1.html"
    deroule(JEU / "mission-1-deroule.md", temporaire)
    try:
        rendre(temporaire, SORTIE / "mission-1-deroule-maitre-du-jeu.pdf", paysage=True,
               pied="Mission 1 · La Disparition d'Éclipse · Déroulé du maître du jeu")
    finally:
        temporaire.unlink()
    rendre(ICI / "fiche-mission-1.html", SORTIE / "mission-1-fiche-joueurs.pdf",
           pied="Mission 1 · La Disparition d'Éclipse · Fiche de mission")
    rendre(ICI / "mode-emploi.html", SORTIE / "mode-emploi.pdf",
           pied="Les Mystères du Haras des Trois Fers · Mode d'emploi")


if __name__ == "__main__":
    main()
