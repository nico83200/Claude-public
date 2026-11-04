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
poser("J2", "J2 · écran", "male", 0, 2, ["5V", "GND", "G5", "EN", "G21", "MOSI", "SCK", "G12", "G4", "MOSI", "MISO", "SCK"], etape=2)
poser("J13", "J13 · tactile", "male", 0, 15, ["G14", "SCK", "MOSI", "MISO", "G15"], etape=2)
poser("J1", "J1 · arrivée du 5 V", "bornier", 1, 21, ["GND", None, "5V"], noms=["−", "", "+"], etape=1)
poser("J14", "J14 · vers la plaque B (le son)", "male", 4, 24, ["5V", "GND", "G17", "G16", "G2", "G36"], sens=-1, etape=4)
poser("J8", "J8 · molette", "male", 15, 2, ["3V3", "GND", "G34", "G35", "G32"], etape=6)
poser("J15", "J15 · vers la plaque C (I2C)", "male", 15, 7, ["3V3", "GND", "G26", "G27"], etape=5)
poser("J10", "J10 · clé", "male", 15, 12, ["G33", "GND"], etape=6)
poser("J9", "J9 · gros bouton", "male", 15, 15, ["G39", "GND"], etape=6)
poser("J7", "J7 · chaîne de LED", "male", 15, 18, ["5V", "GND", "DIN"], etape=7)
poser("J12", "J12 · 5 V du servomoteur", "bornier", 15, 22, ["GND", None, "5V"], noms=["−", "", "+"], etape=7)
poser("J3", "J3 · lecteur RFID de la fente", "male", 17, 2, ["3V3", "3V3", "GND", "MISO", "MOSI", "SCK", "G13"], etape=3)
poser("J4", "J4 · lecteur RFID à plat", "male", 17, 10, ["3V3", "3V3", "GND", "MISO", "MOSI", "SCK", "G22"], etape=3)
poser("R4", "R4 · 10 kΩ (gros bouton)", "resistance", 14, 11, ["3V3", None, None, None, "G39"], etape=6)
poser("R3", "R3 · 330 Ω (LED)", "resistance", 14, 16, ["G25", None, None, None, "DIN"], etape=7)
poser("C1", "C1 · 100 µF", "condo", 11, 23, ["GND", "5V"], noms=["−", "+"], etape=1)

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


# Étape 1 : l'alimentation
nu("GND", "B3 B21", 1, "rail de masse du haut (rangée B)")
nu("GND", "A3 B3", 2, "pont J2-2 → rail de masse")
nu("GND", "D14 B14", 1, "pont GND de l'ESP32 → rail de masse")
nu("GND", "B21 C21 C23 Q23", 1, "rail de masse de droite (colonne 23)")
nu("GND", "Q3 Q23", 3, "rail de masse du bas (rangée Q)")
nu("5V", "B23 B24 P24", 1, "rail 5 V de droite (colonne 24)")
nu("GND", "P23 P22", 7, "pont J12 − → rail de masse")
# Les barres de distribution : un seul fil part de la broche de l'ESP32, chaque connecteur a son trou.
nu("3V3", "D15 H15", 3, "barre 3,3 V (sous l'ESP32)")
nu("MOSI", "D1 F1 F5", 2, "barre MOSI (broche 23, sous l'ESP32)")
nu("MISO", "D6 H6 H2", 2, "barre MISO (broche 19, sous l'ESP32)")
nu("SCK", "D7 K7 K2", 2, "barre SCK (broche 18, sous l'ESP32)")
# Les ponts entre broches voisines
nu("3V3", "P2 R2", 3, "pont J8-1 → J3-1 (3,3 V)")
nu("3V3", "R2 R3", 3, "pont J3-1 → J3-2 (RST du RC522 tenu à 3,3 V)")
nu("3V3", "R10 R11", 3, "pont J4-1 → J4-2 (RST du RC522 tenu à 3,3 V)")
nu("GND", "R4 Q4", 3, "pont J3-3 → rail de masse")
nu("GND", "R12 Q12", 3, "pont J4-3 → rail de masse")
nu("GND", "P8 Q8", 5, "pont J15-2 → rail de masse")
nu("G26", "N9 P9", 5, "pont broche 26 → J15-3 (SDA)")
nu("G27", "N10 P10", 5, "pont broche 27 → J15-4 (SCL)")
nu("GND", "P3 Q3", 6, "pont J8-2 → rail de masse")
nu("G34", "N4 P4", 6, "pont broche 34 → J8-3")
nu("G35", "N5 P5", 6, "pont broche 35 → J8-4")
nu("G32", "N6 P6", 6, "pont broche 32 → J8-5")
nu("GND", "P13 Q13", 6, "pont J10-2 → rail de masse")
nu("GND", "P16 Q16", 6, "pont J9-2 → rail de masse")
nu("G39", "O15 P15", 6, "pont R4 → J9-1")
nu("GND", "P19 Q19", 7, "pont J7-2 → rail de masse")
nu("DIN", "O20 P20", 7, "pont R3 → J7-3")

fils = []   # (n°, signal, de, à, étape, rôle, épais)
_n = [0]


def fil(signal, a, b, etape, role, epais=False):
    _n[0] += 1
    fils.append((_n[0], signal, h(a), h(b), etape, role, epais))


# Étape 1
fil("5V", "N24", "N15", 1, "5 V → VIN de l'ESP32", True)
# Étape 2 : l'écran et le tactile
fil("5V", "D24", "A2", 2, "5 V → J2-1 (alimentation de l'écran)", True)
fil("G5", "D8", "A4", 2, "broche 5 → J2-3 (CS de l'écran)")
fil("EN", "N1", "A5", 2, "EN → J2-4 (RESET de l'écran)")
fil("G21", "D5", "A6", 2, "broche 21 → J2-5 (DC de l'écran)")
fil("MOSI", "F1", "A7", 2, "barre MOSI → J2-6")
fil("SCK", "K2", "A8", 2, "barre SCK → J2-7")
fil("G12", "N12", "A9", 2, "broche 12 → J2-8 (rétroéclairage)")
fil("G4", "D11", "A10", 2, "broche 4 → J2-9 (CS de la carte SD)")
fil("MOSI", "F2", "A11", 2, "barre MOSI → J2-10")
fil("MISO", "H2", "A12", 2, "barre MISO → J2-11")
fil("SCK", "K3", "A13", 2, "barre SCK → J2-12")
fil("G14", "N11", "A15", 2, "broche 14 → J13-1 (CS du tactile)")
fil("SCK", "K4", "A16", 2, "barre SCK → J13-2")
fil("MOSI", "F3", "A17", 2, "barre MOSI → J13-3")
fil("MISO", "H3", "A18", 2, "barre MISO → J13-4")
fil("G15", "D13", "A19", 2, "broche 15 → J13-5 (réserve)")
# Étape 3 : les lecteurs RFID
fil("3V3", "E15", "P2", 3, "barre 3,3 V → J8-1, J3-1 et J3-2")
fil("3V3", "F15", "R10", 3, "barre 3,3 V → J4-1 et J4-2")
fil("MISO", "H4", "R5", 3, "barre MISO → J3-4")
fil("MOSI", "F4", "R6", 3, "barre MOSI → J3-5")
fil("SCK", "K5", "R7", 3, "barre SCK → J3-6")
fil("G13", "N13", "R8", 3, "broche 13 → J3-7 (CS de la fente)")
fil("MISO", "H5", "R13", 3, "barre MISO → J4-4")
fil("MOSI", "F5", "R14", 3, "barre MOSI → J4-5")
fil("SCK", "K6", "R15", 3, "barre SCK → J4-6")
fil("G22", "D2", "R16", 3, "broche 22 → J4-7 (CS du lecteur à plat)")
# Étape 4 : le son
fil("G17", "D9", "E22", 4, "broche 17 → J14-3")
fil("G16", "D10", "E21", 4, "broche 16 → J14-4")
fil("G2", "D12", "E20", 4, "broche 2 → J14-5")
fil("G36", "N2", "E19", 4, "broche 36 → J14-6")
# Étape 5 : l'I2C
fil("3V3", "G15", "P7", 5, "barre 3,3 V → J15-1")
# Étape 6 : les commandes
fil("G33", "N7", "P12", 6, "broche 33 → J10-1 (clé)")
fil("G39", "N3", "O15", 6, "broche 39 → R4 et J9-1 (gros bouton)")
fil("3V3", "H15", "O11", 6, "barre 3,3 V → R4")
# Étape 7 : les LED et le servomoteur
fil("G25", "N8", "O16", 7, "broche 25 → R3")
fil("5V", "O24", "P18", 7, "5 V → J7-1 (LED)", True)

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
                nus.append((sig, [u, t], et, f"pont du fil {n}"))
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
    1: ("L'alimentation et l'ESP32", "Bornier J1, barrettes femelles de l'ESP32, rails 5 V et masse, C1."),
    2: ("L'écran et le tactile", "J2, J13, les trois barres SPI et leurs fils."),
    3: ("Les deux lecteurs RFID", "J3, J4, la barre 3,3 V, le rail de masse du bas."),
    4: ("Le son (vers la plaque B)", "J14 et ses quatre fils."),
    5: ("Le bus I2C (vers la plaque C)", "J15."),
    6: ("La molette, le gros bouton et la clé", "J8, J9, J10 et R4."),
    7: ("Les LED et le servomoteur", "J7, R3 et le bornier J12."),
}
TESTS = {
    1: ["Multimètre en position bip, <b>sans l'ESP32</b> : pas de bip entre B23 (+) et B21 (−) de J1.",
        "Bip entre B21 et Q23, entre B21 et D14 ; bip entre B23 et P24, entre B23 et N15 (VIN).",
        "Branchez le 5 V sur J1, toujours sans l'ESP32 : 5 V entre N15 (+) et D14 (−). Débranchez, enfoncez l'ESP32 (USB à droite), rebranchez : la LED rouge de l'ESP32 s'allume. Entre D15 et D14 : 3,3 V."],
    2: ["Bip entre la broche 23 (D1) et J2-6, J2-10, J13-3 ; entre 18 (D7) et J2-7, J2-12, J13-2 ; entre 19 (D6) et J2-11, J13-4.",
        "<b>Pas de bip</b> entre deux barres voisines (D1 et D6, D6 et D7), ni entre une barre et la masse.",
        "Branchez l'écran sur J2 et J13 : le logo, puis le menu ; le doigt sur la dalle fait réagir les menus. Avec la carte SD : le menu des mondes."],
    3: ["Bip entre E15 (barre 3,3 V) et J3-1, J3-2, J4-1, J4-2, J8-1. Pas de bip entre la barre 3,3 V et la masse, ni avec le 5 V.",
        "Bip entre D1 et J3-5, J4-5 ; D7 et J3-6, J4-6 ; D6 et J3-4, J4-4.",
        "Branchez les deux RC522 : le journal série affiche leur version (0x91 ou 0x92, et non 0xFF) ; une carte passée dans la fente est lue."],
    4: ["Bip entre B23 (5 V) et J14-1, entre B21 (masse) et J14-2.",
        "Branchez la plaque B : le son d'accueil sort du haut-parleur."],
    5: ["Bip entre E15 (3,3 V) et J15-1, entre Q3 (masse) et J15-2.",
        "Branchez la plaque C : le journal série liste les modules I2C trouvés (0x20, 0x21…)."],
    6: ["Bip entre E15 (3,3 V) et J8-1. Résistance de R4 : environ 10 kΩ entre O11 et O15.",
        "Branchez la molette, le gros bouton et la clé : le menu « Tester le pupitre » les voit."],
    7: ["Bip entre B23 et J7-1, entre B23 et J12 + ; bip entre B21 et J7-2, J12 −. Environ 330 Ω entre O16 et O20.",
        "Branchez l'anneau de LED : il s'allume au démarrage. Branchez le servomoteur (par le PCA9685) : le tiroir s'ouvre au test."],
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
            if bouts[t] > 1:
                erreurs.append(f"fil {n} : deux fils soudés sur {nom(t)}")
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
        "J2": ("A7", 0, -12, "J2 · ÉCRAN"), "J13": ("A17", 0, -12, "J13 · TACTILE"), "J1": ("B22", 0, -18.5, "J1 · 5 V"),
        "J14": ("E21", 0, 13, "J14 → B"), "J8": ("P4", 0, 13, "J8"), "J15": ("P8", 5, 13, "J15 → C"),
        "J10": ("P12", 5, 13, "J10"), "J9": ("P15", 5, 13, "J9"), "J7": ("P19", 0, 13, "J7"), "J12": ("P23", 0, -18, "J12 · SERVO"),
        "J3": ("R5", 0, 12, "J3 · RFID FENTE"), "J4": ("R13", 0, 12, "J4 · RFID À PLAT"),
        "R4": ("O13", 0, -5, "R4 10 kΩ"), "R3": ("O18", 0, -5, "R3 330 Ω"),
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
    for sig, trous, et, _ in nus:
        st = etat(et)
        if not st:
            continue
        pts = " ".join(f"{xy(t, True)[0]:.1f},{xy(t, True)[1]:.1f}" for t in trous)
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
    for ref, dx, dy, txt, sig, et in [("F3", 0, -5, "barre MOSI (23)", "MOSI", 2), ("H4", 0, -5, "barre MISO (19)", "MISO", 2),
                                       ("K4", 0, 10, "barre SCK (18)", "SCK", 2), ("G15", -6, 2, "barre 3,3 V", "3V3", 3),
                                       ("B12", 0, -4.5, "rail de masse", "GND", 1), ("Q13", 0, 10, "rail de masse", "GND", 3),
                                       ("K24", -6, 2, "5 V", "5V", 1), ("K23", 6, 2, "masse", "GND", 1)]:
        if etat(et) != "vif":
            continue
        x, y = xy(h(ref), True)
        anc = "middle" if dx == 0 else ("start" if dx > 0 else "end")
        s.append(f'<text x="{x + dx:.1f}" y="{y + dy:.1f}" font-size="6.5" text-anchor="{anc}" font-weight="700" fill="{coul(sig)}" '
                 f'paint-order="stroke" stroke="#fff" stroke-width="2.2">{txt}</text>')
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
            if et != e or titre_nu.startswith("pont du fil"):
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
    ponts_partages = """
<table><tr><th>Broche de l'ESP32</th><th>Elle part vers</th><th>Comment</th></tr>
<tr><td>{p}<b>23 · MOSI</b> (D1)</td><td>J2-6, J2-10, J13-3, J3-5, J4-5</td><td>Un fil nu descend de D1 à F1 puis longe la rangée F jusqu'à F5 : la <b>barre MOSI</b>. Les fils {nm} arrivent chacun dans son trou, F1 à F5.</td></tr>
<tr><td>{q}<b>19 · MISO</b> (D6)</td><td>J2-11, J13-4, J3-4, J4-4</td><td>Fil nu de D6 à H6, puis de H6 à H2 : la <b>barre MISO</b>. Fils {ni} dans H2 à H5.</td></tr>
<tr><td>{r}<b>18 · SCK</b> (D7)</td><td>J2-7, J2-12, J13-2, J3-6, J4-6</td><td>Fil nu de D7 à K7, puis de K7 à K2 : la <b>barre SCK</b>. Fils {nk} dans K2 à K6.</td></tr>
<tr><td>{s}<b>3V3</b> (D15)</td><td>J3, J4, J8, J15, R4</td><td>Fil nu de D15 à H15 : la <b>barre 3,3 V</b>. Fils {n3} dans E15 à H15. J8-1 et J3 se partagent un seul fil (pont P2 → R2).</td></tr>
<tr><td>{t}<b>VIN</b> et le 5 V</td><td>J2-1, J14-1, J7-1, J12 +</td><td>Le 5 V ne passe pas par l'ESP32 : il part du <b>rail 5 V</b> (colonne 24), qui va de J1 à J12. Fils épais {n5} vers VIN, l'écran et les LED.</td></tr>
<tr><td>{u}<b>GND</b></td><td>tout le reste</td><td>Trois rails de masse en fil nu (rangée B, colonne 23, rangée Q) ; chaque connecteur s'y raccroche par un petit pont.</td></tr>
</table>""".format(p=puce("MOSI"), q=puce("MISO"), r=puce("SCK"), s=puce("3V3"), t=puce("5V"), u=puce("GND"), nm=liste("MOSI"), ni=liste("MISO"), nk=liste("SCK"), n3=liste("3V3"), n5=liste("5V"))
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
<p class="petit"><b>Trois sortes de liaisons</b>, toutes au verso :</p>
<ul class="petit" style="margin:0 0 4px 16px;padding:0">
<li><b>Fil nu</b> (trait de couleur cerné de blanc) : fil de cuivre étamé de 0,5 à 0,6 mm, couché le long des trous et soudé à <b>chaque</b> trou qu'il traverse (les petits points).</li>
<li><b>Pont</b> : un fil nu de 2 ou 3 trous entre deux pastilles voisines (ou un pont d'étain si elles se touchent presque).</li>
<li><b>Fil isolé numéroté</b> (courbe avec un numéro) : couché à plat sur le verso, chaque bout dans son propre trou d'entrée, relié à sa broche par un pont (encadré ci-dessous). Il peut passer par-dessus les fils nus : son isolant le protège.</li>
</ul>
<div class="alerte"><b>Avant toute soudure</b>, posez l'ESP32 sur ses deux barrettes femelles et enfoncez celles-ci dans la plaque : la rangée du haut en <b>D1 à D15</b>,
la rangée du bas doit tomber en <b>N1 à N15</b> (9 trous libres entre les deux). Si elle tombe en <b>M</b> (ESP32 plus étroit, 22,9 mm), soudez-la en M :
tout le reste du plan est inchangé : les fils nus et les ponts qui arrivent à une broche de la rangée N (vers J8, J15, et les ponts des fils
{rang_n}) s'allongent d'un trou pour rejoindre la rangée M.</div>
<div class="note"><b>Comment souder un fil isolé sans faux contact.</b> Un fil n'est jamais posé sur une soudure déjà faite.
<b>« entre en C5 »</b> : le bout dénudé (3 mm) passe par le trou libre <b>C5</b> depuis le verso, dépasse de 1 mm au recto (plié, il tient seul),
et se soude au verso ; un <b>pont</b> (2 mm de fil nu, ou une chute de patte de résistance) relie ensuite ce trou à la pastille de la broche.
<b>« enroulé sur la broche »</b> : pas de trou libre à côté (une barrette bordée par un rail) ; le bout dénudé fait un demi-tour serré autour
de la broche qui dépasse au verso, <b>avant</b> de souder. <b>« enroulé sur le fil nu »</b> : de même autour du rail. Tirez doucement sur chaque
fil une fois soudé : il ne doit pas bouger. Quand tout est testé, une goutte de colle chaude sur chaque fil, à 5 mm de sa soudure.</div>
<div class="note"><b>Les sept étapes</b> — Soudez une étape, faites son test, puis passez à la suivante : une erreur se trouve tout de suite. Les fils nus se soudent avant les
fils isolés de la même étape. « Pts » : le nombre de soudures de la ligne. Au total, environ <b>{total} soudures</b>.
La longueur des fils isolés est indicative (coupez 1 cm de plus, dénudez 2 mm à chaque bout).</div>
</div>
<div>
<h2>Les fils qui arrivent sur la même broche</h2>
<p class="petit">Jamais deux fils sur la même broche de l'ESP32 : une broche ne reçoit qu'<b>un seul</b> fil (ou un fil nu), qui file vers une
<b>barre de distribution</b> cachée sous l'ESP32, au verso. Chaque connecteur a ensuite son propre trou sur la barre.</p>
{ponts_partages}
</div>
</div>

<h2 class="saut">Le recto : où poser chaque composant</h2>
<p class="petit legende"><span><b style="color:#e5484d">○</b> broche 1 (ou plot repéré)</span><span>Bleu : bornier à vis</span><span>Noir : barrette mâle</span><span>Gris foncé : barrette femelle de l'ESP32</span>
<span>Pointillés : la carte ESP32 au-dessus (elle déborde de quelques millimètres à gauche, c'est normal : l'antenne reste dégagée)</span></p>
<div class="note" style="margin-bottom:4px"><b>J1</b> : le <b>+</b> à droite (B23), le <b>−</b> à gauche (B21) — marquez le + au feutre rouge sur le bornier. <b>J14</b> est posé à l'envers :
sa broche 1 est à <b>droite</b> (E24), le câble vers la plaque B reste droit (broche 1 face à broche 1). <b>J12</b> : le <b>+</b> à droite (P24).
R3, R4 et C1 se couchent à plat : ils passent sous le bord de l'ESP32.</div>
{svg(recto(), "Recto de la plaque A")}



{corps}
<h2 class="saut">Le verso terminé (pour la vérification finale)</h2>
<p class="petit legende"><span>{puce("5V")}5 V</span><span>{puce("GND")}masse</span><span>{puce("3V3")}3,3 V</span><span>{puce("MOSI")}MOSI (23)</span>
<span>{puce("MISO")}MISO (19)</span><span>{puce("SCK")}SCK (18)</span><span>{puce("DIN")}LED</span><span>{puce("G5")}les autres signaux</span>
<span>Cercles gris : soudures des broches · gros traits : fils isolés épais (22 AWG)</span></p>
{svg(verso(), "Verso de la plaque A")}

<div class="note"><b>Ce plan a été vérifié automatiquement</b> : chaque signal forme un seul morceau, aucun fil nu ne passe par la pastille d'un autre
signal, et aucune pastille ne reçoit deux fils. Les broches TX0, RX0 et le GND du bas de l'ESP32 (N14) sont soudées mais ne reçoivent rien : c'est voulu
(N14 est déjà relié à D14 dans l'ESP32).</div>
</body>
</html>
"""


if __name__ == "__main__":
    (ICI / "plaque-a.html").write_text(page())
    print("✓ plaque-a.html")
