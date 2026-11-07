# Génère plaque-a.html : le plan trou par trou de la plaque A (l'ESP32), recto et verso,
# avec la liste de toutes les soudures, étape par étape, et une vérification automatique :
# chaque liaison relie bien ce qu'elle doit relier, et aucun fil nu ne touche un autre signal.
#
#   python3 plaque_a.py            (écrit plaque-a.html à côté de ce fichier)
#
# La plaque : 50 × 70 mm, 18 × 24 trous, pastilles des deux côtés. On la tient à l'horizontale,
# les deux bandes de pastilles allongées à gauche et à droite (elles ne servent pas).
# Coordonnées : colonnes 1 à 24 de gauche à droite VUES DU RECTO, rangées A à R de haut en bas.
import sys
from pathlib import Path

from cablage_modules import STYLE as STYLE_MODULES, table_modules

ICI = Path(__file__).parent
RANGS = "ABCDEFGHIJKLMNOPQR"
NC, NR = 24, 18


def h(ref):
    """« D15 » → (3, 15)."""
    return RANGS.index(ref[0]), int(ref[1:])


def nom(t):
    return f"{RANGS[t[0]]}{t[1]}"


# --------------------------------------------------------------------------------------------
# Les composants : chaque broche, son trou et son signal
# --------------------------------------------------------------------------------------------
pads = {}       # trou → (composant, broche, signal)
composants = []  # (id, nom, type, liste des trous, étape)


def poser(cid, titre, genre, rang, col0, signaux, sens=1, vertical=False, etape=1, noms=None):
    trous = []
    for k, s in enumerate(signaux):
        t = (rang + k * sens, col0) if vertical else (rang, col0 + k * sens)
        if s is None:
            continue
        assert t not in pads, f"{cid} : trou {nom(t)} déjà pris par {pads[t]}"
        pads[t] = (cid, noms[k] if noms else str(k + 1), s)
        trous.append(t)
    composants.append((cid, titre, genre, trous, etape))


HAUT = ["MOSI", "G22", "TX0", "RX0", "G21", "MISO", "SCK", "G5", "G17", "G16", "G4", "G2", "G15", "GND", "3V3"]
HAUT_N = ["23", "22", "TX0", "RX0", "21", "19", "18", "5", "17", "16", "4", "2", "15", "GND", "3V3"]
BAS = ["EN", "G36", "G39", "G34", "G35", "G32", "G33", "G25", "G26", "G27", "G14", "G12", "G13", "GND", "5V"]
BAS_N = ["EN", "36", "39", "34", "35", "32", "33", "25", "26", "27", "14", "12", "13", "GND", "VIN"]
# TX0 et RX0 ne vont nulle part : on les garde comme signaux distincts pour la vérification.
poser("ESP-H", "ESP32, rangée du haut", "femelle", 3, 1, HAUT, noms=HAUT_N)
poser("ESP-B", "ESP32, rangée du bas", "femelle", 13, 1, BAS, noms=BAS_N)
# La plaque A ne porte que les signaux propres à chaque module : tout ce qui est partagé
# (5 V, masse, 3,3 V, MOSI, SCK, MISO) part vers la plaque D par J16, et chaque module y prend
# ses fils communs. Ainsi chaque broche de l'ESP32 ne reçoit qu'un seul fil.
poser("J16", "J16 · vers la plaque D", "male", 0, 18, ["MOSI", "SCK", "MISO", "3V3", "5V", "GND"],
      noms=["MOSI", "SCK", "MISO", "3,3 V", "5 V", "GND"], etape=1)
poser("J2", "J2 · écran (sélections et commandes)", "male", 0, 2, ["G5", "EN", "G21", "G12", "G4", "G14"],
      noms=["CS", "RESET", "DC", "LED", "SD_CS", "T_CS"], etape=2)
poser("J3", "J3 · sélection des lecteurs RFID", "male", 0, 9, ["G13", "G22", "G15"],
      noms=["fente", "à plat", "réserve"], etape=3)
poser("J14", "J14 · vers la plaque B (le son)", "male", 0, 13, ["G17", "G16", "G2", "G36"],
      noms=["3 (17)", "4 (16)", "5 (2)", "6 (36)"], etape=4)
poser("J15", "J15 · vers la plaque C (I2C)", "male", 15, 9, ["G26", "G27"], noms=["SDA", "SCL"], etape=5)
poser("J8", "J8 · commandes", "male", 15, 4, ["G34", "G35", "G32", "G33", "G39"],
      noms=["CLK", "DT", "SW", "clé", "bouton"], etape=6)
poser("R4", "R4 · 10 kΩ (gros bouton)", "resistance", 16, 8, ["G39", None, None, None, "3V3"], etape=6)
poser("J7", "J7 · données des LED", "male", 15, 18, ["DIN"], noms=["DIN"], etape=7)
poser("R3", "R3 · 330 Ω (LED)", "resistance", 16, 14, ["G25", None, None, None, "DIN"], etape=7)

# --------------------------------------------------------------------------------------------
# Le verso : fils nus (rails, barres de distribution, ponts) et fils isolés
# --------------------------------------------------------------------------------------------
nus = []    # (signal, [trous dans l'ordre], étape, titre)


def nu(signal, chemin, etape, titre):
    trous = [h(x) for x in chemin.split()]
    full = [trous[0]]
    for a, b in zip(trous, trous[1:]):
        assert a[0] == b[0] or a[1] == b[1], f"{titre} : {nom(a)}→{nom(b)} n'est pas droit"
        dr = (b[0] > a[0]) - (b[0] < a[0])
        dc = (b[1] > a[1]) - (b[1] < a[1])
        t = a
        while t != b:
            t = (t[0] + dr, t[1] + dc)
            full.append(t)
    nus.append((signal, full, etape, titre))


fils = []   # (n°, signal, de, à, étape, rôle, épais)
_n = [0]


def fil(signal, a, b, etape, role, epais=False):
    _n[0] += 1
    fils.append((_n[0], signal, h(a), h(b), etape, role, epais))


# Aucun fil nu : chaque liaison est un fil isolé. Un signal qui va à plusieurs broches (la
# masse, le 5 V, le 3,3 V, les lignes 18, 19 et 23 du bus SPI) passe de broche en broche, en
# GUIRLANDE : chaque broche reçoit au plus deux fils, celui qui arrive et celui qui repart.
# Les guirlandes se construisent étape par étape : un nouveau connecteur se raccroche à la
# broche la plus proche du même signal déjà câblée qui a encore une place libre.
SOURCES = {"5V": h("A22"), "GND": h("A23"), "3V3": h("D15")}
SANS_FIL = {h("N14")}           # le GND du bas de l'ESP32 : relié à D14 dans la carte elle-même
EPAIS = {"5V", "GND"}
etape_pad = {t: et for _, _, _, trous, et in composants for t in trous}


def nom_broche(t):
    c, b, _ = pads[t]
    if c.startswith("ESP"):
        return f"broche {b}" if b not in ("GND", "3V3", "VIN", "EN") else b
    if c in ("R3", "R4", "C1"):
        return f"{c} ({nom(t)})"
    return f"{c} {b}"


def guirlandes():
    par_signal = {}
    for t, (c, b, s) in pads.items():
        if t in SANS_FIL or s in ("TX0", "RX0"):
            continue
        par_signal.setdefault(s, []).append(t)
    degre = {}
    for e in sorted(ETAPES_NUM):
        for s, trous in sorted(par_signal.items()):
            if s in SOURCES:
                source = SOURCES[s]
            else:
                esp = [t for t in trous if pads[t][0].startswith("ESP")]
                source = esp[0] if esp else min(trous, key=lambda t: (etape_pad[t], t))
            relies = {t for t in trous if etape_pad[t] < e} | {source}
            relies = {t for t in relies if etape_pad[t] <= e}
            nouveaux = [t for t in trous if etape_pad[t] == e and t not in relies]
            while nouveaux:
                libres = [t for t in relies if degre.get(t, 0) < (1 if pads[t][0].startswith("ESP") and s not in SOURCES else 2)]
                if not libres:
                    libres = [t for t in relies if degre.get(t, 0) < 2]
                d, a, b = min((abs(a[0] - b[0]) + abs(a[1] - b[1]), a, b) for a in libres for b in nouveaux)
                fil(s, nom(a), nom(b), e, f"{nom_broche(a)} → {nom_broche(b)}", s in EPAIS)
                degre[a] = degre.get(a, 0) + 1
                degre[b] = degre.get(b, 0) + 1
                relies.add(b)
                nouveaux.remove(b)


ETAPES_NUM = range(1, 8)
guirlandes()

INTERDITS_EN_PLUS = {(12, c) for c in range(1, 16)}  # rangée M : la variante d'ESP32 étroit y met ses broches
# Chaque bout de fil isolé a son propre trou : un trou libre voisin de sa cible, où le fil
# entre par le verso (il dépasse de 1 mm au recto, plié), relié à la cible par un pont. Un fil
# ainsi tenu par son trou ne tire jamais sur la soudure de la broche. S'il n'y a aucun trou
# libre à côté, le bout dénudé s'enroule d'un demi-tour autour de la broche avant d'être soudé.
INTERDITS = set(INTERDITS_EN_PLUS)   # sous le corps des borniers : rien ne doit dépasser au recto
for _cid, _t, _genre, _trous, _e in composants:
    if _genre == "bornier":
        _rs = [t[0] for t in _trous]
        _cs = [t[1] for t in _trous]
        _r = range(min(_rs) - 1, max(_rs) + 2)
        _c = range(min(_cs) - 1, max(_cs) + 2)
        INTERDITS.update((r, c) for r in _r for c in _c)
entrees = {}   # (n° du fil, 0 ou 1) → ("trou", entrée, cible) ou ("enroule", cible, cible)
VOISINS = ((0, 1), (0, -1), (1, 0), (-1, 0))


def placer_entrees():
    occ = set(pads)
    reseau = {t: p[2] for t, p in pads.items()}
    for s, tr, _, _ in nus:
        occ.update(tr)
        for t in tr:
            reseau.setdefault(t, s)
    nouveaux = []
    for n, sig, a, b, et, role, ep in fils:
        bouts = []
        for k, (t, autre) in enumerate(((a, b), (b, a))):
            cands = []
            for dr, dc in VOISINS:
                u = (t[0] + dr, t[1] + dc)
                if not (0 <= u[0] < NR and 1 <= u[1] <= NC) or u in occ or u in INTERDITS:
                    continue
                gene = sum(1 for d2 in VOISINS if (u[0] + d2[0], u[1] + d2[1]) != t
                           and reseau.get((u[0] + d2[0], u[1] + d2[1])) not in (None, sig))
                cands.append((gene * 3 + abs(u[0] - autre[0]) + abs(u[1] - autre[1]), u))
            if cands:
                u = min(cands)[1]
                occ.add(u)
                reseau[u] = sig
                nus.append((sig, [u, t], et, f"queue du fil {n}"))
                entrees[(n, k)] = ("trou", u, t)
                bouts.append(u)
            else:
                assert t in occ, f"fil {n} : aucun trou libre à côté de {nom(t)}, ni broche ni fil nu où s'enrouler"
                entrees[(n, k)] = ("enroule" if t in pads else "rail", t, t)
                bouts.append(t)
        nouveaux.append((n, sig, bouts[0], bouts[1], et, role, ep))
    fils[:] = nouveaux


placer_entrees()


def bout_txt(n, k):
    genre, u, t = entrees[(n, k)]
    if genre == "trou":
        return f"{nom(t)} <span class='g'>(entre en {nom(u)})</span>"
    if genre == "rail":
        return f"{nom(t)} <span class='g'>(enroulé sur le fil nu)</span>"
    return f"{nom(t)} <span class='g'>(enroulé sur la broche)</span>"


ETAPES = {
    1: ("L'ESP32 et la liaison vers D", "Les barrettes femelles de l'ESP32, J16 et ses six fils : la plaque D doit être faite avant."),
    2: ("L'écran et le tactile", "J2 et ses six fils."),
    3: ("Les lecteurs RFID", "J3 et ses trois fils."),
    4: ("Le son (vers la plaque B)", "J14 et ses quatre fils."),
    5: ("Le bus I2C (vers la plaque C)", "J15 et ses deux fils."),
    6: ("La molette, la clé et le gros bouton", "J8, R4 et leurs fils."),
    7: ("Les LED", "J7, R3 et leurs fils."),
}
TESTS = {
    1: ["<b>Sans l'ESP32</b> : branchez J16 sur la plaque D (six fils Dupont, chacun sur son rail) et le 5 V sur J1 de D. Entre VIN (+) et GND (−) de la barrette : 5 V.",
        "Coupez, enfoncez l'ESP32 (USB à droite), rallumez : sa LED rouge s'allume ; entre 3V3 et GND : 3,3 V. Le journal série affiche « Le Pupitre des Énigmes »."],
    2: ["Branchez l'écran : CS, RESET, DC, LED, SD_CS et T_CS sur J2 ; ses autres fils sur les rails de D (tableau « Les câbles des modules »).",
        "Le logo, puis le menu ; le doigt sur la dalle fait réagir les menus. Avec la carte SD : le menu des mondes."],
    3: ["Branchez les deux RC522 : SDA sur J3 (la fente sur J3-1, le lecteur à plat sur J3-2), le reste sur D.",
        "Le journal série affiche leur version (0x91 ou 0x92, et non 0xFF) ; une carte passée dans la fente est lue."],
    4: ["Câble vers la plaque B : ses broches 3 à 6 sur J14, ses broches 1 (5 V) et 2 (masse) sur les rails de D.",
        "Le son d'accueil sort du haut-parleur."],
    5: ["Câble vers la plaque C : SDA et SCL sur J15, le 3,3 V et la masse sur D.",
        "Le journal série liste les modules I2C trouvés (0x20, 0x21…)."],
    6: ["Branchez la molette (CLK, DT, SW sur J8 ; + et GND sur D), la clé et le gros bouton (un fil sur J8, l'autre sur la masse de D).",
        "Le menu « Tester le pupitre » les voit."],
    7: ["Branchez DIN de l'anneau sur J7, son 5 V et sa masse sur le bornier J12 de D.",
        "L'anneau s'allume au démarrage."],
}

# --------------------------------------------------------------------------------------------
# La vérification
# --------------------------------------------------------------------------------------------
def verifier():
    erreurs = []
    reseau = {t: p[2] for t, p in pads.items()}
    for s, trous, _, titre in nus:
        for t in trous:
            if t in reseau and reseau[t] != s:
                erreurs.append(f"{titre} : le fil nu passe par {nom(t)}, qui est {reseau[t]}")
            reseau.setdefault(t, s)
    parent = {}

    def trouve(x):
        parent.setdefault(x, x)
        while parent[x] != x:
            parent[x] = parent[parent[x]]
            x = parent[x]
        return x

    def unir(a, b):
        parent[trouve(a)] = trouve(b)

    for s, trous, _, _ in nus:
        for a, b in zip(trous, trous[1:]):
            unir(a, b)
    bouts = {}
    for n, s, a, b, *_ in fils:
        for t in (a, b):
            if reseau.get(t) != s:
                erreurs.append(f"fil {n} : le bout {nom(t)} est {reseau.get(t)}, pas {s}")
            bouts[t] = bouts.get(t, 0) + 1
            # deux fils au plus sur une même broche (enroulés l'un après l'autre), jamais trois
            if bouts[t] > (2 if t in pads else 1):
                erreurs.append(f"fil {n} : trop de fils soudés sur {nom(t)}")
        unir(a, b)
    # Les deux GND de l'ESP32 sont reliés dans la carte elle-même.
    unir(h("D14"), h("N14"))
    groupes = {}
    for t, s in reseau.items():
        groupes.setdefault(s, set()).add(trouve(t))
    for s, g in groupes.items():
        if s in ("TX0", "RX0"):
            continue
        if len(g) > 1:
            erreurs.append(f"le signal {s} est coupé en {len(g)} morceaux")
    racines = {}
    for t, s in reseau.items():
        r = trouve(t)
        if racines.setdefault(r, s) != s:
            erreurs.append(f"court-circuit entre {racines[r]} et {s} (vers {nom(t)})")
    # Chaque broche de connecteur doit être reliée à quelque chose (sauf TX0, RX0 et la GND du bas).
    for t, (c, b, s) in pads.items():
        seul = all(trouve(t) != trouve(u) for u in reseau if u != t)
        if seul and s not in ("TX0", "RX0"):
            erreurs.append(f"{c}-{b} ({nom(t)}, {s}) n'est relié à rien")
    return erreurs, reseau


# --------------------------------------------------------------------------------------------
# Les dessins
# --------------------------------------------------------------------------------------------
P = 10.0                 # un pas de 2,54 mm
LARGE, HAUTE = 70 / 2.54 * P, 50 / 2.54 * P
X0 = (LARGE - (NC - 1) * P) / 2
Y0 = (HAUTE - (NR - 1) * P) / 2
MARGE = 16

COULEURS = {"5V": "#d23b2e", "GND": "#222", "3V3": "#f08a24", "MOSI": "#2e6fd1", "MISO": "#2f9a4a", "SCK": "#8a5a2b",
            "DIN": "#c2185b"}
AUTRE = "#7b4bb3"


def coul(s):
    return COULEURS.get(s, AUTRE)


def xy(t, miroir=False):
    r, c = t
    cc = (NC + 1 - c) if miroir else c
    return MARGE + X0 + (cc - 1) * P, MARGE + Y0 + r * P


def base(miroir):
    s = [f'<rect x="{MARGE}" y="{MARGE}" width="{LARGE:.1f}" height="{HAUTE:.1f}" rx="5" fill="#2f6b3c"/>']
    for x in (MARGE + 7, MARGE + LARGE - 7):
        for k in range(NR):
            y = MARGE + Y0 + k * P
            s.append(f'<rect x="{x - 3.4:.1f}" y="{y - 1.6:.1f}" width="6.8" height="3.2" rx="1.6" fill="#b9b2a2" opacity=".5"/>')
    for x in (MARGE + 6, MARGE + LARGE - 6):
        for y in (MARGE + 6, MARGE + HAUTE - 6):
            s.append(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="4" fill="#fff" stroke="#173d22" stroke-width=".8"/>')
    for r in range(NR):
        for c in range(1, NC + 1):
            x, y = xy((r, c), miroir)
            s.append(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="3.4" fill="#d9c89a"/><circle cx="{x:.1f}" cy="{y:.1f}" r="1.5" fill="#173d22"/>')
    # les repères
    for c in range(1, NC + 1):
        x, _ = xy((0, c), miroir)
        for y in (MARGE - 5, MARGE + HAUTE + 11):
            s.append(f'<text x="{x:.1f}" y="{y:.1f}" font-size="6.5" text-anchor="middle" fill="#6d665d" font-weight="700">{c}</text>')
    for r in range(NR):
        _, y = xy((r, 1), miroir)
        for x in (MARGE - 7, MARGE + LARGE + 7):
            s.append(f'<text x="{x:.1f}" y="{y + 2.3:.1f}" font-size="6.5" text-anchor="middle" fill="#6d665d" font-weight="700">{RANGS[r]}</text>')
    return s


def recto():
    s = base(False)
    # L'ESP32 en transparence : sa carte déborde à gauche (antenne), son USB est à droite.
    xa, ya = xy((3, 1))
    xb, yb = xy((13, 15))
    s.append(f'<rect x="{xa - 3.6 * P:.1f}" y="{ya - 1.45 / 2.54 * P:.1f}" width="{xb - xa + 6.35 * P:.1f}" '
             f'height="{yb - ya + 2.9 / 2.54 * P:.1f}" rx="2" fill="#1b2a3a" opacity=".10" stroke="#1b2a3a" stroke-width=".8" stroke-dasharray="3 2"/>')
    s.append(f'<rect x="{xb + 2.2 * P:.1f}" y="{(ya + yb) / 2 - 4.5:.1f}" width="9" height="9" rx="1.5" fill="#c0c0c0" stroke="#666" stroke-width=".5"/>')
    s.append(f'<text x="{(xa + xb) / 2:.1f}" y="{(ya + yb) / 2 - 8:.1f}" font-size="9" text-anchor="middle" fill="#fff" font-weight="700" paint-order="stroke" stroke="#1b2a3a" stroke-width="2.5">ESP32, posé sur ses barrettes femelles</text>')
    s.append(f'<text x="{(xa + xb) / 2:.1f}" y="{(ya + yb) / 2 + 4:.1f}" font-size="6.5" text-anchor="middle" fill="#fff" paint-order="stroke" stroke="#1b2a3a" stroke-width="2">antenne ← · puce vers le haut · → prise USB</text>')
    s.append(f'<text x="{xb + 3.2 * P:.1f}" y="{(ya + yb) / 2 + 13:.1f}" font-size="5" text-anchor="middle" fill="#fff">USB</text>')
    s.append(f'<rect x="{xy((6, 18))[0] - 4:.1f}" y="{xy((6, 18))[1] - 4:.1f}" width="{6 * P + 8:.1f}" height="{4 * P + 8:.1f}" rx="2" fill="none" stroke="#fde9b0" stroke-width=".7" stroke-dasharray="2 2"/>')
    s.append(f'<text x="{xy((8, 21))[0] + 4:.1f}" y="{xy((8, 21))[1] + 2:.1f}" font-size="6" text-anchor="middle" fill="#fde9b0" font-weight="700" paint-order="stroke" stroke="#2f6b3c" stroke-width="2">passage du câble USB</text>')
    for cid, titre, genre, trous, _ in composants:
        xs = [xy(t)[0] for t in trous]
        ys = [xy(t)[1] for t in trous]
        x1, x2, y1, y2 = min(xs), max(xs), min(ys), max(ys)
        if genre in ("male", "femelle"):
            fond = "#151515" if genre == "male" else "#3a3a3a"
            s.append(f'<rect x="{x1 - 4.6:.1f}" y="{y1 - 4.6:.1f}" width="{x2 - x1 + 9.2:.1f}" height="{y2 - y1 + 9.2:.1f}" rx="1" fill="{fond}"/>')
            for t in trous:
                x, y = xy(t)
                if genre == "male":
                    s.append(f'<rect x="{x - 1.6:.1f}" y="{y - 1.6:.1f}" width="3.2" height="3.2" fill="#e8c46a"/>')
                else:
                    s.append(f'<rect x="{x - 1.4:.1f}" y="{y - 1.4:.1f}" width="2.8" height="2.8" fill="#000"/>')
            x, y = xy(trous[0])
            s.append(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="3.2" fill="none" stroke="#e5484d" stroke-width="1.1"/>')
        elif genre == "bornier":
            s.append(f'<rect x="{x1 - P:.1f}" y="{y1 - 1.5 * P:.1f}" width="{x2 - x1 + 2 * P:.1f}" height="{3 * P:.1f}" rx="1.5" fill="#2e6fd1" opacity=".93"/>')
            for t in trous:
                x, y = xy(t)
                s.append(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="3.6" fill="#c9d3dd"/><path d="M{x - 2.4:.1f} {y:.1f}h4.8" stroke="#556" stroke-width="1"/>')
            for t in trous:
                p = pads[t]
                x, y = xy(t)
                s.append(f'<text x="{x:.1f}" y="{y + (-6.5 if cid == "J1" else 10):.1f}" font-size="7" text-anchor="middle" fill="#fff" font-weight="700">{p[1]}</text>')
        elif genre == "resistance":
            s.append(f'<path d="M{x1:.1f} {y1:.1f}H{x2:.1f}" stroke="#aaa" stroke-width="1.2"/>'
                     f'<rect x="{x1 + 1.2 * P:.1f}" y="{y1 - 2.6:.1f}" width="{x2 - x1 - 2.4 * P:.1f}" height="5.2" rx="2.4" fill="#d9b98a" stroke="#8a6a3a" stroke-width=".4"/>')
            for t in trous:
                x, y = xy(t)
                s.append(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="1.6" fill="#aaa"/>')
        elif genre == "condo":
            (xa_, ya_), (xb_, yb_) = xy(trous[0]), xy(trous[1])
            s.append(f'<path d="M{xa_:.1f} {ya_:.1f}H{xa_ - 0.6 * P:.1f}M{xb_:.1f} {yb_:.1f}V{yb_ + 4:.1f}H{xa_ - 0.6 * P:.1f}" stroke="#aaa" stroke-width="1" fill="none"/>'
                     f'<rect x="{xa_ - 4.6 * P:.1f}" y="{ya_ - 6:.1f}" width="{4 * P:.1f}" height="12" rx="3" fill="#2a3f6b"/>'
                     f'<rect x="{xa_ - 4.6 * P:.1f}" y="{ya_ - 6:.1f}" width="{4 * P:.1f}" height="3" rx="1.5" fill="#c9d3dd"/>'
                     f'<text x="{xa_ - 2.6 * P:.1f}" y="{ya_ + 4:.1f}" font-size="6" text-anchor="middle" fill="#fff" font-weight="700">C1 100 µF</text>')
            for t in trous:
                x, y = xy(t)
                s.append(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="1.6" fill="#aaa"/>')
            s.append(f'<text x="{xa_:.1f}" y="{ya_ - 5:.1f}" font-size="6" text-anchor="middle" fill="#fff" font-weight="700">−</text>'
                     f'<text x="{xb_:.1f}" y="{yb_ - 5:.1f}" font-size="6" text-anchor="middle" fill="#fff" font-weight="700">+</text>')
    # Les noms des connecteurs
    etiquettes = {
        "J2": ("A4", 5, -12, "J2 · ÉCRAN"), "J3": ("A10", 0, -12, "J3 · RFID"), "J14": ("A14", 5, -12, "J14 → B"),
        "J16": ("A20", 5, -12, "J16 → PLAQUE D"), "J8": ("P6", 0, 13, "J8 · COMMANDES"), "J15": ("P9", 5, 13, "J15 → C"),
        "J7": ("P18", 0, 13, "J7 LED"), "R4": ("Q10", 0, 10, "R4 10 kΩ"), "R3": ("Q16", 0, 10, "R3 330 Ω"),
    }
    for cid, (ref, dx, dy, txt) in etiquettes.items():
        x, y = xy(h(ref))
        s.append(f'<text x="{x + dx:.1f}" y="{y + dy:.1f}" font-size="7" text-anchor="middle" fill="#fff" font-weight="700" '
                 f'paint-order="stroke" stroke="#2f6b3c" stroke-width="2">{txt}</text>')
    # Les broches de l'ESP32
    for t, (c, b, _) in pads.items():
        if c.startswith("ESP"):
            x, y = xy(t)
            dy = 10.5 if c == "ESP-H" else -7
            s.append(f'<text x="{x:.1f}" y="{y + dy:.1f}" font-size="5" text-anchor="middle" fill="#fde9b0" font-weight="700" '
                     f'paint-order="stroke" stroke="#1b2a3a" stroke-width="1.6">{b}</text>')
    return s


def verso(e=None):
    """Le verso. Avec e : ce qui a déjà été fait en grisé, l'étape e en couleur, la suite cachée."""
    s = base(True)
    etape_de = {t: et for _, _, _, trous, et in composants for t in trous}

    def etat(et):
        if e is None or et == e:
            return "vif"
        return "fait" if et < e else None

    # Les soudures des broches des composants
    for t, (c, b, sig) in pads.items():
        st = etat(etape_de[t])
        if st:
            x, y = xy(t, True)
            s.append(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="3.3" fill="{"#e6e6e6" if st == "vif" else "#b8b8b8"}" stroke="#777" stroke-width=".6"/>')
    # Les fils nus
    for sig, trous, et, titre_q in nus:
        st = etat(et)
        if not st:
            continue
        pts = " ".join(f"{xy(t, True)[0]:.1f},{xy(t, True)[1]:.1f}" for t in trous)
        if titre_q.startswith("queue du fil"):
            if st == "vif":
                s.append(f'<polyline points="{pts}" fill="none" stroke="{coul(sig)}" stroke-width="1.3" stroke-dasharray="1.4 .8"/>')
            continue
        if st == "fait":
            s.append(f'<polyline points="{pts}" fill="none" stroke="#9a9a9a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" opacity=".8"/>')
            continue
        s.append(f'<polyline points="{pts}" fill="none" stroke="#f7f7f7" stroke-width="3.8" stroke-linecap="round" stroke-linejoin="round"/>'
                 f'<polyline points="{pts}" fill="none" stroke="{coul(sig)}" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"/>')
        for t in trous:
            x, y = xy(t, True)
            s.append(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="1.5" fill="#bfbfbf" stroke="{coul(sig)}" stroke-width=".7"/>')
    # Les fils isolés, en courbe douce
    for n, sig, a, b, et, _, epais in fils:
        st = etat(et)
        if not st:
            continue
        (x1, y1), (x2, y2) = xy(a, True), xy(b, True)
        mx, my = (x1 + x2) / 2, (y1 + y2) / 2
        dx, dy = x2 - x1, y2 - y1
        k = 0.06 + (n % 4) * 0.03
        cx, cy = mx - dy * k, my + dx * k
        chemin = f"M{x1:.1f} {y1:.1f}Q{cx:.1f} {cy:.1f} {x2:.1f} {y2:.1f}"
        if st == "fait":
            s.append(f'<path d="{chemin}" fill="none" stroke="#9a9a9a" stroke-width="1" opacity=".7"/>')
            continue
        w = 2.6 if epais else 1.7
        s.append(f'<path d="{chemin}" fill="none" stroke="#fff" stroke-width="{w + 1.6}" opacity=".9"/>'
                 f'<path d="{chemin}" fill="none" stroke="{coul(sig)}" stroke-width="{w}"/>')
        for x, y in ((x1, y1), (x2, y2)):
            s.append(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="2.2" fill="{coul(sig)}" stroke="#fff" stroke-width=".7"/>')
        tx, ty = 0.25 * x1 + 0.5 * cx + 0.25 * x2, 0.25 * y1 + 0.5 * cy + 0.25 * y2
        s.append(f'<circle cx="{tx:.1f}" cy="{ty:.1f}" r="4.4" fill="#fff" stroke="{coul(sig)}" stroke-width="1"/>'
                 f'<text x="{tx:.1f}" y="{ty + 2.2:.1f}" font-size="6" text-anchor="middle" font-weight="700" fill="#222">{n}</text>')
    # Les noms des rails et des barres (vus du verso : la droite et la gauche sont inversées)
    return s



def svg(elements, titre):
    w, hh = LARGE + 2 * MARGE, HAUTE + 2 * MARGE
    return (f'<svg viewBox="0 0 {w:.1f} {hh:.1f}" style="display:block;margin:0 auto;width:100%;max-height:148mm" role="img" aria-label="{titre}">' + "".join(elements) + "</svg>")


# --------------------------------------------------------------------------------------------
# Les tableaux
# --------------------------------------------------------------------------------------------
def longueur(a, b):
    return round((abs(a[0] - b[0]) + abs(a[1] - b[1])) * 2.54 + 12)


NOMS_SIG = {"5V": "5 V", "GND": "masse", "3V3": "3,3 V", "MOSI": "MOSI (23)", "MISO": "MISO (19)", "SCK": "SCK (18)",
            "DIN": "données des LED"}


def sig_txt(s):
    return NOMS_SIG.get(s, "broche " + s[1:] if s.startswith("G") else s)


def puce(s):
    return f'<span class="c" style="background:{coul(s)}"></span>'


def tables():
    out = []
    total = 0
    for e in sorted(ETAPES):
        titre, resume = ETAPES[e]
        lignes = []
        n_pts = 0
        for cid, t_, genre, trous, et in composants:
            if et != e:
                continue
            if genre == "femelle":
                desc = "barrette femelle 1 × 15"
            elif genre == "male":
                desc = f"barrette mâle 1 × {len(trous)}"
            elif genre == "bornier":
                desc = "bornier à vis 2 plots, entrées des fils vers le bord"
            elif genre == "resistance":
                desc = "à plat, pattes pliées à 4 trous d'écart"
            else:
                desc = "couché vers la gauche, patte − (bande claire) en L23"
            places = ", ".join(f"{nom(t)} <span class='g'>{pads[t][1]}</span>" if genre in ("bornier", "condo", "femelle") or cid == "J14"
                               else nom(t) for t in trous)
            if genre in ("male",):
                places = f"{nom(trous[0])} (broche 1) à {nom(trous[-1])}"
            if genre == "femelle":
                places = f"{nom(trous[0])} à {nom(trous[-1])}"
            lignes.append(f"<tr><td class='q'>{len(trous)}</td><td><b>{t_}</b> — {desc}</td><td>{places}</td></tr>")
            n_pts += len(trous)
        for sig, trous, et, titre_nu in nus:
            if et != e or titre_nu.startswith("queue du fil"):
                continue
            libres = [t for t in trous if t not in pads]
            lignes.append(f"<tr><td class='q'>{len(trous)}</td><td>{puce(sig)}<b>Fil nu</b> : {titre_nu}</td>"
                          f"<td>{nom(trous[0])} → {nom(trous[-1])} <span class='g'>({len(trous)} trous"
                          f"{', dont ' + str(len(trous) - len(libres)) + ' déjà soudés' if len(libres) < len(trous) else ''})</span></td></tr>")
            n_pts += len(libres)
        for n, sig, a, b, et, role, epais in fils:
            if et != e:
                continue
            pts = sum(2 if entrees[(n, k)][0] == "trou" else 1 for k in (0, 1))
            lignes.append(f"<tr><td class='q'>{pts}</td><td>{puce(sig)}<b>Fil {n}</b> · {role}</td>"
                          f"<td>{bout_txt(n, 0)} → {bout_txt(n, 1)} <span class='g'>· {'22 AWG' if epais else '26 à 30 AWG'} · ≈ {longueur(a, b)} mm</span></td></tr>")
            n_pts += pts
        total += n_pts
        tests = "".join(f"<li>{t}</li>" for t in TESTS[e])
        out.append(f"""<div class="etape saut"><h2>Étape {e} · {titre} <span class="g">— environ {n_pts} soudures</span></h2>
<div class="cote"><div>{svg(verso(e), "Verso, étape " + str(e))}
<p class="petit legende">Vu du <b>verso</b>. En couleur : ce que l'on soude à cette étape ; en gris : les étapes déjà faites.</p></div>
<div><p class="petit">{resume}</p>
<table><tr><th class="q">Pts</th><th>Quoi</th><th>Où (vu du recto)</th></tr>{''.join(lignes)}</table>
<div class="test"><b>Test avant l'étape suivante</b><ul>{tests}</ul></div></div></div></div>""")
    return "".join(out), total


def page():
    erreurs, reseau = verifier()
    if erreurs:
        print("\n".join("✗ " + e for e in erreurs))
        sys.exit(1)
    corps, total = tables()

    def liste(sig):
        ns = [str(f[0]) for f in fils if f[1] == sig]
        return ", ".join(ns[:-1]) + " et " + ns[-1]
    rang_n = ", ".join(sorted({str(n) for (n, k), v in entrees.items() if v[2][0] == 13}, key=int))
    def chemin(sig):
        """La guirlande d'un signal, de broche en broche, depuis sa source (un arbre sans branche à plus de 2 fils)."""
        voisins = {}
        for n, sg, a, b, *_ in fils:
            if sg != sig:
                continue
            ta, tb = entrees[(n, 0)][2], entrees[(n, 1)][2]
            voisins.setdefault(ta, []).append(tb)
            voisins.setdefault(tb, []).append(ta)
        depart = SOURCES.get(sig) or next(t for t in voisins if pads[t][0].startswith("ESP"))
        vus, ordre, pile = set(), [], [depart]
        while pile:
            t = pile.pop()
            if t in vus:
                continue
            vus.add(t)
            ordre.append(t)
            pile.extend(sorted(voisins.get(t, []), reverse=True))
        return " → ".join(nom_broche(t) for t in ordre)
    ponts_partages = "" and "<table><tr><th>Signal</th><th>La guirlande, de broche en broche</th></tr>" + "".join(
        f"<tr><td>{puce(sg)}<b>{lib}</b></td><td>{chemin(sg)}</td></tr>"
        for sg, lib in (("MOSI", "23 · MOSI"), ("MISO", "19 · MISO"), ("SCK", "18 · SCK"), ("3V3", "3,3 V"), ("5V", "5 V"), ("GND", "Masse"))) + "</table>"
    return f"""<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Plaque A trou par trou</title>
<link rel="stylesheet" href="commun.css">
<style>
  @page {{ size: A4 landscape; margin: 10mm 11mm 12mm; }}
  body {{ font-size: 9.2pt; }}
  .couverture {{ background: var(--vert); color: #fff; border-radius: 10px; padding: 12px 18px; margin-bottom: 8px; }}
  .couverture h1 {{ color: #fff; margin: 0; font-size: 21pt; }}
  .couverture p {{ color: #f2d9a6; margin: 3px 0 0; }}
  h2 {{ break-after: avoid; border-bottom: 2px solid var(--or); padding-bottom: 2px; margin: 10px 0 5px; }}
  h3 {{ break-after: avoid; margin: 8px 0 3px; }}
  .saut {{ break-before: page; }}
  table {{ width: 100%; border-collapse: collapse; font-size: 8.1pt; }}
  th {{ background: var(--vert); color: #fff; text-align: left; padding: 3px 5px; }}
  td {{ border-bottom: 1px solid var(--filet); padding: 2.2px 5px; vertical-align: top; }}
  tr {{ break-inside: avoid; }}
  td.q, th.q {{ width: 26px; text-align: right; color: var(--gris); }}
  .g {{ color: var(--gris); font-weight: 400; }}
  .c {{ display: inline-block; width: 9px; height: 9px; border-radius: 2px; border: 1px solid #0003; vertical-align: -1px; margin-right: 4px; }}
  .note {{ background: #fbf7ee; border-left: 4px solid var(--or); padding: 5px 10px; margin: 5px 0; font-size: 8.6pt; }}
  .alerte {{ background: #fbeceb; border-left: 4px solid var(--rouge); padding: 5px 10px; margin: 5px 0; font-size: 8.6pt; }}
  .test {{ background: #eef3ec; border-left: 4px solid var(--vert); padding: 4px 10px; margin: 5px 0 10px; font-size: 8.4pt; }}
  .test ul {{ margin: 2px 0 0 16px; padding: 0; }}
  .cote {{ display: grid; grid-template-columns: 1.25fr 1fr; gap: 12px; align-items: start; }}
  .cote table {{ font-size: 7.6pt; }}
  .deux {{ display: grid; grid-template-columns: 1.15fr 1fr; gap: 12px; align-items: start; }}
  .petit {{ font-size: 8.2pt; margin: 2px 0 5px; }}
  .legende span {{ margin-right: 12px; white-space: nowrap; }}
""" + STYLE_MODULES + f"""
  svg text {{ font-family: "Source Sans", sans-serif; }}
</style>
</head>
<body>
<div class="couverture"><h1>Plaque A, trou par trou</h1>
<p>L'ESP32 et tous ses connecteurs sur la plaque 50 × 70 mm (18 × 24 trous) : chaque composant, chaque fil, chaque soudure, en sept étapes testées une à une.</p></div>

<div class="deux">
<div>
<h2>Comment lire ce plan</h2>
<p class="petit"><b>Tenez la plaque à l'horizontale</b>, les deux bandes de pastilles allongées à gauche et à droite (elles ne servent pas).
Les <b>colonnes</b> vont de <b>1 à 24</b>, les <b>rangées</b> de <b>A à R</b>. Une coordonnée comme <b>D15</b> désigne toujours le même trou,
<b>vu du recto</b> (le côté des composants), colonne 1 à gauche. Écrivez au feutre « 1 », « 24 », « A » et « R » dans les coins des deux faces avant de commencer.</p>
<p class="petit"><b>Le verso</b> (côté soudures) est dessiné tel que vous le voyez en retournant la plaque <b>comme une page de livre</b> :
la colonne 1 passe à droite. Les coordonnées restent les mêmes.</p>
<p class="petit"><b>Une seule sorte de liaison : le fil isolé numéroté</b>, au verso. <b>Aucun fil nu</b>, aucun pont d'étain : chaque fil va d'une
broche à une autre, couché à plat, et peut croiser les autres (leur isolant les protège). Les fils de la masse et du 5 V sont en <b>22 AWG</b>,
les autres en fil fin (26 à 30 AWG).</p>
<div class="alerte"><b>Avant toute soudure</b>, posez l'ESP32 sur ses deux barrettes femelles et enfoncez celles-ci dans la plaque : la rangée du haut en <b>D1 à D15</b>,
la rangée du bas doit tomber en <b>N1 à N15</b> (9 trous libres entre les deux). Si elle tombe en <b>M</b> (ESP32 plus étroit, 22,9 mm), soudez-la en M :
tout le reste du plan est inchangé : les fils {rang_n}, qui arrivent à une broche de la rangée N, se soudent sur la broche de la rangée M
(leur trou d'entrée reste le même, leur bout dénudé va un trou plus loin).</div>
<div class="note"><b>Comment souder un fil isolé sans faux contact.</b> Un fil n'est jamais posé sur une soudure déjà faite.
<b>« entre en C5 »</b> : le bout dénudé (3 mm) passe par le trou libre <b>C5</b> depuis le verso, dépasse de 1 mm au recto (plié, il tient seul),
et se soude au verso ; dénudez-le sur 5 mm : ce qui dépasse au verso se couche jusqu'à la broche voisine et s'y soude. Le fil fait
lui-même la liaison, tenu par son trou. <b>« enroulé sur la broche »</b> : pas de trou libre à côté ; le bout dénudé fait un demi-tour serré
autour de la broche qui dépasse au verso, <b>avant</b> de souder. Deux fils sur une même broche : enroulez le premier, puis le second
par-dessus, et soudez les deux ensemble. Tirez doucement sur chaque fil une fois soudé : il ne doit pas bouger. Quand tout est testé, une goutte de colle chaude sur chaque fil, à 5 mm de sa soudure.</div>
<div class="note"><b>Les sept étapes</b> — Soudez une étape, faites son test, puis passez à la suivante : une erreur se trouve tout de suite. Dans une étape, soudez d'abord les
connecteurs, puis les fils dans l'ordre de leur numéro. « Pts » : le nombre de soudures de la ligne. Au total, environ <b>{total} soudures</b>.
La longueur des fils isolés est indicative (coupez 1 cm de plus, dénudez 2 mm à chaque bout).</div>
</div>
<div>
<h2>Une broche, un fil</h2>
<p class="petit">La plaque A ne porte plus que les signaux <b>propres à chaque module</b> : la sélection de l'écran, des lecteurs, les liaisons
du son, de l'I2C, des commandes et des LED. Tout ce qui est <b>partagé</b> (5 V, masse, 3,3 V et les trois lignes du bus SPI : 23, 18, 19)
part par <b>J16</b> vers la <b>plaque D</b>, qui le distribue sur ses rails (plaque-d.pdf). Chaque broche de l'ESP32 ne reçoit donc
qu'<b>un seul fil</b>, qui va droit à son connecteur : {len(fils)} fils en tout, sans guirlande.</p>
<p class="petit">Chaque module a ainsi une partie de ses fils sur A et l'autre sur D : le tableau « Les câbles des modules », en fin de document,
dit où va chaque fil. Sur un rail de D, n'importe quelle broche convient.</p>
</div>
</div>

<h2 class="saut">Le recto : où poser chaque composant</h2>
<p class="petit legende"><span><b style="color:#e5484d">○</b> broche 1 (ou plot repéré)</span><span>Bleu : bornier à vis</span><span>Noir : barrette mâle</span><span>Gris foncé : barrette femelle de l'ESP32</span>
<span>Pointillés : la carte ESP32 au-dessus (elle déborde de quelques millimètres à gauche, c'est normal : l'antenne reste dégagée)</span></p>
<div class="note" style="margin-bottom:4px">En haut, de gauche à droite : <b>J2</b> (écran), <b>J3</b> (sélection des lecteurs RFID), <b>J14</b> (son),
<b>J16</b> (vers la plaque D). En bas : <b>J8</b> (molette, clé, gros bouton), <b>J15</b> (I2C), <b>J7</b> (données des LED). R4 et R3 se couchent à plat,
sous les connecteurs du bas. Plus de bornier sur A : le 5 V arrive par la plaque D.</div>
{svg(recto(), "Recto de la plaque A")}



{corps}
<h2 class="saut">Les câbles des modules : sur A ou sur D</h2>
<p class="petit">Chaque fil Dupont d'un module se branche soit sur un connecteur de la <b style="color:#2e6fd1">plaque A</b> (son signal propre),
soit sur un <b style="color:#a3392b">rail de la plaque D</b> (n'importe quelle broche libre du rail). Les modules eux-mêmes ne changent pas.</p>
<div class="colonnes">{table_modules()}</div>
<h2 class="saut">Le verso terminé (pour la vérification finale)</h2>
<p class="petit legende"><span>{puce("5V")}5 V</span><span>{puce("GND")}masse</span><span>{puce("3V3")}3,3 V</span><span>{puce("MOSI")}MOSI (23)</span>
<span>{puce("MISO")}MISO (19)</span><span>{puce("SCK")}SCK (18)</span><span>{puce("DIN")}LED</span><span>{puce("G5")}les autres signaux</span>
<span>Cercles gris : soudures des broches · gros traits : fils isolés épais (22 AWG)</span></p>
{svg(verso(), "Verso de la plaque A")}

<div class="note"><b>Ce plan a été vérifié automatiquement</b> : chaque signal forme un seul morceau, aucun fil ne relie deux signaux différents,
et aucune broche ne reçoit plus de deux fils. Les broches TX0, RX0 et le GND du bas de l'ESP32 (N14) sont soudées mais ne reçoivent rien : c'est voulu
(N14 est déjà relié à D14 dans l'ESP32).</div>
</body>
</html>
"""


if __name__ == "__main__":
    (ICI / "plaque-a.html").write_text(page())
    print("✓ plaque-a.html")
