#!/usr/bin/env python3
"""Fabrique les PDF imprimables du Pupitre des Énigmes dans pdf/ (à la racine).

    pip install markdown
    python3 construire.py

Le rendu utilise Chromium via Playwright (node rendre.js).
"""
import json
import re
import subprocess
from html import escape
from pathlib import Path

import markdown

ICI = Path(__file__).resolve().parent
JEU = ICI.parent.parent
SORTIE = JEU / "pdf"
HARAS = JEU / "mondes" / "haras"

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
<div class="confidentiel"><span>Le Pupitre des Énigmes · {monde}</span><span>Confidentiel · maître du jeu · contient les solutions</span></div>
{corps}
</body></html>
"""


def deroule(source, cible, monde):
    texte = source.read_text(encoding="utf-8")
    # Les liens vers les autres fichiers du dépôt n'ont pas de sens sur papier.
    texte = re.sub(r"\[([^\]]+)\]\([^)]+\.(?:md|json)\)", r"\1", texte)
    corps = markdown.markdown(texte, extensions=["tables", "fenced_code"])
    # Les écrans et le déroulé commencent chacun sur une nouvelle page.
    corps = re.sub(r"<h2>([34]\.)", r'<h2 class="saut">\1', corps)
    titre = re.search(r"^# (.+)$", texte, re.M).group(1)
    cible.write_text(GABARIT_DEROULE.format(titre=titre, corps=corps, monde=monde), encoding="utf-8")


GABARIT_DOUBLAGE = """<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>Script de doublage</title>
<link rel="stylesheet" href="commun.css">
<style>
  @page {{ size: A4; margin: 13mm 13mm 15mm; }}
  body {{ font-size: 10pt; }}
  .entete {{ background: var(--vert); color: #fff; border-radius: 8px; padding: 16px 20px; margin-bottom: 14px; }}
  .entete h1 {{ color: #fff; margin: 0; }}
  .entete p {{ margin: 4px 0 0; color: #f2d9a6; }}
  .voix {{ break-before: page; }}
  .fiche {{ display: grid; grid-template-columns: 1fr 1fr; gap: 6px 14px; background: #fff; border: 1px solid var(--filet);
            border-left: 5px solid var(--or); border-radius: 6px; padding: 10px 14px; margin-bottom: 10px; font-size: 9.5pt; }}
  .fiche .large {{ grid-column: 1 / -1; }}
  .fiche b {{ color: var(--vert); display: block; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .06em; }}
  .prompt {{ font-family: "DejaVu Sans Mono", monospace; font-size: 8pt; color: var(--gris); }}
  .stats {{ color: var(--cuir); font-weight: 600; margin: -2px 0 8px; }}
  table.repliques td {{ font-size: 9.6pt; }}
  table.repliques td.coche {{ width: 16px; font-size: 12pt; color: var(--gris); }}
  table.repliques td.fichier {{ white-space: nowrap; font-family: "DejaVu Sans Mono", monospace; font-size: 8.5pt; }}
  table.repliques td.usage {{ width: 22%; color: var(--gris); font-size: 8.8pt; }}
  .casque {{ display: inline-block; background: #6b3fa0; color: #fff; border-radius: 3px; padding: 0 4px; font-size: 8pt; margin-left: 4px; }}
</style></head>
<body>{corps}</body></html>
"""

# Les personnages d'abord, puis les voix de service.
CASQUE = '<span class="casque">🎧 casque</span>'
ORDRE_VOIX = ["helene", "lucas", "camille", "victoire", "antoine", "delorme", "porteur",
              "papy_marcel", "gendarme", "commentateur", "narrateur", "pupitre"]


def nombre(n):
    """1234 -> '1 234'"""
    return f"{n:,}".replace(",", " ")


def doublage(sources, cible, titre):
    """sources : liste de fichiers de répliques (chacun avec son voix.json voisin)."""
    voix, repliques = {}, {}
    for source in sources:
        contenu = json.loads(source.read_text(encoding="utf-8"))
        voix.update(json.loads((source.parent / "voix.json").read_text(encoding="utf-8"))["voix"])
        for ligne in contenu["lignes"]:
            repliques.setdefault(ligne["voix"], []).append((contenu["dossier"], ligne))
    cles = [c for c in ORDRE_VOIX if c in repliques] + sorted(set(repliques) - set(ORDRE_VOIX))

    def nb_car(cle):
        return sum(len(l["texte"]) for _, l in repliques[cle])

    resume = "".join(
        f"<tr><td><strong>{escape(voix[c]['nom'])}</strong></td><td>{len(repliques[c])}</td>"
        f"<td>{sum(1 for _, l in repliques[c] if l.get('sortie') == 'casque') or '—'}</td>"
        f"<td>{nombre(nb_car(c))}</td></tr>"
        for c in cles)
    total = sum(nb_car(c) for c in cles)
    corps = [f"""<div class="entete"><h1>Script de doublage</h1>
      <p>{escape(titre)} · toutes les répliques, voix par voix, à enregistrer avec ElevenLabs</p></div>
      <p>Chaque voix a sa page : son <strong>caractère</strong> (comment la jouer), sa description pour
      <em>Voice Design</em>, puis la liste de ses répliques. La colonne <strong>Fichier</strong> donne le nom
      du MP3 sur la carte SD. Les répliques marquées <span class="casque">🎧 casque</span> sont jouées dans le
      casque, pour un seul joueur. Cochez ☐ au fur et à mesure.</p>
      <table><thead><tr><th>Voix</th><th>Répliques</th><th>Au casque</th><th>Caractères</th></tr></thead>
      <tbody>{resume}<tr><td><strong>Total</strong></td><td>{sum(len(repliques[c]) for c in cles)}</td>
      <td></td><td><strong>{nombre(total)}</strong></td></tr></tbody></table>"""]

    for c in cles:
        v = voix[c]
        r = v["reglages"]
        lignes = "".join(
            f"<tr><td class='coche'>☐</td><td class='fichier'>{dossier:02d}/{l['n']:03d}.mp3</td>"
            f"<td class='usage'>{escape(l['usage'])}</td><td>{escape(l['texte'])}"
            f"{CASQUE if l.get('sortie') == 'casque' else ''}</td></tr>"
            for dossier, l in sorted(repliques[c], key=lambda x: (x[0], x[1]["n"])))
        corps.append(f"""<section class="voix"><h2>{escape(v['nom'])}</h2>
          <p class="stats">{len(repliques[c])} répliques · {nombre(nb_car(c))} caractères</p>
          <div class="fiche">
            <div class="large"><b>Caractère et jeu</b>{escape(v.get('portrait', ''))}</div>
            <div><b>Voix</b>{escape(v['description'])}</div>
            <div><b>Réglages ElevenLabs</b>stabilité {r['stability']} · similarité {r['similarity_boost']} · style {r['style']}</div>
            <div class="large"><b>Description pour Voice Design</b><span class="prompt">{escape(v['prompt_voice_design'])}</span></div>
          </div>
          <table class="repliques"><thead><tr><th></th><th>Fichier</th><th>Situation</th><th>Texte à dire</th></tr></thead>
          <tbody>{lignes}</tbody></table></section>""")
    cible.write_text(GABARIT_DOUBLAGE.format(corps="".join(corps)), encoding="utf-8")


def rendre(html, pdf, paysage=False, pied=""):
    commande = ["node", str(ICI / "rendre.js"), str(html), str(pdf), "paysage" if paysage else "portrait"]
    if pied:
        commande.append(pied)
    subprocess.run(commande, check=True)
    print(f"✓ {pdf.relative_to(JEU)}")


def main():
    SORTIE.mkdir(exist_ok=True)
    temporaire = ICI / "_deroule-mission-1.html"
    deroule(HARAS / "mission-1-deroule.md", temporaire, "Le Haras des Trois Fers")
    try:
        rendre(temporaire, SORTIE / "mission-1-deroule-maitre-du-jeu.pdf", paysage=True,
               pied="Le Haras des Trois Fers · Mission 1 · Déroulé du maître du jeu")
    finally:
        temporaire.unlink()
    rendre(HARAS / "impression" / "fiche-mission-1.html", SORTIE / "mission-1-fiche-joueurs.pdf",
           pied="Le Haras des Trois Fers · Mission 1 · Fiche de mission")
    rendre(ICI / "mode-emploi.html", SORTIE / "mode-emploi.pdf",
           pied="Le Pupitre des Énigmes · Mode d'emploi")

    temporaire = ICI / "_doublage-mission-1.html"
    doublage([JEU / "console" / "audio" / "repliques.json", HARAS / "audio" / "mission-1.json"], temporaire,
             "Le Haras des Trois Fers · Mission 1, et la voix du Pupitre")
    try:
        rendre(temporaire, SORTIE / "mission-1-script-doublage.pdf",
               pied="Le Haras des Trois Fers · Mission 1 · Script de doublage")
    finally:
        temporaire.unlink()


if __name__ == "__main__":
    main()
