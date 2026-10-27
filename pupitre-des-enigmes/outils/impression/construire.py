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
from pypdf import PageObject, PdfReader, PdfWriter, Transformation

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


GABARIT_NOTICE = """<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>Notice de montage</title>
<link rel="stylesheet" href="commun.css">
<style>
  @page {{ size: A4; margin: 13mm 14mm 15mm; }}
  body {{ font-size: 9.8pt; }}
  .couverture {{ background: var(--vert); color: #fff; border-radius: 10px; padding: 26px 28px; margin-bottom: 16px; }}
  .couverture h1 {{ color: #fff; font-size: 30pt; margin: 0; }}
  .couverture p {{ color: #f2d9a6; margin: 6px 0 0; font-size: 11pt; }}
  .sommaire {{ columns: 2; font-size: 10pt; }}
  .partie {{ break-before: page; background: var(--vert); color: #fff; border-radius: 8px; padding: 10px 16px; font-size: 20pt; }}
  h2 {{ break-after: avoid; border-bottom: 2px solid var(--or); padding-bottom: 2px; margin-top: 16px; }}
  h3 {{ break-after: avoid; }}
  table {{ font-size: 8.5pt; }}
  tr {{ break-inside: avoid; }}
  td, th {{ padding: 3px 5px; vertical-align: top; }}
  pre {{ font-size: 6.6pt; line-height: 1.15; break-inside: avoid; background: #fbf7ee; border: 1px solid var(--filet);
         border-radius: 4px; padding: 6px; white-space: pre; overflow: hidden; }}
  code {{ font-family: "DejaVu Sans Mono", monospace; font-size: 8.4pt; }}
  li {{ margin-bottom: 2px; }}
</style></head>
<body>{corps}</body></html>
"""


def notice(cible):
    """La notice de montage : la console (boîtier, électronique, câblage), puis l'installation
    du programme et des cartes SD, d'après console/README.md, firmware/binaires/LISEZMOI.md
    et les § 0 à 6 de firmware/README.md."""
    def md(texte):
        texte = re.sub(r"\[([^\]]+)\]\([^)]+\)", r"\1", texte)  # les liens n'ont pas de sens sur papier
        return markdown.markdown(texte, extensions=["tables", "fenced_code"])

    console = (JEU / "console" / "README.md").read_text(encoding="utf-8")
    console = re.sub(r"^# .*\n", "", console, count=1)
    binaires = (JEU / "firmware" / "binaires" / "LISEZMOI.md").read_text(encoding="utf-8")
    cartes_sd = (JEU / "cartes-sd" / "LISEZMOI.md").read_text(encoding="utf-8")
    cartes_sd = re.sub(r"^# .*\n", "", cartes_sd, count=1)
    binaires = re.sub(r"^# .*\n", "", binaires, count=1)
    programme = (JEU / "firmware" / "README.md").read_text(encoding="utf-8")
    debut, fin = programme.index("## 0. Sans rien installer"), programme.index("## 7. Le simulateur")
    programme = programme[debut:fin]
    sections = re.findall(r"^## (.+)$", console, re.M)
    sommaire = "".join(f"<li>{escape(t)}</li>" for t in sections)
    corps = f"""<div class="couverture"><h1>Le Pupitre des Énigmes</h1>
<p>Notice de montage et d'installation électronique : le boîtier, les composants, le câblage,
le programme de l'ESP32 et les cartes SD.</p></div>
<h3>Partie 1 · La console</h3><ul class="sommaire">{sommaire}</ul>
<h3>Partie 2 · Le programme et les cartes SD</h3>
<ul class="sommaire"><li>Programmer l'ESP32 depuis un navigateur</li><li>Installer PlatformIO, téléverser, mettre à jour par le Wi-Fi</li>
<li>Les cartes micro-SD, sans script</li><li>Préparer les cartes micro-SD avec le script</li><li>Enregistrer les cartes, jouer, régler</li></ul>
<div class="partie">Partie 1 · La console</div>
{md(console)}
<div class="partie">Partie 2 · Le programme et les cartes SD</div>
<h2>Programmer l'ESP32 depuis un navigateur</h2>
{md(binaires)}
<h2>Les cartes micro-SD, sans script</h2>
{md(cartes_sd)}
{md(programme)}"""
    cible.write_text(GABARIT_NOTICE.format(corps=corps), encoding="utf-8")


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
    # La mission de test du pupitre : une fiche (joueurs, puis maître du jeu), le plan du banc d'essai et les cartes.
    {"num": 5, "monde": "atelier", "nom": "L'Atelier du professeur Lumen", "materiel": [
        ("fiche-mission-5", "fiche-mission", "Fiche de mission"),
        ("plateau", "plateau", ""),
        ("cartes", "cartes", ""),
    ]},
    # Un jeu de communication : la fiche (démineur), le manuel des experts (produit par
    # mondes/deminage/generer.py) et les cartes.
    {"num": 6, "monde": "deminage", "nom": "Brigade de déminage", "materiel": [
        ("fiche-mission-6", "fiche-mission", "Fiche de mission"),
        ("manuel", "manuel", "Manuel de désamorçage · experts"),
        ("cartes", "cartes", ""),
    ]},
    {"num": 7, "monde": "blackwood", "nom": "Le Manoir Blackwood"},
]


MONDES_NOMS = {m["num"]: m["nom"] for m in MISSIONS}

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
               fichier("deroule-maitre-du-jeu"), paysage=True, pied=f"{nom} · Déroulé du maître du jeu")
    temporaire(f"doublage-{n}",
               lambda h: doublage([JEU / "console" / "audio" / "repliques.json", dossier / "audio" / f"mission-{n}.json"],
                                  h, f"{nom}, et la voix du Pupitre"),
               fichier("script-doublage"), pied=f"{nom} · Script de doublage")

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
               pied=f"{nom} · {pied}" if pied else "")
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
    livret(m)


# ----------------------------------------------------------------------------------------
# Les livrets A5 : couverture, fiche de mission et fiche d'enquête, pages de notes, 4e de
# couverture. Deux fichiers : les pages A5 dans l'ordre de lecture, et la brochure imposée
# (feuilles A4 paysage, recto verso bord court, à plier en deux et agrafer au milieu).
# Les joueurs ne voient jamais de numéro de mission : seulement son nom.
# ----------------------------------------------------------------------------------------
DIFFICULTES = {1: "Facile", 2: "Intermédiaire", 3: "Difficile"}
LIVRETS = {
    1: {"titre": "La Disparition d'Éclipse", "genre": "Enquête : qui, où, pourquoi", "joueurs": "2 à 6", "duree": "60 min",
        "public": "Adultes et ados", "etoiles": 1, "embleme": "🐴", "pages": ["fiche-mission-1", "fiche-enquete"],
        "description": "Normandie, samedi, 8 h. Éclipse, la jument favorite du Grand Prix de Deauville, a disparu de son box. "
                       "À 9 h, l'expert de l'assurance signera le constat de vol, et plus personne ne la cherchera. Une heure pour "
                       "découvrir qui l'a fait sortir, où elle se trouve, et pourquoi.",
        "essentiel": ["6 rôles aux pouvoirs secrets, 6 suspects, 9 lieux", "La façade du haras, la fente et le plateau",
                      "Le casque : confidences et alibis", "La forge, l'enclume, le sentier aux gestes", "Le tiroir secret du bureau",
                      "Papy Marcel pour les indices"]},
    2: {"titre": "Le Casse du Siècle", "genre": "Casse et bluff : vous êtes les voleurs", "joueurs": "2 à 6", "duree": "60 min",
        "public": "Adultes", "etoiles": 2, "embleme": "🎰", "pages": ["fiche-mission-2", "fiche-enquete"],
        "description": "Monte-Carlo, 1928. Ce soir, le baron de Valcourt exhibe « L'Œil du Nil », un diamant rose de 42 carats, "
                       "rangé depuis 22 h 45 dans le coffre du casino. À minuit, le directeur l'ouvrira. Vous êtes une bande de "
                       "cambrioleurs : ouvrez le coffre et repartez avec le diamant, sans éveiller les soupçons.",
        "essentiel": ["La jauge de Soupçon : à 12, vous êtes démasqués", "Le bluff : répondre sans hésiter",
                      "Le stéthoscope au casque pour ouvrir le coffre", "Les rondes du couloir au gros bouton",
                      "6 rôles de cambrioleurs", "Le coup final : qui, où, dans quoi"]},
    3: {"titre": "Signal Perdu", "genre": "Enquête de science-fiction", "joueurs": "2 à 6", "duree": "75 min",
        "public": "Adultes", "etoiles": 3, "embleme": "🚀", "pages": ["fiche-mission-3", "fiche-enquete"],
        "description": "An 2147. La station minière Kepler-9 ne répond plus depuis 72 heures. Votre vaisseau de secours vient de "
                       "s'amarrer, avec 75 minutes d'oxygène dans vos combinaisons. À bord, une seule voix vous accueille : "
                       "HÉLIOS, l'intelligence artificielle de la station. Calme. Trop calme.",
        "essentiel": ["L'oxygène : 75 minutes au chrono", "Répartir l'énergie entre six modules", "La boîte noire et le noyau d'HÉLIOS",
                      "Un spectrogramme à décoder", "Variante « Traître à bord » dès 4 joueurs", "6 rôles d'équipage"]},
    4: {"titre": "Dernier souffle", "genre": "Escape game sous-marin", "joueurs": "2 à 6", "duree": "60 min",
        "public": "Adultes", "etoiles": 3, "embleme": "⚓", "pages": ["fiche-mission-4", "carnet-de-bord"],
        "description": "Le sous-marin Narval repose par 142 mètres de fond. L'air n'est plus recyclé : une heure d'oxygène. Seule la "
                       "dynamo de secours, actionnée à la main, garde un peu de lumière. Un sous-marin de sauvetage arrive, mais le "
                       "sas est bloqué. Débloquez la sortie avant de manquer d'air.",
        "essentiel": ["Pas d'enquête : sortir avant de manquer d'air", "La dynamo : quelqu'un au gros bouton toute la partie",
                      "Combiner deux objets : la fente et le plateau sont vos deux mains", "La clé de commandement, les voyants du bord",
                      "Des gestes à quatre mains", "L'interphone pour les conseils"]},
    5: {"titre": "Banc d'essai", "genre": "La mission de test du pupitre", "joueurs": "2", "duree": "20 à 30 min",
        "public": "Tous", "etoiles": 1, "embleme": "⚙️", "pages": ["fiche-mission-5"],
        "description": "Le professeur Lumen a construit ce pupitre. Avant de le livrer, il faut essayer chacun de ses organes : "
                       "boutons, molette, voyants, capteurs, lecteurs de cartes, casque, et même le tiroir secret. Chaque organe qui "
                       "répond allume un voyant vert. Quand tout est vert, le coffre s'ouvre.",
        "essentiel": ["Treize bancs d'essai, un par organe", "Chaque organe validé passe son voyant au vert",
                      "Une fiche de recette pour noter ce qui marche", "Le casque, le coffre et le tiroir secret pour finir",
                      "Idéal après le montage, ou avant une soirée"]},
    6: {"titre": "Désamorçage", "genre": "Jeu de communication coopératif", "joueurs": "2 à 8", "duree": "12 min par bombe",
        "public": "Ados et adultes", "etoiles": 2, "embleme": "💣", "pages": ["fiche-mission-6"],
        "description": "Une bombe vient d'être découverte. Un seul d'entre vous la voit : le démineur, au pupitre. Les autres, les "
                       "experts, ont le manuel de désamorçage, mais ne regardent jamais l'écran. Il va falloir parler, vite et bien.",
        "essentiel": ["Un démineur au pupitre, des experts avec le manuel", "12 bombes tirées au sort : jamais la même partie",
                      "8 modules : fils, détonateur, clavier, surchauffe, viseur, nuancier, radio, stabilisateur",
                      "3 erreurs, et tout saute", "On change de démineur à chaque bombe"]},
    7: {"titre": "La dernière nuit de Lord Edgar", "genre": "Enquête gothique", "joueurs": "3 à 8", "duree": "60 min",
        "public": "Adultes", "etoiles": 2, "embleme": "🕯️", "pages": ["fiche-mission-7", "fiche-enquete"],
        "description": "Dartmoor, 31 octobre 1893. Venus pour une séance de spiritisme, vous trouvez Lord Edgar Blackwood mort dans "
                       "sa bibliothèque. Le docteur parle d'une crise cardiaque ; vous n'y croyez pas. L'inspecteur n'arrivera qu'à "
                       "minuit. Une heure pour découvrir qui l'a tué, où, et avec quoi.",
        "essentiel": ["La lampe à huile à garder allumée (bouton bleu)", "Une séance de spiritisme : le fantôme parle",
                      "6 rôles, 6 suspects, 10 lieux", "La poussière de la chapelle, le tiroir secret du bureau",
                      "Le casque : alibis et confidences", "Le corbeau pour les conseils"]},
}

A5_CSS = """<style>
  /* Le livret A5 : la page A4 d'origine, réduite et remise en page au format A5. */
  @page { size: 148mm 210mm !important; margin: 9mm 8mm 10mm !important; }
  html { zoom: .74; }
</style>
</head>"""

GABARIT_COUVERTURE = """<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>{titre}</title>
<link rel="stylesheet" href="commun.css">
<style>
  @page {{ size: 148mm 210mm; margin: 0; }}
  body {{ margin: 0; }}
  .c {{ width: 148mm; height: 210mm; box-sizing: border-box; padding: 9mm 10mm; background: radial-gradient(circle at 50% 30%, {clair}, {fond} 72%);
        color: {texte}; position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; }}
  .c::before {{ content: ""; position: absolute; inset: 5mm; border: 1.2px solid {accent}; border-radius: 3mm; }}
  .haut {{ display: flex; align-items: center; gap: 3mm; font-size: 7.5pt; letter-spacing: .22em; text-transform: uppercase; color: {accent}; }}
  .haut img {{ width: 13mm; height: 13mm; }}
  .monde {{ font-family: "Playfair"; font-style: italic; font-size: 12pt; margin-top: 5mm; color: {accent}; }}
  .embleme {{ width: 34mm; height: 34mm; border-radius: 50%; border: 2px solid {accent}; display: flex; align-items: center; justify-content: center;
             font-size: 46pt; margin: 4mm 0 3mm; background: rgba(255,255,255,.06); overflow: hidden; }}
  .embleme img {{ width: 100%; height: 100%; object-fit: cover; }}
  h1 {{ font-family: "Playfair"; font-size: 25pt; line-height: 1.05; color: {texte}; margin: 0; }}
  .genre {{ font-size: 9pt; letter-spacing: .14em; text-transform: uppercase; color: {accent}; margin-top: 2.5mm; }}
  .etoiles {{ font-size: 18pt; letter-spacing: .15em; color: {accent}; margin-top: 3mm; line-height: 1; }}
  .etoiles span {{ opacity: .25; }}
  .niveau {{ font-size: 8pt; letter-spacing: .2em; text-transform: uppercase; }}
  .infos {{ display: flex; gap: 3mm; margin: 4mm 0 3mm; }}
  .infos div {{ border-top: 1px solid {accent}; padding-top: 1.5mm; width: 37mm; font-size: 7.5pt; letter-spacing: .08em; text-transform: uppercase; }}
  .infos b {{ display: block; font-family: "Playfair"; font-size: 13pt; letter-spacing: 0; text-transform: none; color: {texte}; }}
  .description {{ font-family: "Playfair"; font-style: italic; font-size: 9.6pt; line-height: 1.35; margin: 1mm 2mm 3mm; }}
  .essentiel {{ text-align: left; width: 100%; border: 1px solid {accent}88; border-radius: 2mm; padding: 2.5mm 4mm; font-size: 8.4pt; }}
  .essentiel h3 {{ margin: 0 0 1mm; color: {accent}; font-size: 9pt; letter-spacing: .16em; text-transform: uppercase; font-family: "Source Sans"; }}
  .essentiel ul {{ margin: 0; padding-left: 4mm; }}
  .essentiel li {{ margin: .6mm 0; }}
</style></head><body><div class="c">
  <div class="haut"><img src="../../console/logo-medaillon.png" alt=""><span>Le Pupitre des Énigmes</span></div>
  <div class="monde">{monde}</div>
  <div class="embleme">{embleme}</div>
  <h1>{titre}</h1>
  <div class="genre">{genre}</div>
  <div class="etoiles">{etoiles}</div>
  <div class="niveau">{niveau}</div>
  <div class="infos"><div><b>{joueurs}</b>joueurs</div><div><b>{duree}</b>durée</div><div><b>{public}</b>public</div></div>
  <div class="description">{description}</div>
  <div class="essentiel"><h3>L'essentiel</h3><ul>{essentiel}</ul></div>
</div></body></html>
"""

GABARIT_NOTES = """<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>Notes</title><link rel="stylesheet" href="commun.css">
<style>
  @page {{ size: 148mm 210mm; margin: 12mm 11mm; }}
  h2 {{ font-size: 14pt; margin: 0 0 4mm; }}
  .lignes {{ height: 168mm; background: repeating-linear-gradient(transparent 0 8.4mm, #cfc6b4 8.4mm 8.7mm); }}
</style></head><body><h2>Notes de l'équipe · {titre}</h2><div class="lignes"></div></body></html>
"""

GABARIT_DOS = """<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>Le Pupitre des Énigmes</title><link rel="stylesheet" href="commun.css">
<style>
  @page {{ size: 148mm 210mm; margin: 0; }}
  body {{ margin: 0; }}
  .d {{ width: 148mm; height: 210mm; box-sizing: border-box; padding: 14mm 12mm; background: radial-gradient(circle at 50% 35%, #34385a, #191b2b 70%);
        color: #e9e6f2; text-align: center; display: flex; flex-direction: column; align-items: center; }}
  img {{ width: 62mm; height: 62mm; margin: 4mm 0 4mm; }}
  h2 {{ color: #fff; border: 0; font-size: 17pt; margin: 0; }}
  p {{ font-family: "Playfair"; font-style: italic; color: #e9cf8f; margin: 1mm 0 6mm; }}
  table {{ font-size: 8.4pt; width: 100%; color: #e9e6f2; }}
  td {{ border-bottom: 1px solid rgba(196,154,58,.35); padding: 1.6mm 2mm; background: none; text-align: left; }}
  td.e {{ color: #e9cf8f; text-align: right; white-space: nowrap; }}
  .ici td {{ color: #fff; font-weight: 700; }}
</style></head><body><div class="d">
  <img src="../../console/logo-medaillon.png" alt="">
  <h2>Le Pupitre des Énigmes</h2>
  <p>Escape game de table · multi-mondes</p>
  <table>{missions}</table>
</div></body></html>
"""


def eclaircir(couleur, part):
    """« #15110f » un peu plus clair : le centre du dégradé de la couverture."""
    r, v, b = (int(couleur[i:i + 2], 16) for i in (1, 3, 5))
    return "#" + "".join(f"{round(c + (255 - c) * part):02x}" for c in (r, v, b))


def etoiles(n):
    return "★" * n + f"<span>{'★' * (3 - n)}</span>"


def livret(m):
    n = m["num"]
    if n not in LIVRETS:
        return
    L = LIVRETS[n]
    dossier = JEU / "mondes" / m["monde"]
    theme = json.loads((dossier / "pupitre" / "monde.json").read_text(encoding="utf-8"))["theme"]
    pages = []
    lecture = PdfWriter()

    # Un livret composé page à page (impression/livret.html, feuille livret.css) prime sur la
    # mise au format A5 des fiches. Il doit compter un multiple de 4 pages.
    natif = dossier / "impression" / "livret.html"
    if natif.exists():
        pdf = SORTIE / f"_livret-{n}.pdf"
        rendre(natif, pdf)
        pages.append(pdf)
        for page in PdfReader(pdf).pages:
            lecture.add_page(page)
        if len(lecture.pages) % 4:
            sys.exit(f"✗ {natif.relative_to(JEU)} : {len(lecture.pages)} pages, il en faut un multiple de 4")
    else:
        composer(m, L, dossier, theme, pages, lecture)
    sortie = SORTIE / f"mission-{n}-livret-a5.pdf"
    lecture.write(str(sortie))
    imposer(m, lecture, pages, sortie)


def composer(m, L, dossier, theme, pages, lecture):
    """Le livret générique : couverture, fiches réduites au format A5, notes et 4e de couverture."""
    n = m["num"]

    # La couverture. Un logo de mission (impression/images/logo-mission.png) remplace l'emblème.
    logo = dossier / "impression" / "images" / "logo-mission.png"
    embleme = f'<img src="{logo.as_uri()}" alt="">' if logo.exists() else L["embleme"]
    couverture = GABARIT_COUVERTURE.format(
        titre=escape(L["titre"]), monde=escape(m["nom"]), embleme=embleme, genre=escape(L["genre"]), etoiles=etoiles(L["etoiles"]),
        niveau=DIFFICULTES[L["etoiles"]], joueurs=L["joueurs"], duree=L["duree"], public=L["public"],
        description=escape(L["description"]), essentiel="".join(f"<li>{escape(e)}</li>" for e in L["essentiel"]),
        fond=theme["fond"], clair=eclaircir(theme["fond"], .22), texte=theme["texte"], accent=theme["accent"])
    pdf_couv = SORTIE / f"_couverture-{n}.pdf"
    temporaire(f"couverture-{n}", lambda h: h.write_text(couverture, encoding="utf-8"), pdf_couv)
    pages.append(pdf_couv)

    # Les pages de la mission, remises au format A5.
    for source in L["pages"]:
        html = dossier / "impression" / f"{source}.html"
        copie = dossier / "impression" / f"_livret-{source}.html"
        copie.write_text(html.read_text(encoding="utf-8").replace("</head>", A5_CSS, 1), encoding="utf-8")
        pdf = SORTIE / f"_livret-{n}-{source}.pdf"
        try:
            rendre(copie, pdf)
        finally:
            copie.unlink()
        pages.append(pdf)

    for p in pages:
        for page in PdfReader(p).pages:
            lecture.add_page(page)
    # Un multiple de 4 pages : des pages de notes avant la 4e de couverture.
    manque = (-(len(lecture.pages) + 1)) % 4
    if manque:
        pdf_notes = SORTIE / f"_notes-{n}.pdf"
        temporaire(f"notes-{n}", lambda h: h.write_text(GABARIT_NOTES.format(titre=escape(L["titre"])), encoding="utf-8"), pdf_notes)
        pages.append(pdf_notes)
        for _ in range(manque):
            lecture.add_page(PdfReader(pdf_notes).pages[0])
    lignes = "".join(f'<tr class="{"ici" if k == n else ""}"><td>{escape(v["titre"])}</td><td>{escape(MONDES_NOMS[k])}</td>'
                     f'<td class="e">{"★" * v["etoiles"]}</td></tr>' for k, v in LIVRETS.items())
    pdf_dos = SORTIE / f"_dos-{n}.pdf"
    temporaire(f"dos-{n}", lambda h: h.write_text(GABARIT_DOS.format(missions=lignes), encoding="utf-8"), pdf_dos)
    pages.append(pdf_dos)
    lecture.add_page(PdfReader(pdf_dos).pages[0])


def imposer(m, lecture, pages, sortie):
    n = m["num"]
    # La brochure : deux pages A5 par face d'une feuille A4 paysage.
    total = len(lecture.pages)
    l5, h5 = float(lecture.pages[0].mediabox.width), float(lecture.pages[0].mediabox.height)
    brochure = PdfWriter()
    for f in range(total // 4):
        for gauche, droite in ((total - 1 - 2 * f, 2 * f), (2 * f + 1, total - 2 - 2 * f)):
            face = PageObject.create_blank_page(width=2 * l5, height=h5)
            face.merge_transformed_page(lecture.pages[gauche], Transformation())
            face.merge_transformed_page(lecture.pages[droite], Transformation().translate(l5, 0))
            brochure.add_page(face)
    imposee = SORTIE / f"mission-{n}-livret-a5-brochure.pdf"
    brochure.write(str(imposee))
    for p in pages:
        p.unlink()
    print(f"✓ {sortie.relative_to(JEU)} ({total} pages A5) · {imposee.relative_to(JEU)} ({total // 4} feuille{"s" if total > 4 else ""} A4 recto verso)")


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
        rendre(ICI / "lire-une-carte.html", SORTIE / "lire-une-carte-a5.pdf")
        rendre(ICI / "plan-cablage.html", SORTIE / "plan-cablage.pdf", pied="Le Pupitre des Énigmes · Plan de câblage")
        temporaire("notice-montage", notice, SORTIE / "notice-montage.pdf", pied="Le Pupitre des Énigmes · Notice de montage")
        temporaire("liste-achats", lambda h: achats(JEU / "console" / "achats.json", h),
                   SORTIE / "liste-achats.pdf", pied="Le Pupitre des Énigmes · Liste d'achat")


if __name__ == "__main__":
    main()
