"""Les plaques aimantées des missions (mondes/<monde>/impression/plateau.html), sur le gabarit commun.

Chaque mission habille le pupitre d'une plaque qui ne découvre que les organes dont elle se sert, et
les renomme pour l'histoire (l'œil devient le soupirail, le capteur de couleur la loupe…). Les organes
inutiles restent sous la plaque, cachés par un décor. Les plaques du Haras, du Narval et de l'Atelier
sont dessinées à la main ; celles-ci sont fabriquées ici :

    python3 outils/impression/plaques.py            (toutes)
    python3 outils/impression/plaques.py casse      (une seule)
"""
import sys
from pathlib import Path
JEU = Path(__file__).resolve().parents[2]

def fenetre_rect(x, y, w, h, fond):
    return f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="2" fill="{fond}" stroke="#ffffff" stroke-width=".5" stroke-dasharray="2 1.5"/>'

def fenetre_rond(cx, cy, r, fond, pas="1.5 1"):
    return f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="{fond}" stroke="#ffffff" stroke-width=".5" stroke-dasharray="{pas}"/>'

def lecteur_defaut(T):
    a, c, f = T['accent'], T['trait'], T['fenetre']
    return f'''  <!-- le lecteur à plat -->
  <rect x="257" y="105" width="76" height="70" rx="5" fill="none" stroke="{a}" stroke-width="1.2"/>
  <text x="295" y="112.5" text-anchor="middle" font-size="4.8" fill="{a}" letter-spacing=".6">{T['plateau']}</text>
  <rect x="270" y="117" width="50" height="40" rx="3" fill="none" stroke="{c}" stroke-width=".5" stroke-dasharray="2 1.5"/>
  <g transform="translate(295 137)">{T['plateau_dessin']}</g>
  <text x="295" y="164" text-anchor="middle" font-size="3.5" fill="{c}">{T['plateau2']}</text>
  <circle cx="325" cy="167" r="3.6" fill="none" stroke="{a}" stroke-width=".8"/>
  {fenetre_rond(325, 167, 2, f, ".8 .6")}

'''

def page(T):
    a, c, f, clair = T["accent"], T["trait"], T["fenetre"], T["clair"]
    svg = f'''
  <defs>
    <linearGradient id="fond" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{T['fond1']}"/><stop offset=".6" stop-color="{T['fond2']}"/><stop offset="1" stop-color="{T['fond3']}"/></linearGradient>
    <pattern id="motif" width="{T['motif_pas']}" height="{T['motif_pas']}" patternUnits="userSpaceOnUse">{T['motif']}</pattern>
    <pattern id="grille-hp" width="5" height="5" patternUnits="userSpaceOnUse"><circle cx="2.5" cy="2.5" r="1.1" fill="{f}"/></pattern>
    <g id="rivet"><circle r="2" fill="{a}" opacity=".85"/><circle r="1" fill="#ffffff" opacity=".35" cx="-.5" cy="-.5"/></g>
  </defs>
  <rect x=".5" y=".5" width="379" height="261" rx="10" fill="url(#fond)" stroke="#000" stroke-width="1"/>
  <rect x=".5" y=".5" width="379" height="261" rx="10" fill="url(#motif)"/>
  <rect x="5" y="5" width="370" height="252" rx="7" fill="none" stroke="{a}" stroke-width="1"/>
  <rect x="7.5" y="7.5" width="365" height="247" rx="5.5" fill="none" stroke="{a}" stroke-width=".35" opacity=".7"/>
  <g><use href="#rivet" x="100" y="9"/><use href="#rivet" x="280" y="9"/><use href="#rivet" x="100" y="253"/><use href="#rivet" x="280" y="253"/></g>
  <g fill="none" stroke="{c}" stroke-width=".4" opacity=".5"><circle cx="15" cy="15" r="5"/><circle cx="365" cy="15" r="5"/><circle cx="15" cy="247" r="5"/><circle cx="365" cy="247" r="5"/></g>
  <g font-family="{T['police']}">
  <text x="190" y="15" text-anchor="middle" font-size="5.2" fill="{a}" letter-spacing="1.3">{T['titre']}</text>

  <!-- le clavier -->
  <rect x="19" y="19" width="78" height="86" rx="3" fill="none" stroke="{c}" stroke-width=".9"/>
  {fenetre_rect(23, 23, 70, 78, f)}
  <text x="58" y="64" text-anchor="middle" font-size="4.5" fill="{clair}">✂ CLAVIER</text>
  <rect x="25" y="16.5" width="66" height="5" fill="{T['fond2']}"/>
  <text x="58" y="20.6" text-anchor="middle" font-size="4.2" fill="{a}" letter-spacing=".4">{T['clavier']}</text>

  <!-- le haut-parleur et l'anneau -->
  <circle cx="133" cy="62" r="20" fill="url(#grille-hp)" stroke="{c}" stroke-width=".9"/>
  <text x="133" y="90" text-anchor="middle" font-size="4" fill="{c}">{T['hp']}</text>
  <circle cx="190" cy="62" r="22" fill="none" stroke="{a}" stroke-width=".6" stroke-dasharray="4 1 1 1"/>
  {fenetre_rond(190, 62, 19, f, "2 1.5")}
  <text x="190" y="90" text-anchor="middle" font-size="4" fill="{c}">{T['anneau']}</text>

  <!-- l'écran -->
  <rect x="236" y="30" width="98" height="64" rx="3" fill="none" stroke="{a}" stroke-width=".9"/>
  {fenetre_rect(248, 37.5, 74, 49, f)}
  <text x="285" y="64" text-anchor="middle" font-size="4.5" fill="{clair}">✂ ÉCRAN 3,5"</text>
  <text x="285" y="99" text-anchor="middle" font-size="3.6" fill="{c}">{T['ecran']}</text>

  {T['cle']}
  {T['voyants']}

  <!-- la fente -->
  <rect x="150" y="116" width="80" height="34" rx="3" fill="none" stroke="{a}" stroke-width="1.2"/>
  <rect x="156" y="129.5" width="68" height="5" rx="1" fill="{f}" stroke="#ffffff" stroke-width=".5" stroke-dasharray="1.5 1"/>
  <text x="190" y="125.5" text-anchor="middle" font-size="5.2" fill="{a}" letter-spacing=".8">{T['fente']}</text>
  <text x="190" y="142" text-anchor="middle" font-size="3.7" fill="{c}">{T['fente2']}</text>
  <text x="190" y="146.6" text-anchor="middle" font-size="3.3" fill="{c}" opacity=".8">{T['fente3']}</text>

  {T.get('lecteur') or lecteur_defaut(T)}
  {T['couleur']}
  {T['casque']}
  {T['forge_enclume']}

  <!-- l'horloge -->
  <text x="175" y="166" text-anchor="middle" font-size="4.2" fill="{a}">{T['horloge']}</text>
  <rect x="146" y="168.5" width="58" height="23" rx="2" fill="none" stroke="{c}" stroke-width=".8"/>
  {fenetre_rect(150, 170.5, 50, 19, f)}
  <text x="175" y="182" text-anchor="middle" font-size="4" fill="{clair}">✂ HORLOGE</text>

  {T['oeil']}
  {T['boutons']}

  <!-- la molette -->
  <circle cx="190" cy="222" r="13" fill="none" stroke="{c}" stroke-width=".9"/>
  <g stroke="{c}" stroke-width=".7"><path d="M190 206v3M190 235v3M174 222h3M203 222h3"/></g>
  {fenetre_rond(190, 222, 3.5, f, "1 .8")}
  <text x="190" y="202" text-anchor="middle" font-size="4.2" fill="{a}">{T['molette']}</text>
  <text x="190" y="244" text-anchor="middle" font-size="3.2" fill="{c}">{T['molette2']}</text>

  <!-- le gros bouton -->
  <circle cx="295" cy="222" r="30" fill="none" stroke="{c}" stroke-width=".6" stroke-dasharray="4 1 1 1"/>
  <circle cx="295" cy="222" r="23" fill="none" stroke="{a}" stroke-width="1.1"/>
  {fenetre_rond(295, 222, 11, f, "2 1.5")}
  <text x="351" y="219" text-anchor="middle" font-size="5.4" fill="{a}">{T['gros']}</text>
  <text x="351" y="226" text-anchor="middle" font-size="3.3" fill="{c}">{T['gros2']}</text>
  <text x="236" y="253" text-anchor="middle" font-size="3" fill="{c}" opacity=".8">{T['signature']}</text>
  </g>'''
    return f'''<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>{T['mission']} · Plateau du pupitre</title>
<link rel="stylesheet" href="../../../outils/impression/commun.css">
<style>
  :root {{ --vert: {T['fond2']}; --or: {a}; }}
  @page {{ size: A4; margin: 5mm; }}
  .page {{ break-after: page; position: relative; }}
  .page:last-child {{ break-after: auto; }}
  .notice {{ padding: 6mm 9mm 0; }}
  .bandeau {{ display: flex; justify-content: space-between; font-size: 8pt; letter-spacing: .14em; text-transform: uppercase;
             color: {T['bandeau']}; border-bottom: 1.5px solid var(--or); padding-bottom: 3px; margin-bottom: 8px; }}
  .apercu svg {{ width: 100%; display: block; border-radius: 3mm; box-shadow: 0 1mm 3mm #0004; }}
  .moitie {{ width: 195mm; height: 262mm; margin: 0 auto; position: relative; }}
  .moitie svg {{ width: 195mm; height: 262mm; display: block; }}
  .reperes {{ font-family: "Share Tech Mono", monospace; font-size: 8pt; color: var(--gris); text-align: center; margin-top: 2mm; letter-spacing: .05em; }}
  table.organes td, table.organes th {{ padding: 1.5px 6px; font-size: 8.4pt; }}
  .notice p {{ font-size: 9.5pt; }}
</style>
</head>
<body>

<!-- Le plateau (380 × 262 mm, cotes du gabarit outils/impression/gabarit-facade.html), copié dans
     chaque page par le script en bas. Les organes dont la mission ne se sert pas restent sous la
     plaque : on ne découpe que les fenêtres en pointillés blancs. -->
<script type="text/plain" id="modele">{svg}
</script>

<div class="page notice">
  <div class="bandeau"><span>Le Pupitre des Énigmes · {T['monde']}</span><span>Plateau · {T['mission']}</span></div>
  <h1 style="margin-bottom:2mm">{T['h1']}</h1>
  <p>{T['intro']}</p>
  <div class="apercu"><svg viewBox="0 0 380 262" class="copie"></svg></div>
  <h3>Les organes, sous leur nom de mission</h3>
  <table class="organes"><tr><th>Sur la plaque</th><th>L'organe du pupitre</th><th>Ce qu'on en fait</th></tr>{T['table']}</table>
  <h3>Fabrication</h3>
  <ol style="font-size:9pt;margin-top:0">
    <li>Imprimez les deux pages suivantes <strong>à 100 %</strong> (sans « ajuster à la page »), en couleur, sur papier épais ou adhésif.</li>
    <li>Assemblez-les : la bande de 10 mm en trop à droite de la moitié gauche passe sous la moitié droite.</li>
    <li>Collez sur la façade (carton gris de 1,5 mm, dessous peint en noir mat), plastifiez si possible.</li>
    <li>Découpez <strong>seulement les fenêtres en pointillés blancs</strong> : {T['decoupes']}.</li>
  </ol>
</div>

<div class="page">
  <div class="moitie"><svg viewBox="0 0 195 262" class="copie"></svg></div>
  <div class="reperes">MOITIÉ GAUCHE · la bande de droite (10 mm, après les pointillés) passe sous la moitié droite</div>
</div>

<div class="page">
  <div class="moitie"><svg viewBox="185 0 195 262" class="copie"></svg></div>
  <div class="reperes">MOITIÉ DROITE · aligner le bord gauche sur les pointillés de la moitié gauche</div>
</div>

<script>
  const modele = document.getElementById("modele").textContent;
  const repere = '<path d="M185 0v262" stroke="#ffffff" stroke-width=".3" stroke-dasharray="3 3" opacity=".5"/>';
  document.querySelectorAll("svg.copie").forEach((s, i) => {{
    s.setAttribute("xmlns", "http://www.w3.org/2000/svg");
    s.innerHTML = modele.replace(/id="([\\w-]+)"/g, `id="$1-${{i}}"`).replace(/#([\\w-]+)\\)/g, `#$1-${{i}})`).replace(/href="#([\\w-]+)"/g, `href="#$1-${{i}}"`) + (i ? repere : "");
  }});
</script>
</body>
</html>
'''

def ligne(*cellules):
    return "<tr>" + "".join(f"<td>{x}</td>" for x in cellules) + "</tr>"

# ---------------------------------------------------------------- Le Muséum
A, C, F = "#c9a24a", "#e9dfc6", "#0f1a1b"
M = dict(
    monde="Le Muséum national", mission="Panique au Muséum", h1="La plaque du Muséum",
    fond1="#24403d", fond2="#1b2f2e", fond3="#14211f", accent=A, trait=C, fenetre=F, clair="#7f9792", bandeau="#8a6d24",
    police="Playfair, serif", motif_pas=14,
    motif='<path d="M7 0 14 7 7 14 0 7Z" fill="none" stroke="#c9a24a" stroke-width=".18" opacity=".35"/>',
    titre="MUSÉUM NATIONAL D'HISTOIRE NATURELLE · GRANDE GALERIE · 1912",
    clavier="COFFRE DE LA RÉSERVE", hp="LE DIRECTEUR", anneau="L'HORLOGE", ecran="l'écran du Muséum : la salle où vous êtes",
    cle='<!-- la clé : cachée, une rosace -->\n  <g transform="translate(358 40)" fill="none" stroke="#c9a24a" stroke-width=".5" opacity=".7"><circle r="9"/><circle r="5"/><path d="M0-9V9M-9 0H9M-6.4-6.4 6.4 6.4M-6.4 6.4 6.4-6.4"/></g>',
    voyants='''<!-- les voyants : cachés, sous le cartouche d'Auguste -->
  <rect x="31" y="108" width="98" height="56" rx="3" fill="none" stroke="#c9a24a" stroke-width=".8"/>
  <rect x="34" y="111" width="92" height="50" rx="2" fill="none" stroke="#c9a24a" stroke-width=".3"/>
  <text x="80" y="124" text-anchor="middle" font-size="7.4" fill="#c9a24a" font-weight="700" letter-spacing="1.5">AUGUSTE</text>
  <text x="80" y="131" text-anchor="middle" font-size="3.8" fill="#e9dfc6" font-style="italic">Tyrannosaurus rex</text>
  <path d="M50 136h60" stroke="#c9a24a" stroke-width=".3"/>
  <text x="80" y="143" text-anchor="middle" font-size="3.4" fill="#e9dfc6">Hell Creek, Montana</text>
  <text x="80" y="149" text-anchor="middle" font-size="3.4" fill="#e9dfc6">Crétacé supérieur</text>
  <text x="80" y="156" text-anchor="middle" font-size="3" fill="#c9a24a" letter-spacing=".6">GRANDE GALERIE · TRAVÉE CENTRALE</text>''',
    fente="SALLES & PERSONNES", fente2="glissez la carte d'une salle,", fente3="ou celle d'une personne",
    plateau="LE SOCLE D'AUGUSTE", plateau2="posez ici un objet, à plat",
    plateau_dessin='<g fill="none" stroke="#c9a24a" stroke-width=".8"><path d="M-14 4c0-8 6-13 15-13 6 0 11 3 13 7l3 1-1 3-4 1c-1 3-4 5-8 5h-6l-2 4h-4l1-4c-4-1-6-2-6-4z"/><path d="M-2 -1h3M4-4l1 2"/><path d="M-8 6l1 3M-4 7l1 3M0 7l1 3M4 6l1 3"/></g>',
    couleur='<!-- le capteur de couleur : caché, une ammonite -->\n  <g transform="translate(358 107)" fill="none" stroke="#c9a24a" stroke-width=".6" opacity=".75"><path d="M0 0a2 2 0 0 1 2 2 4 4 0 0 1-4 4 6 6 0 0 1-6-6 8 8 0 0 1 8-8 10 10 0 0 1 10 10"/></g>',
    casque='<!-- le casque : caché -->\n  <text x="358" y="170" text-anchor="middle" font-size="3.4" fill="#c9a24a" opacity=".7">✦</text>',
    forge_enclume='''<!-- la forge et l'enclume : cachées, sous la vitrine des fossiles -->
  <rect x="38" y="168" width="90" height="30" rx="2" fill="none" stroke="#c9a24a" stroke-width=".5"/>
  <text x="83" y="176" text-anchor="middle" font-size="3.4" fill="#c9a24a" letter-spacing=".8">VITRINE DES FOSSILES</text>
  <g fill="none" stroke="#e9dfc6" stroke-width=".5" opacity=".8">
    <g transform="translate(55 187)"><path d="M0 0a2 2 0 0 1 2 2 4 4 0 0 1-4 4 6 6 0 0 1-6-6 8 8 0 0 1 8-8"/></g>
    <path d="M74 192c4-8 10-8 14 0M76 192v-3M80 192v-5M84 192v-3"/>
    <path d="M100 190l6-6 6 6-6 3z"/><path d="M106 184v9"/>
  </g>''',
    horloge="L'HORLOGE DE LA GALERIE",
    oeil='''<!-- l'œil : LE SOUPIRAIL, une lampe -->
  <rect x="222" y="170" width="26" height="20" rx="2" fill="none" stroke="#c9a24a" stroke-width="1"/>
  <g stroke="#c9a24a" stroke-width=".8"><path d="M228.5 170v20M235 170v20M241.5 170v20"/></g>
  <circle cx="235" cy="180" r="5" fill="#0f1a1b" stroke="#ffffff" stroke-width=".5" stroke-dasharray="1 .8"/>
  <text x="235" y="200" text-anchor="middle" font-size="4.6" fill="#c9a24a" letter-spacing=".5">LE SOUPIRAIL</text>
  <text x="235" y="205.5" text-anchor="middle" font-size="3.3" fill="#e9dfc6">une lampe de téléphone ici</text>''',
    boutons='''<!-- les boutons de couleur : cachés, sous la frise -->
  <g fill="none" stroke="#c9a24a" stroke-width=".5" opacity=".7">
    <path d="M20 222h150"/><path d="M20 218h150M20 226h150" stroke-dasharray="1 2"/>
    <g transform="translate(95 222)"><circle r="7"/><path d="M-4 0h8M0-4v8"/></g>
  </g>
  <text x="95" y="240" text-anchor="middle" font-size="3.4" fill="#c9a24a" font-style="italic">« La nature ne fait pas de sauts. »</text>''',
    molette="MOLETTE", molette2="appui : où en êtes-vous ?",
    gros="LA GRILLE", gros2="la fermer : appuyez",
    signature="Muséum national d'histoire naturelle · Jardin des Plantes · Paris",
    intro="Pour « Panique au Muséum », la façade aimantée habille le pupitre en <strong>vitrine du Muséum</strong>. Seuls les organes du tutoriel sont découverts, chacun sous le nom qu'il porte dans l'histoire : la <strong>fente</strong> pour les salles et les personnes, le <strong>socle d'Auguste</strong> (le lecteur à plat), le <strong>soupirail</strong> (l'œil, qu'on éclaire avec une lampe), le <strong>clavier du coffre</strong>, l'<strong>horloge</strong>, la <strong>grille</strong> (le gros bouton) et la molette. Le reste du pupitre dort sous la plaque.",
    table="".join([ligne("Salles &amp; personnes", "La fente", "Glisser la carte d'une salle pour s'y rendre, ou d'une personne pour lui parler"),
                   ligne("Le socle d'Auguste", "Le lecteur à plat", "Poser un objet à plat : le montrer, ou remettre le crâne"),
                   ligne("Le soupirail", "L'œil", "Y braquer la lampe d'un téléphone, à 10 cm, 2 secondes"),
                   ligne("Le coffre de la réserve", "Le clavier", "Taper le code, puis valider avec #"),
                   ligne("L'horloge de la galerie", "L'afficheur à 4 chiffres", "Le temps restant, et le code pendant la saisie"),
                   ligne("La grille", "Le gros bouton", "La fermer au nez du photographe"),
                   ligne("Molette", "La molette", "Un appui : les étapes de la mission")]),
    decoupes="écran, clavier, anneau, haut-parleur, fente, LED du lecteur à plat, horloge, soupirail (l'œil), molette et gros bouton. Le lecteur à plat ne se découpe pas",
)

# ---------------------------------------------------------------- Le Manoir Harrington
A, C, F = "#c8a45c", "#e6dcc4", "#0b1410"
H = dict(
    monde="Le Manoir Harrington", mission="Le Vol de l'Œil de Jade", h1="La plaque du poste de sécurité",
    fond1="#1f3a2c", fond2="#172b21", fond3="#0f1d16", accent=A, trait=C, fenetre=F, clair="#6f8a7a", bandeau="#3d7a5a",
    police="Playfair, serif", motif_pas=18,
    motif='<path d="M9 0 18 9 9 18 0 9Z M9 4 14 9 9 14 4 9Z" fill="none" stroke="#c8a45c" stroke-width=".15" opacity=".3"/>',
    titre="MANOIR HARRINGTON · POSTE DE SÉCURITÉ · CHUBB &amp; SONS · LONDON 1925",
    clavier="CLAVIER DE SÉCURITÉ", hp="TÉLÉPHONE DU YARD", anneau="LES GEMMES", ecran="le poste de sécurité : la pièce où vous êtes",
    cle='''<!-- l'interrupteur à clé : la DÉRIVATION -->
  <circle cx="358" cy="40" r="10" fill="none" stroke="#c8a45c" stroke-width="1"/>
  <circle cx="358" cy="40" r="6" fill="#0b1410" stroke="#ffffff" stroke-width=".5" stroke-dasharray="1.5 1"/>
  <text x="358" y="57" text-anchor="middle" font-size="4.2" fill="#c8a45c" letter-spacing=".4">DÉRIVATION</text>
  <text x="358" y="61.5" text-anchor="middle" font-size="2.9" fill="#e6dcc4">secteur · groupe</text>''',
    voyants='''<!-- les voyants : cachés, sous le plan des secteurs -->
  <rect x="31" y="108" width="98" height="56" rx="3" fill="none" stroke="#c8a45c" stroke-width=".8"/>
  <text x="80" y="115" text-anchor="middle" font-size="3.6" fill="#c8a45c" letter-spacing=".8">LES SECTEURS DU MANOIR</text>
  <g transform="translate(80 139)" fill="none" stroke="#e6dcc4" stroke-width=".5">
    <circle r="18"/><circle r="11" stroke-dasharray="1 1"/><path d="M0-21V21M-21 0H21"/>
    <path d="M0-18 3-3 18 0 3 3 0 18-3 3-18 0-3-3Z" fill="#c8a45c" fill-opacity=".25" stroke="#c8a45c"/>
  </g>
  <g font-size="3.6" fill="#c8a45c" text-anchor="middle"><text x="80" y="119">N</text><text x="80" y="163">S</text><text x="56" y="140">O</text><text x="104" y="140">E</text></g>''',
    fente="PIÈCES &amp; SUSPECTS", fente2="glissez la carte d'une pièce,", fente3="ou celle d'un suspect",
    plateau="PIÈCE À CONVICTION", plateau2="un objet ici, à plat",
    plateau_dessin='<g fill="none" stroke="#c8a45c" stroke-width=".7"><path d="M-10-4h20l-10 14z"/><path d="M-10-4l4-5h12l4 5M-6-9l2 5 4-5 4 5 2-5M-4-4l4 14 4-14"/></g>',
    couleur='''<!-- le capteur de couleur : LA LOUPE -->
  <circle cx="358" cy="107" r="10" fill="none" stroke="#c8a45c" stroke-width="1.2"/>
  <path d="M365 114l7 7" stroke="#c8a45c" stroke-width="2.4" stroke-linecap="round"/>
  <g stroke-width="1.6" fill="none"><path d="M351 101a9 9 0 0 1 7-3" stroke="#e5484d"/><path d="M358 98a9 9 0 0 1 7 3" stroke="#30a46c"/><path d="M365 101a9 9 0 0 1 2 6" stroke="#3e7bfa"/></g>
  <circle cx="358" cy="107" r="6" fill="#0b1410" stroke="#ffffff" stroke-width=".5" stroke-dasharray="1.5 1"/>
  <text x="352" y="126" text-anchor="middle" font-size="4.4" fill="#c8a45c" letter-spacing=".5">LA LOUPE</text>
  <text x="352" y="131" text-anchor="middle" font-size="3.1" fill="#e6dcc4">une gemme, à plat</text>''',
    casque='''<!-- le casque : la TSF -->
  <circle cx="358" cy="167" r="7" fill="none" stroke="#c8a45c" stroke-width="1"/>
  <circle cx="358" cy="167" r="3" fill="#0b1410" stroke="#ffffff" stroke-width=".5" stroke-dasharray="1 .8"/>
  <text x="358" y="181" text-anchor="middle" font-size="4.2" fill="#c8a45c">ÉCOUTEUR TSF</text>''',
    forge_enclume='''<!-- la forge et l'enclume : cachées, sous les armes des Harrington -->
  <g transform="translate(82 184)" fill="none" stroke="#c8a45c" stroke-width=".6">
    <path d="M-10-12h20v12c0 8-10 13-10 13s-10-5-10-13z"/><path d="M-10-3h20M0-12v25" stroke-width=".35"/>
    <circle cx="-5" cy="-7.5" r="2"/><circle cx="5" cy="5" r="2"/>
  </g>
  <text x="82" y="205" text-anchor="middle" font-size="3.2" fill="#c8a45c" font-style="italic">Fide et Vigilantia</text>''',
    horloge="L'HORODATEUR",
    oeil='''<!-- l'œil : caché, une rosace -->
  <g transform="translate(235 180)" fill="none" stroke="#c8a45c" stroke-width=".5" opacity=".7"><circle r="8"/><path d="M0-8 2-2 8 0 2 2 0 8-2 2-8 0-2-2Z"/></g>''',
    boutons='''<!-- les quatre boutons : L'ALARME -->
  <text x="95" y="249" text-anchor="middle" font-size="4.2" fill="#c8a45c" letter-spacing=".6">L'ALARME · CONFIRMER EN VERT</text>
  <g fill="none" stroke-width="2.4">
    <circle cx="35" cy="222" r="17" stroke="#e5484d"/><circle cx="75" cy="222" r="17" stroke="#3e7bfa"/>
    <circle cx="115" cy="222" r="17" stroke="#f5b83d"/><circle cx="155" cy="222" r="17" stroke="#30a46c"/>
  </g>
  <g fill="#0b1410" stroke="#ffffff" stroke-width=".5" stroke-dasharray="2 1.5"><circle cx="35" cy="222" r="15"/><circle cx="75" cy="222" r="15"/><circle cx="115" cy="222" r="15"/><circle cx="155" cy="222" r="15"/></g>''',
    molette="LE CADRAN", molette2="tourner : régler · appui : valider",
    gros="ACCUSER", gros2="dans le bureau",
    signature="Chubb &amp; Sons · Patent Safe and Lock Makers · St Paul's Churchyard",
    intro="Pour « Le Vol de l'Œil de Jade », la façade aimantée fait du pupitre le <strong>poste de sécurité du manoir Harrington</strong>. Chaque organe y porte son nom de mission : la <strong>loupe</strong> (le capteur de couleur) pour les gemmes, la <strong>dérivation</strong> (l'interrupteur à clé), le <strong>cadran</strong> (la molette), l'<strong>horodateur</strong> (l'horloge), l'<strong>écouteur TSF</strong> (le casque) et le bouton <strong>accuser</strong>.",
    table="".join([ligne("Pièces &amp; suspects", "La fente", "Glisser une pièce pour s'y rendre, ou un suspect pour l'interroger"),
                   ligne("Pièce à conviction", "Le lecteur à plat", "Poser un objet : le montrer au suspect, ou l'Œil de Jade pour accuser"),
                   ligne("La loupe", "Le capteur de couleur", "Poser les gemmes une à une, à plat"),
                   ligne("Dérivation", "L'interrupteur à clé", "Basculer sur le groupe électrogène : un quart de tour"),
                   ligne("Le cadran", "La molette", "Régler l'heure de l'incident, la longueur d'onde de la TSF"),
                   ligne("Clavier de sécurité", "Le clavier", "Les codes des portes, validés avec #"),
                   ligne("L'horodateur", "L'afficheur à 4 chiffres", "Le temps restant, l'heure et les codes pendant la saisie"),
                   ligne("Écouteur TSF", "Le casque", "La police, et un murmure"),
                   ligne("L'alarme", "Les 4 boutons", "Couper la sirène ; confirmer l'accusation au vert"),
                   ligne("Les gemmes", "L'anneau", "Un segment vert par gemme acceptée"),
                   ligne("Accuser", "Le gros bouton", "Dans le bureau : le suspect dans la fente, le jade sur le plateau")]),
    decoupes="écran, clavier, anneau, haut-parleur, clé, fente, LED du lecteur à plat, loupe (capteur de couleur), écouteur, horloge, quatre boutons, molette et gros bouton. Le lecteur à plat ne se découpe pas",
)


# ---------------------------------------------------------------- Les briques des organes
# Positions du gabarit commun (380 × 262 mm). Chaque organe a sa version visible (nom de mission,
# puis le nom courant de l'organe en petit) et sa version cachée (un décor, rien à découper).

def petit(x, y, texte, c, taille=3.1):
    return f'<text x="{x}" y="{y}" text-anchor="middle" font-size="{taille}" fill="{c}" opacity=".85">{texte}</text>'

def cle(a, c, f, nom, sous="interrupteur à clé"):
    return f'''<circle cx="358" cy="40" r="10" fill="none" stroke="{a}" stroke-width="1"/>
  {fenetre_rond(358, 40, 6, f)}
  <text x="358" y="57" text-anchor="middle" font-size="4.1" fill="{a}" letter-spacing=".4">{nom}</text>
  {petit(358, 61.5, sous, c, 2.9)}'''

def couleur(a, c, f, nom, sous="capteur de couleur"):
    return f'''<circle cx="358" cy="107" r="10" fill="none" stroke="{a}" stroke-width="1"/>
  <g stroke-width="2.2" fill="none"><path d="M358 95.5a11.5 11.5 0 0 1 11.5 11.5" stroke="#e5484d"/><path d="M369.5 107a11.5 11.5 0 0 1-11.5 11.5" stroke="#3e7bfa"/>
     <path d="M358 118.5a11.5 11.5 0 0 1-11.5-11.5" stroke="#30a46c"/><path d="M346.5 107a11.5 11.5 0 0 1 11.5-11.5" stroke="#f5b83d"/></g>
  {fenetre_rond(358, 107, 6, f)}
  <text x="358" y="126" text-anchor="middle" font-size="4.2" fill="{a}">{nom}</text>
  {petit(358, 131, sous, c)}'''

def casque(a, c, f, nom, sous="prise casque"):
    return f'''<circle cx="358" cy="167" r="7" fill="none" stroke="{a}" stroke-width="1"/>
  {fenetre_rond(358, 167, 3, f, "1 .8")}
  <text x="358" y="181" text-anchor="middle" font-size="4.1" fill="{a}">{nom}</text>
  {petit(358, 185.5, sous, c, 2.9)}'''

def forge(a, c, f, nom, sous="soufflez ici"):
    trous = "".join(f'<circle cx="{60 + dx}" cy="{183 + dy}" r="1"/>' for dx, dy in
                    [(0, 0), (-3.5, 0), (3.5, 0), (0, -3.5), (0, 3.5), (-2.5, -2.5), (2.5, -2.5), (-2.5, 2.5), (2.5, 2.5)])
    return f'''<text x="60" y="171.5" text-anchor="middle" font-size="4" fill="{a}">{nom}</text>
  <circle cx="60" cy="183" r="9" fill="none" stroke="{c}" stroke-width=".7"/>
  <g fill="{f}" stroke="#ffffff" stroke-width=".35" stroke-dasharray=".8 .6">{trous}</g>
  {petit(60, 197, sous, c)}'''

def oeil(a, c, f, nom, sous="la main à 5 cm, ou une lampe", fleches=True):
    f4 = f'<g fill="{a}"><path d="M235 164l-3 4h6z"/><path d="M235 196l-3-4h6z"/><path d="M219 180l4-3v6z"/><path d="M251 180l-4-3v6z"/></g>' if fleches else ""
    return f'''<circle cx="235" cy="180" r="8" fill="none" stroke="{c}" stroke-width=".9"/>
  {fenetre_rond(235, 180, 5, f, "1 .8")}
  {f4}
  <text x="235" y="204" text-anchor="middle" font-size="4.2" fill="{a}">{nom}</text>
  {petit(235, 209, sous, c)}'''

def voyants(a, c, f, titre, noms, legende):
    xs = [46.7, 63.3, 80, 96.7, 113.3]
    ronds = "".join(fenetre_rond(x, y, 3, f, "1 .8") for y in (116, 148) for x in xs)
    textes = "".join(f'<text x="{x}" y="{124 if i < 5 else 156}">{n}</text>' for i, (x, n) in enumerate(zip(xs * 2, noms)))
    return f'''<rect x="31" y="108" width="98" height="56" rx="3" fill="none" stroke="{c}" stroke-width=".6"/>
  <rect x="44" y="105.5" width="72" height="5" fill="#00000000"/>
  <text x="80" y="109.6" text-anchor="middle" font-size="4" fill="{a}" letter-spacing=".5">{titre}</text>
  {ronds}
  <g font-size="2.9" fill="{c}" text-anchor="middle">{textes}</g>
  {petit(80, 161.6, legende, c, 2.8)}'''

def boutons(a, f, titre):
    return f'''<text x="95" y="249" text-anchor="middle" font-size="4.1" fill="{a}" letter-spacing=".5">{titre}</text>
  <g fill="none" stroke-width="2.4">
    <circle cx="35" cy="222" r="17" stroke="#e5484d"/><circle cx="75" cy="222" r="17" stroke="#3e7bfa"/>
    <circle cx="115" cy="222" r="17" stroke="#f5b83d"/><circle cx="155" cy="222" r="17" stroke="#30a46c"/>
  </g>
  <g fill="{f}" stroke="#ffffff" stroke-width=".5" stroke-dasharray="2 1.5"><circle cx="35" cy="222" r="15"/><circle cx="75" cy="222" r="15"/><circle cx="115" cy="222" r="15"/><circle cx="155" cy="222" r="15"/></g>'''

def rosace(cx, cy, a, r=8):
    return (f'<g transform="translate({cx} {cy})" fill="none" stroke="{a}" stroke-width=".5" opacity=".7"><circle r="{r}"/>'
            f'<path d="M0-{r} {r/4}-{r/4} {r} 0 {r/4} {r/4} 0 {r}-{r/4} {r/4}-{r} 0-{r/4}-{r/4}Z"/></g>')

def cartouche(a, c, lignes, x=31, y=108, w=98, h=56):
    """Un cartouche décoratif (cache les voyants, la forge et l'enclume…)."""
    t = "".join(f'<text x="{x + w / 2}" y="{y + 12 + i * 8}" text-anchor="middle" font-size="{4.4 if i == 0 else 3.4}" '
                f'fill="{a if i == 0 else c}" letter-spacing="{1 if i == 0 else .3}">{l}</text>' for i, l in enumerate(lignes))
    return f'''<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="3" fill="none" stroke="{a}" stroke-width=".8"/>
  <rect x="{x + 3}" y="{y + 3}" width="{w - 6}" height="{h - 6}" rx="2" fill="none" stroke="{a}" stroke-width=".3"/>{t}'''

def fiche(*rangees):
    return "".join(ligne(*r) for r in rangees)


# ---------------------------------------------------------------- Monte-Carlo 1928
A, C, F = "#d4af37", "#efe4c6", "#0d0a12"
CASSE = dict(
    monde="Monte-Carlo 1928", mission="Le Casse du Siècle", h1="La plaque du Casino de la Riviera",
    fond1="#2a2233", fond2="#1b1622", fond3="#110d16", accent=A, trait=C, fenetre=F, clair="#7d6f8a", bandeau="#8a6d24",
    police="Playfair, serif", motif_pas=16,
    motif='<path d="M8 0 16 8 8 16 0 8Z" fill="none" stroke="#d4af37" stroke-width=".2" opacity=".35"/><path d="M8 5 11 8 8 11 5 8Z" fill="#d4af37" opacity=".12"/>',
    titre="CASINO DE LA RIVIERA · MONTE-CARLO · GALA DU 18 AOÛT 1928",
    clavier="LE STANDARD", hp="L'ORCHESTRE", anneau="LE SOUPÇON", ecran="l'écran : le salon où vous êtes",
    cle=rosace(358, 40, A),
    voyants=cartouche(A, C, ["LA ROULETTE", "rouge · noir · pair · impair", "faites vos jeux", "rien ne va plus", "table 7"]),
    fente="SALONS &amp; INVITÉS", fente2="glissez un salon, un invité,", fente3="ou votre carte Rôle",
    plateau="LE TAPIS VERT", plateau2="un objet, ou votre carte Rôle",
    plateau_dessin='<g fill="none" stroke="#d4af37" stroke-width=".7"><circle r="9"/><circle r="6" stroke-dasharray="1.5 1.5"/><path d="M-3 0h6M0-3v6"/></g>',
    couleur=rosace(358, 107, A, 9),
    casque=casque(A, C, F, "LE STÉTHOSCOPE", "casque : la Perceuse"),
    forge_enclume=cartouche(A, C, ["◆ ◇ ◆", "Les Années folles", "Monte-Carlo"], x=38, y=168, w=90, h=30),
    horloge="LA PENDULE",
    oeil=rosace(235, 180, A),
    boutons=boutons(A, F, "LES JETONS · réponses et discrétion"),
    molette="LE CADRAN DU COFFRE", molette2="molette : les disques, le carton",
    gros="LE COUP FINAL", gros2="gros bouton : avancer, accuser",
    signature="Société des Bains de Mer · Monte-Carlo · Principauté de Monaco",
    intro="Pour « Le Casse du Siècle », la façade fait du pupitre une <strong>table du Casino de la Riviera</strong>. Les quatre boutons deviennent <strong>les jetons</strong> (répondre aux questions, rejouer une suite, tenir ensemble), la molette <strong>le cadran du coffre</strong>, le casque <strong>le stéthoscope</strong> de la Perceuse, l'anneau <strong>le soupçon</strong> et le gros bouton <strong>le coup final</strong>. Les voyants, la clé, le capteur de couleur, la forge, l'enclume et l'œil restent sous la plaque.",
    table=fiche(("Salons &amp; invités", "La fente", "Glisser un salon pour s'y rendre, un invité pour l'approcher, votre carte Rôle"),
                ("Le tapis vert", "Le lecteur à plat", "Poser un objet, ou votre carte Rôle"),
                ("Les jetons", "Les 4 boutons", "Répondre au bluff, rejouer une suite, tenir ensemble"),
                ("Le cadran du coffre", "La molette", "Le numéro du carton, les trois disques du coffre"),
                ("Le standard", "Le clavier", "Taper un numéro"),
                ("Le stéthoscope", "Le casque", "La Perceuse écoute les disques"),
                ("Le soupçon", "L'anneau", "La méfiance du personnel : or, puis rouge"),
                ("La pendule", "L'afficheur", "23 h → minuit"),
                ("Le coup final", "Le gros bouton", "Avancer pendant les rondes, et accuser")),
    decoupes="écran, clavier, haut-parleur, anneau, fente, LED du lecteur à plat, prise casque, horloge, quatre boutons, molette et gros bouton. Le lecteur à plat ne se découpe pas",
)

# ---------------------------------------------------------------- Station Kepler-9
A, C, F = "#3fc1e8", "#cfe8f2", "#06101a"
KEPLER = dict(
    monde="Station Kepler-9", mission="Signal Perdu", h1="La plaque de la console de bord",
    fond1="#14283a", fond2="#0d1c2b", fond3="#07111b", accent=A, trait=C, fenetre=F, clair="#4f7590", bandeau="#1f7fa8",
    police="Share Tech Mono, monospace", motif_pas=10,
    motif='<path d="M10 0V10H0" fill="none" stroke="#3fc1e8" stroke-width=".12" opacity=".35"/>',
    titre="CONSORTIUM ARKAN · STATION KEPLER-9 · CONSOLE DE BORD · 2147",
    clavier="LE TERMINAL", hp="HÉLIOS", anneau="RÉSERVE D'O₂", ecran="l'écran : le module où vous êtes",
    cle=rosace(358, 40, A),
    voyants=cartouche(A, C, ["STATION KEPLER-9", "6 modules · 1 noyau", "orbite de Kepler-9 b", "équipage : 6", "statut : silence radio"]),
    fente="MODULES &amp; ÉQUIPAGE", fente2="un module, un membre d'équipage,", fente3="ou votre carte Rôle",
    plateau="LE SCANNER", plateau2="un objet à analyser, ou votre Rôle",
    plateau_dessin='<g fill="none" stroke="#3fc1e8" stroke-width=".7"><rect x="-10" y="-8" width="20" height="16" rx="2"/><path d="M-10 0h20" stroke-dasharray="2 1"/><circle r="3"/></g>',
    couleur=rosace(358, 107, A, 9),
    casque=casque(A, C, F, "CASQUE RADIO", "balises, canal caché"),
    forge_enclume=cartouche(A, C, ["ALBATROS", "amarré au sas", "contrôle mission"], x=38, y=168, w=90, h=30),
    horloge="O₂ RESTANT",
    oeil=rosace(235, 180, A),
    boutons=boutons(A, F, "LES COMMANDES · choix, avaries"),
    molette="LE RÉGULATEUR", molette2="molette : énergie, lettres, codes",
    gros="RAPPORT FINAL", gros2="gros bouton",
    signature="Consortium Arkan · division minière · station de classe Pallas",
    intro="Pour « Signal Perdu », la façade fait du pupitre la <strong>console de bord de Kepler-9</strong>. Le clavier devient <strong>le terminal</strong>, la molette <strong>le régulateur</strong> (énergie, lettres, codes), le lecteur à plat <strong>le scanner</strong>, le casque <strong>le casque radio</strong>, l'anneau et l'horloge la <strong>réserve d'oxygène</strong>, et le gros bouton <strong>le rapport final</strong>. Les voyants, la clé, le capteur de couleur, la forge, l'enclume et l'œil restent sous la plaque.",
    table=fiche(("Modules &amp; équipage", "La fente", "Un module pour s'y rendre, un membre d'équipage pour son journal, votre Rôle"),
                ("Le scanner", "Le lecteur à plat", "Un objet à analyser, ou votre carte Rôle"),
                ("Le terminal", "Le clavier", "Les codes d'accès, le mot de passe"),
                ("Le régulateur", "La molette", "Répartir l'énergie, régler des lettres"),
                ("Les commandes", "Les 4 boutons", "Les choix, les avaries, une suite à rejouer"),
                ("Casque radio", "Le casque", "La balise morse, le canal caché, le traître"),
                ("Réserve d'O₂", "L'anneau et l'horloge", "L'oxygène qui reste"),
                ("Rapport final", "Le gros bouton", "L'accusation")),
    decoupes="écran, clavier, haut-parleur, anneau, fente, LED du lecteur à plat, prise casque, horloge, quatre boutons, molette et gros bouton. Le lecteur à plat ne se découpe pas",
)

# ---------------------------------------------------------------- Brigade de déminage
A, C, F = "#f2b134", "#ece9e2", "#0b0c0f"
_hachures = '<pattern id="hachures" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="4" height="8" fill="#f2b134"/><rect x="4" width="4" height="8" fill="#16181c"/></pattern>'
DEMINAGE = dict(
    monde="Brigade de déminage", mission="Désamorçage", h1="La plaque de la bombe",
    fond1="#2a2d33", fond2="#1d1f24", fond3="#131418", accent=A, trait=C, fenetre=F, clair="#6b6f78", bandeau="#a8721a",
    police="Share Tech Mono, monospace", motif_pas=6,
    motif='<circle cx="3" cy="3" r=".4" fill="#ece9e2" opacity=".12"/>',
    titre="⚠ ENGIN EXPLOSIF · NE PAS DÉPLACER · BRIGADE DE DÉMINAGE ⚠",
    clavier="CLAVIER", hp="LE QG", anneau="COMPTE À REBOURS", ecran="l'écran : n° de série, modules, consignes",
    cle=cle(A, C, F, "SÉCURITÉ FINALE", "la clé, 15 s"),
    voyants=voyants(A, C, F, "FILS 1 → 5 · MODULES", ["FIL 1", "FIL 2", "FIL 3", "FIL 4", "FIL 5", "MOD.", "MOD.", "MOD.", "MOD.", "MOD."],
                    "en haut : les fils · en bas : désamorcés"),
    fente="MODULES", fente2="glissez la carte d'un module", fente3="et laissez-la pendant l'essai",
    lecteur='''<!-- le lecteur à plat : caché, sous la plaque constructeur -->
  <rect x="257" y="105" width="76" height="70" rx="3" fill="#00000033" stroke="#f2b134" stroke-width=".8"/>
  <rect x="257" y="105" width="76" height="6" fill="url(#hachures)"/>
  <g font-size="3.6" fill="#ece9e2" text-anchor="middle"><text x="295" y="124">MODÈLE  XB-12</text><text x="295" y="132">CHARGE : 4 KG</text>
  <text x="295" y="140">MODULES : 5 / 8</text><text x="295" y="148">MINUTERIE : 12:00</text></g>
  <text x="295" y="166" text-anchor="middle" font-size="4" fill="#f2b134" letter-spacing=".8">NE PAS OUVRIR</text>''',
    plateau="", plateau2="", plateau_dessin="",
    couleur=couleur(A, C, F, "LE NUANCIER", "une pastille à plat"),
    casque=casque(A, C, F, "LA RADIO", "casque : le mot de code"),
    forge_enclume=forge(A, C, F, "REFROIDISSEUR", "soufflez ici") + '''
  <!-- l'enclume : cachée -->
  <g transform="translate(105 183)" fill="none" stroke="#f2b134" stroke-width=".7"><path d="M0-9 9 7H-9Z"/><path d="M0-3v5" stroke-width="1.2"/><circle cy="4.5" r=".7" fill="#f2b134"/></g>''',
    horloge="LA MINUTERIE",
    oeil=oeil(A, C, F, "LE VISEUR", "la main à plat, à 5 cm"),
    boutons=boutons(A, F, "DÉTONATEUR · SURCHAUFFE"),
    molette="TABLEAU DE BORD", molette2="appui : n° de série, modules, temps",
    gros="STABILISATEUR", gros2="gros bouton : rythme, nuancier",
    signature="Brigade de déminage · ne rien toucher sans l'ordre des experts",
    intro="Pour « Désamorçage », la façade fait du pupitre <strong>la bombe</strong>. Chaque organe y porte le nom de son module : la forge devient <strong>le refroidisseur</strong> (Surchauffe), l'œil <strong>le viseur</strong>, le capteur de couleur <strong>le nuancier</strong>, l'interrupteur à clé <strong>la sécurité finale</strong>, le gros bouton <strong>le stabilisateur</strong>, le casque <strong>la radio</strong>. Le lecteur à plat et l'enclume ne servent pas : la plaque les cache.",
    table=fiche(("Modules", "La fente", "La carte d'un module, laissée en place pendant l'essai"),
                ("Fils · modules", "Les 10 voyants", "En haut : les 5 fils ; en bas : les modules désamorcés"),
                ("Clavier", "Le clavier", "Le numéro d'un fil, un code, une fréquence"),
                ("Détonateur · surchauffe", "Les 4 boutons", "Le bouton à presser"),
                ("Refroidisseur", "La forge", "Souffler, bouche grande ouverte, 2 s"),
                ("Le viseur", "L'œil", "La main à plat, à 5 cm, deux gestes"),
                ("Le nuancier", "Le capteur de couleur", "La pastille demandée, à plat"),
                ("La radio", "Le casque", "Le mot de code du QG"),
                ("Stabilisateur", "Le gros bouton", "Le rythme, et la validation du nuancier"),
                ("Sécurité finale", "L'interrupteur à clé", "À tourner dans les 15 secondes"),
                ("La minuterie · compte à rebours", "L'horloge et l'anneau", "Le temps qui reste"),
                ("Tableau de bord", "La molette", "Un appui : le numéro de série et les modules")),
    decoupes="écran, clavier, haut-parleur, anneau, clé, voyants, fente, nuancier, prise casque, grille du refroidisseur, horloge, viseur, quatre boutons, molette et gros bouton. Le lecteur à plat et l'enclume restent couverts",
)

# ---------------------------------------------------------------- Le Manoir Blackwood
A, C, F = "#c79a4a", "#eadfca", "#0d0908"
BLACKWOOD = dict(
    monde="Le Manoir Blackwood", mission="La dernière nuit de Lord Edgar", h1="La plaque du manoir",
    fond1="#2a1f1b", fond2="#1c1513", fond3="#120d0b", accent=A, trait=C, fenetre=F, clair="#7a6a5a", bandeau="#8a5a24",
    police="Playfair, serif", motif_pas=20,
    motif='<path d="M10 2c3 3 3 5 0 8-3-3-3-5 0-8z" fill="#c79a4a" opacity=".08"/>',
    titre="MANOIR BLACKWOOD · DARTMOOR · NUIT DU 31 OCTOBRE 1893",
    clavier="LE CADENAS", hp="LES VOIX", anneau="LA LAMPE", ecran="l'écran : la pièce où vous êtes",
    cle=rosace(358, 40, A),
    voyants=voyants(A, C, F, "LES PISTES · LA LAMPE", ["BIBLIO.", "GALERIE", "CHAPELLE", "BUREAU", "DOCTEUR", "", "", "", "", ""],
                    "en haut : les pistes · en bas : la lampe"),
    fente="LIEUX &amp; PERSONNES", fente2="glissez un lieu, un habitant,", fente3="ou votre carte Rôle",
    plateau="PIÈCES À CONVICTION", plateau2="un objet, ou votre carte Rôle",
    plateau_dessin='<g fill="none" stroke="#c79a4a" stroke-width=".7"><path d="M-4-10h8M-3-10v6l-6 12h18l-6-12v-6"/><path d="M-6 3h12"/></g>',
    couleur=rosace(358, 107, A, 9),
    casque=casque(A, C, F, "LE CORNET", "casque : murmures, alibis"),
    forge_enclume=forge(A, C, F, "PLAQUE FUNÉRAIRE", "soufflez la poussière") + "\n  " + rosace(105, 183, A, 8),
    horloge="LA PENDULE",
    oeil=oeil(A, C, F, "LE GUÉRIDON", "la main à plat, à 5 cm"),
    boutons=boutons(A, F, "LES SONNETTES · le BLEU remonte la lampe"),
    molette="MOLETTE", molette2="appui : vos pistes",
    gros="ACCUSER", gros2="gros bouton",
    signature="Blackwood Hall · Dartmoor · Devonshire",
    intro="Pour « La dernière nuit de Lord Edgar », la façade fait du pupitre <strong>le manoir Blackwood</strong>. La forge devient <strong>la plaque funéraire</strong> de la chapelle (on y souffle la poussière), l'œil <strong>le guéridon</strong> de la séance, le clavier <strong>le cadenas</strong> du bureau, le casque <strong>le cornet</strong>, et le bouton bleu <strong>remonte la lampe</strong>. La clé, le capteur de couleur et l'enclume restent sous la plaque.",
    table=fiche(("Lieux &amp; personnes", "La fente", "Un lieu pour s'y rendre, un habitant à interroger, votre carte Rôle"),
                ("Pièces à conviction", "Le lecteur à plat", "Un objet à montrer, ou l'arme pour accuser"),
                ("Les sonnettes", "Les 4 boutons", "Le BLEU remonte la lampe ; les choix et les imprévus"),
                ("Les pistes · la lampe", "Les 10 voyants et l'anneau", "En haut, les pistes ; en bas et sur l'anneau, la lumière"),
                ("Plaque funéraire", "La forge", "Souffler la poussière, bouche ouverte, 2 s"),
                ("Le guéridon", "L'œil", "Tracer le pentacle, la main à plat, à 5 cm"),
                ("Le cadenas", "Le clavier", "Le code du secrétaire"),
                ("La pendule", "L'horloge", "L'heure du manoir, et le code"),
                ("Le cornet", "Le casque", "Les alibis, les confidences, un murmure"),
                ("Accuser", "Le gros bouton", "Le coupable, le lieu, l'arme")),
    decoupes="écran, clavier, haut-parleur, anneau, voyants, fente, LED du lecteur à plat, prise casque, grille de la plaque funéraire, horloge, guéridon (l'œil), quatre boutons, molette et gros bouton. Le lecteur à plat ne se découpe pas",
)

PLAQUES = {"museum": M, "harrington": H, "casse": CASSE, "kepler": KEPLER, "deminage": DEMINAGE, "blackwood": BLACKWOOD}


if __name__ == "__main__":
    for monde in sys.argv[1:] or PLAQUES:
        T = PLAQUES[monde]
        html = page(T)
        if monde == "deminage":  # les hachures du cache
            html = html.replace('<g id="rivet">', _hachures + '\n    <g id="rivet">', 1)
        (JEU / "mondes" / monde / "impression" / "plateau.html").write_text(html, encoding="utf-8")
        print(f"✓ mondes/{monde}/impression/plateau.html")
