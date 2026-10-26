#!/usr/bin/env python3
"""Fabrique les PDF imprimables du Pupitre des Énigmes dans pdf/ (à la racine).

    pip install markdown pypdf
    python3 construire.py        (tout)
    python3 construire.py 2      (seulement la mission 2)

Le rendu utilise Chromium via Playwright (node rendre.js).
"""
import json
import re
import subprocess
import sys
from html import escape
from pathlib import Path

import markdown
from pypdf import PdfReader, PdfWriter

ICI = Path(__file__).resolve().parent
JEU = ICI.parent.parent
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

CASQUE = '<span class="casque">🎧 casque</span>'


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
    # Les personnages dans l'ordre du casting, puis les voix de service.
    service = ["narrateur", "pupitre"]
    cles = [c for c in voix if c in repliques and c not in service] + [c for c in service if c in repliques]

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


GABARIT_ACHATS = """<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>Liste d'achat</title>
<link rel="stylesheet" href="commun.css">
<style>
  :root {{ --vert: #23263a; --or: #c49a3a; --cuir: #7a4e8a; }}
  @page {{ size: A4; margin: 13mm 13mm 15mm; }}
  body {{ font-size: 9.8pt; }}
  .entete {{ background: radial-gradient(circle at 80% 20%, #34385a, #191b2b 75%); color: #fff; border-radius: 8px; padding: 16px 20px; margin-bottom: 10px; }}
  .entete h1 {{ color: #fff; margin: 0; }}
  .entete p {{ margin: 4px 0 0; color: #e9cf8f; }}
  .totaux {{ display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 10px 0 4px; }}
  .totaux div {{ background: #fff; border: 1px solid var(--filet); border-radius: 6px; padding: 8px 12px; }}
  .totaux strong {{ display: block; font-family: "Playfair"; font-size: 18pt; color: var(--vert); }}
  h2 {{ display: flex; justify-content: space-between; align-items: baseline; font-size: 13.5pt; margin-top: 14px; }}
  h2 small {{ font-family: "Source Sans"; font-size: 10pt; color: var(--cuir); font-weight: 700; }}
  .option {{ font-family: "Source Sans"; font-size: 8.5pt; color: var(--gris); font-weight: 400; font-style: italic; }}
  table {{ break-inside: auto; }}
  td.coche {{ width: 16px; font-size: 12pt; color: var(--gris); }}
  td.article {{ width: 30%; font-weight: 600; }}
  td.detail {{ color: var(--gris); font-size: 8.8pt; }}
  td.nb {{ text-align: right; white-space: nowrap; width: 9%; }}
  th.nb {{ text-align: right; }}
</style></head>
<body>{corps}</body></html>
"""


def euros(montant):
    texte = f"{montant:,.2f}".replace(",", " ").replace(".", ",")
    return (texte[:-3] if texte.endswith(",00") else texte) + " €"


def achats(source, cible):
    donnees = json.loads(source.read_text(encoding="utf-8"))
    total_essentiel = total_option = 0
    sections = []
    for section in donnees["sections"]:
        sous_total = sum(a["qte"] * a["prix"] for a in section["articles"])
        if section.get("optionnel"):
            total_option += sous_total
        else:
            total_essentiel += sous_total
        lignes = "".join(
            f"<tr><td class='coche'>☐</td><td class='article'>{escape(a['article'])}</td>"
            f"<td class='detail'>{escape(a['detail'])}</td><td class='nb'>{a['qte']}</td>"
            f"<td class='nb'>{euros(a['prix']) if a['prix'] else '—'}</td>"
            f"<td class='nb'><strong>{euros(a['qte'] * a['prix']) if a['prix'] else '—'}</strong></td></tr>"
            for a in section["articles"])
        option = " <span class='option'>(selon besoin)</span>" if section.get("optionnel") else ""
        sections.append(
            f"<h2><span>{escape(section['nom'])}{option}</span><small>{euros(sous_total)}</small></h2>"
            "<table><thead><tr><th></th><th>Article</th><th>Détail et mots-clés</th><th class='nb'>Qté</th>"
            f"<th class='nb'>Prix unit.</th><th class='nb'>Total</th></tr></thead><tbody>{lignes}</tbody></table>")
    conseils = "".join(f"<li>{escape(c)}</li>" for c in donnees["conseils"])
    corps = f"""<div class="entete"><h1>{escape(donnees['titre'])}</h1>
      <p>Console, casque, boîtier et matériel de la mission 1 · à cocher au fil des commandes</p></div>
      <div class="totaux">
        <div>Indispensable<strong>≈ {euros(total_essentiel)}</strong>électronique, boîtier, mission 1</div>
        <div>Selon besoin<strong>≈ {euros(total_option)}</strong>outillage, ElevenLabs, fablab</div>
        <div>Tout compris<strong>≈ {euros(total_essentiel + total_option)}</strong>si vous partez de zéro</div>
      </div>
      <p class="petit">{escape(donnees['note'])}</p>
      {''.join(sections)}
      <div class="encadre or insecable"><h4>Conseils</h4><ul style="margin:0">{conseils}</ul></div>"""
    cible.write_text(GABARIT_ACHATS.format(corps=corps), encoding="utf-8")


def rendre(html, pdf, paysage=False, pied=""):
    commande = ["node", str(ICI / "rendre.js"), str(html), str(pdf), "paysage" if paysage else "portrait"]
    if pied:
        commande.append(pied)
    subprocess.run(commande, check=True)
    print(f"✓ {pdf.relative_to(JEU)}")


MISSIONS = [
    {"num": 1, "monde": "haras", "nom": "Le Haras des Trois Fers", "materiel": [
        ("fiche-mission-1", "fiche-joueurs", "Fiche de mission"),
        ("plateau", "plateau", ""),
        ("cartes", "cartes", ""),
        ("documents-secrets", "documents-secrets", "Documents secrets des rôles"),
        ("enveloppes", "enveloppes", "Contenu des enveloppes (ne pas montrer aux joueurs)"),
        ("fiche-enquete", "fiche-enquete", ""),
    ]},
    {"num": 2, "monde": "casse", "nom": "Monte-Carlo 1928"},
    {"num": 3, "monde": "kepler", "nom": "Station Kepler-9"},
    # Un escape game : pas de rôles (donc pas de documents secrets), un carnet de bord au lieu de la fiche d'enquête.
    # Le plateau renomme les lecteurs du pupitre (MAIN GAUCHE · COMPARTIMENT · MAIN DROITE · DYNAMO).
    {"num": 4, "monde": "narval", "nom": "Sous-marin Narval", "materiel": [
        ("fiche-mission-4", "fiche-joueurs", "Fiche de mission"),
        ("plateau", "plateau", ""),
        ("cartes", "cartes", ""),
        ("enveloppes", "enveloppes", "Contenu des enveloppes (ne pas montrer aux joueurs)"),
        ("carnet-de-bord", "carnet-de-bord", ""),
    ]},
]


def temporaire(nom, fabriquer, pdf, **options):
    """Fabrique une page HTML intermédiaire, la rend en PDF, puis l'efface."""
    html = ICI / f"_{nom}.html"
    fabriquer(html)
    try:
        rendre(html, pdf, **options)
    finally:
        html.unlink()


def mission(m):
    n, nom = m["num"], m["nom"]
    dossier = JEU / "mondes" / m["monde"]

    def fichier(suffixe):
        return SORTIE / f"mission-{n}-{suffixe}.pdf"

    temporaire(f"deroule-{n}", lambda h: deroule(dossier / f"mission-{n}-deroule.md", h, nom),
               fichier("deroule-maitre-du-jeu"), paysage=True, pied=f"{nom} · Mission {n} · Déroulé du maître du jeu")
    temporaire(f"doublage-{n}",
               lambda h: doublage([JEU / "console" / "audio" / "repliques.json", dossier / "audio" / f"mission-{n}.json"],
                                  h, f"{nom} · Mission {n}, et la voix du Pupitre"),
               fichier("script-doublage"), pied=f"{nom} · Mission {n} · Script de doublage")

    # Le matériel de jeu, puis deux fichiers qui rassemblent tout pour l'imprimante : ce qui
    # s'imprime en recto seul, et les planches de cartes, à imprimer en recto verso.
    materiel = m.get("materiel") or [
        (f"fiche-mission-{n}", "fiche-joueurs", "Fiche de mission"),
        ("cartes", "cartes", ""),
        ("documents-secrets", "documents-secrets", "Documents secrets des rôles"),
        ("enveloppes", "enveloppes", "Contenu des enveloppes (ne pas montrer aux joueurs)"),
        ("fiche-enquete", "fiche-enquete", ""),
    ]
    recto, recto_verso = PdfWriter(), PdfWriter()
    for source, suffixe, pied in materiel:
        rendre(dossier / "impression" / f"{source}.html", fichier(suffixe),
               pied=f"{nom} · Mission {n} · {pied}" if pied else "")
        pages = PdfReader(fichier(suffixe)).pages
        if suffixe == "cartes":
            # Les planches vont par paires (rectos, puis versos en miroir) ; la notice, à la fin, en recto seul.
            for page in pages[:-1]:
                recto_verso.add_page(page)
            recto.add_page(pages[-1])
        else:
            for page in pages:
                recto.add_page(page)
    recto.write(str(fichier("a-imprimer-recto")))
    recto_verso.write(str(fichier("a-imprimer-recto-verso")))
    print(f"✓ pdf/mission-{n}-a-imprimer-recto.pdf ({len(recto.pages)} pages)")
    print(f"✓ pdf/mission-{n}-a-imprimer-recto-verso.pdf ({len(recto_verso.pages)} pages)")


def main():
    """python3 construire.py [numéros de mission]   (sans argument : tout)"""
    SORTIE.mkdir(exist_ok=True)
    choix = {int(a) for a in sys.argv[1:]}
    for m in MISSIONS:
        if not choix or m["num"] in choix:
            mission(m)
    if not choix:
        rendre(ICI / "mode-emploi.html", SORTIE / "mode-emploi.pdf", pied="Le Pupitre des Énigmes · Mode d'emploi")
        rendre(ICI / "gabarit-facade.html", SORTIE / "gabarit-facade.pdf")
        temporaire("liste-achats", lambda h: achats(JEU / "console" / "achats.json", h),
                   SORTIE / "liste-achats.pdf", pied="Le Pupitre des Énigmes · Liste d'achat")


if __name__ == "__main__":
    main()
