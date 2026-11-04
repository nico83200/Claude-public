# Génère plaque-b.html : le plan trou par trou de la plaque B (le son : deux DFPlayer Mini),
# recto et verso, avec la liste de toutes les soudures, étape par étape, et la même
# vérification automatique que pour la plaque A (plaque_a.py).
#
#   python3 plaque_b.py            (écrit plaque-b.html à côté de ce fichier)
#
# La plaque : 30 × 70 mm, 10 × 24 trous, pastilles des deux côtés, tenue à l'horizontale.
# Coordonnées : colonnes 1 à 24 de gauche à droite VUES DU RECTO, rangées A à J de haut en bas.
import sys
from pathlib import Path

ICI = Path(__file__).parent
RANGS = "ABCDEFGHIJ"
NC, NR = 24, 10


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


# Un DFPlayer Mini, VCC en haut à gauche : la colonne utile (broches 1 à 8, de B à I), puis la
# colonne « mécanique » (broches 16 à 9), qui ne sert qu'à le tenir, sauf sa broche 10 (GND).
UTILE_N = ["VCC", "RX", "TX", "DAC_R", "DAC_L", "SPK_1", "GND", "SPK_2"]
MECA_N = ["BUSY", "USB−", "USB+", "ADKEY2", "ADKEY1", "IO_2", "GND", "IO_1"]


def dfplayer(cid, titre, col, utiles):
    poser(cid + "-U", titre + ", colonne VCC", "femelle", 1, col, utiles, vertical=True, noms=UTILE_N)
    meca = [f"NC-{cid}-{k}" for k in range(8)]
    meca[6] = "GND"
    poser(cid + "-M", titre + ", autre colonne", "femelle", 1, col + 7, meca, vertical=True, noms=MECA_N)


dfplayer("HP", "DFPlayer du haut-parleur", 4, ["5V", "RX-HP", "G16", "NC-HP-a", "NC-HP-b", "SPK1", "GND", "SPK2"])
dfplayer("CQ", "DFPlayer du casque", 13, ["5V", "RX-CQ", "G36", "DACR", "DACL", "NC-CQ-a", "GND", "NC-CQ-b"])
poser("J14", "J14 · vers la plaque A", "male", 1, 22, ["5V", "GND", "G17", "G16", "G2", "G36"], vertical=True)
poser("J5", "J5 · haut-parleur", "bornier", 6, 1, ["SPK1", None, "SPK2"], vertical=True, noms=["1", "", "2"], etape=2)
poser("J6", "J6 · casque", "bornier", 3, 24, ["DACL", None, "DACR", None, "GND"], vertical=True, noms=["1 G", "", "2 D", "", "3 ⏚"], etape=3)
poser("R1", "R1 · 1 kΩ (haut-parleur)", "resistance", 4, 14, ["RX-HP", None, None, None, "G17"], etape=2)
poser("R2", "R2 · 1 kΩ (casque)", "resistance", 2, 14, ["RX-CQ", None, None, None, "G2"], etape=3)
poser("C2", "C2 · 100 µF", "condo", 1, 6, ["5V", "GND"], noms=["+", "−"], etape=1)

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


nu("5V", "A4 A22", 1, "rail 5 V (rangée A)")
nu("5V", "A4 B4", 1, "pont rail 5 V → VCC du DFPlayer haut-parleur")
nu("5V", "A13 B13", 1, "pont rail 5 V → VCC du DFPlayer casque")
nu("5V", "A22 B22", 1, "pont rail 5 V → J14-1")
nu("5V", "A6 B6", 1, "pont rail 5 V → C2 +")
nu("GND", "H4 H24", 1, "rail de masse (rangée H, par les GND des deux DFPlayer)")
nu("GND", "C22 C21 H21", 1, "J14-2 → rail de masse (colonne 21)")
nu("GND", "B7 H7", 1, "C2 − → rail de masse (colonne 7, sous le DFPlayer)")
nu("SPK1", "G4 G1", 2, "SPK_1 → J5 plot 1")
nu("SPK2", "I4 I1", 2, "SPK_2 → J5 plot 2")
nu("RX-CQ", "C13 C14", 3, "pont RX du DFPlayer casque → R2")

fils = []   # (n°, signal, de, à, étape, rôle, épais)
_n = [0]


def fil(signal, a, b, etape, role, epais=False):
    _n[0] += 1
    fils.append((_n[0], signal, h(a), h(b), etape, role, epais))


# Étape 1
fil("G17", "E18", "D22", 2, "R1 → J14-3 (broche 17 de l'ESP32)")
fil("RX-HP", "E14", "C4", 2, "R1 → RX du DFPlayer haut-parleur")
fil("G16", "D4", "E22", 2, "TX du DFPlayer haut-parleur → J14-4 (broche 16)")
fil("G2", "C18", "F22", 3, "R2 → J14-5 (broche 2 de l'ESP32)")
fil("G36", "D13", "G22", 3, "TX du DFPlayer casque → J14-6 (broche 36)")
fil("DACL", "F13", "D24", 3, "DAC_L → J6 plot 1 (voie gauche)")
fil("DACR", "E13", "F24", 3, "DAC_R → J6 plot 2 (voie droite)")

ETAPES = {
    1: ("L'alimentation et les barrettes", "Les quatre barrettes femelles 1 × 8, J14, C2, le rail 5 V et le rail de masse."),
    2: ("Le haut-parleur", "R1, le bornier J5, les ponts SPK et trois fils."),
    3: ("Le casque", "R2, le bornier J6 et quatre fils."),
}
TESTS = {
    1: ["Multimètre en position bip, <b>sans les DFPlayer</b> : pas de bip entre A4 (5 V) et H4 (masse).",
        "Bip entre J14-1 et B4, B13 ; bip entre J14-2 et H4, H13.",
        "Branchez le câble J14 depuis la plaque A et allumez : 5 V entre B4 et H4, et entre B13 et H13. Coupez avant d'enficher un DFPlayer."],
    2: ["Environ 1 kΩ entre J14-3 et C4. Bip entre D4 et J14-4, entre I4 et le plot 2 de J5.",
        "Enfichez le DFPlayer du haut-parleur (sa carte micro-SD dedans), branchez le haut-parleur sur J5, allumez : le son d'accueil."],
    3: ["Environ 1 kΩ entre J14-5 et C13. Bip entre D13 et J14-6, entre F13 et le plot 1 de J6, entre E13 et le plot 2, entre H4 et le plot 3.",
        "Enfichez le DFPlayer du casque (sa carte micro-SD dedans), branchez la prise jack sur J6 : une épreuve « casque » se fait entendre dans le casque."],
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
    groupes = {}
    for t, s in reseau.items():
        groupes.setdefault(s, set()).add(trouve(t))
    for s, g in groupes.items():
        if s.startswith("NC"):
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
        if seul and not s.startswith("NC"):
            erreurs.append(f"{c}-{b} ({nom(t)}, {s}) n'est relié à rien")
    return erreurs, reseau


# --------------------------------------------------------------------------------------------
# Les dessins
# --------------------------------------------------------------------------------------------
P = 10.0                 # un pas de 2,54 mm
LARGE, HAUTE = 70 / 2.54 * P, 30 / 2.54 * P
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
    for x in (MARGE + 5, MARGE + LARGE - 5):
        for y in (MARGE + 4.5, MARGE + HAUTE - 4.5):
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
    # Les deux DFPlayer en transparence (20 × 20 mm), posés sur leurs barrettes femelles.
    for col, txt in ((4, "DFPlayer haut-parleur"), (13, "DFPlayer casque")):
        xa, ya = xy((1, col))
        xb, yb = xy((8, col + 7))
        m = 1.1 / 2.54 * P
        s.append(f'<rect x="{xa - m:.1f}" y="{ya - m:.1f}" width="{xb - xa + 2 * m:.1f}" height="{yb - ya + 2 * m:.1f}" rx="2" '
                 f'fill="#1d4f8a" opacity=".16" stroke="#1d4f8a" stroke-width=".8" stroke-dasharray="3 2"/>')
        s.append(f'<text x="{(xa + xb) / 2:.1f}" y="{yb + 1.05 * P:.1f}" font-size="7" text-anchor="middle" fill="#fff" font-weight="700" '
                 f'paint-order="stroke" stroke="#1d4f8a" stroke-width="2.2">{txt}</text>')
        s.append(f'<text x="{(xa + xb) / 2:.1f}" y="{yb + 1.75 * P:.1f}" font-size="5.5" text-anchor="middle" fill="#fff" '
                 f'paint-order="stroke" stroke="#1d4f8a" stroke-width="1.8">VCC en haut à gauche</text>')
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
            if x1 == x2:   # vertical : les entrées des fils vers le bord le plus proche
                s.append(f'<rect x="{x1 - 1.5 * P:.1f}" y="{y1 - P:.1f}" width="{3 * P:.1f}" height="{y2 - y1 + 2 * P:.1f}" rx="1.5" fill="#2e6fd1" opacity=".93"/>')
            else:
                s.append(f'<rect x="{x1 - P:.1f}" y="{y1 - 1.5 * P:.1f}" width="{x2 - x1 + 2 * P:.1f}" height="{3 * P:.1f}" rx="1.5" fill="#2e6fd1" opacity=".93"/>')
            for t in trous:
                x, y = xy(t)
                s.append(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="3.6" fill="#c9d3dd"/><path d="M{x - 2.4:.1f} {y:.1f}h4.8" stroke="#556" stroke-width="1"/>')
            for t in trous:
                p = pads[t]
                x, y = xy(t)
                s.append(f'<text x="{x + (5 if cid == "J5" else -5):.1f}" y="{y + 2.4:.1f}" font-size="5.5" text-anchor="{"start" if cid == "J5" else "end"}" x2="0" fill="#fff" font-weight="700">{p[1]}</text>')
        elif genre == "resistance":
            s.append(f'<path d="M{x1:.1f} {y1:.1f}H{x2:.1f}" stroke="#aaa" stroke-width="1.2"/>'
                     f'<rect x="{x1 + 1.2 * P:.1f}" y="{y1 - 2.6:.1f}" width="{x2 - x1 - 2.4 * P:.1f}" height="5.2" rx="2.4" fill="#d9b98a" stroke="#8a6a3a" stroke-width=".4"/>')
            for t in trous:
                x, y = xy(t)
                s.append(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="1.6" fill="#aaa"/>')
        elif genre == "condo":
            (xa_, ya_), (xb_, yb_) = xy(trous[0]), xy(trous[1])
            xm = (xa_ + xb_) / 2
            s.append(f'<rect x="{xm - 6.3:.1f}" y="{ya_ + 3:.1f}" width="12.6" height="{4.2 * P:.1f}" rx="3" fill="#2a3f6b"/>'
                     f'<rect x="{xb_ - 1:.1f}" y="{ya_ + 3:.1f}" width="3.4" height="{4.2 * P:.1f}" rx="1.5" fill="#c9d3dd"/>'
                     f'<text transform="translate({xm - 1:.1f} {ya_ + 3 + 2.1 * P:.1f}) rotate(-90)" font-size="5.5" text-anchor="middle" fill="#fff" font-weight="700">C2 100 µF</text>')
            for t in trous:
                x, y = xy(t)
                s.append(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="1.8" fill="#aaa"/>')
            s.append(f'<text x="{xa_:.1f}" y="{ya_ - 4.5:.1f}" font-size="6.5" text-anchor="middle" fill="#fff" font-weight="700">+</text>'
                     f'<text x="{xb_:.1f}" y="{yb_ - 4.5:.1f}" font-size="6.5" text-anchor="middle" fill="#fff" font-weight="700">−</text>')
    # Les noms des connecteurs
    etiquettes = {
        "J14": ("D22", 0, 0, ""), "R1": ("E16", 0, 9, "R1 1 kΩ"), "R2": ("C16", 0, -4.5, "R2 1 kΩ"),
    }
    for cid, (ref, dx, dy, txt) in etiquettes.items():
        x, y = xy(h(ref))
        s.append(f'<text x="{x + dx:.1f}" y="{y + dy:.1f}" font-size="7" text-anchor="middle" fill="#fff" font-weight="700" '
                 f'paint-order="stroke" stroke="#2f6b3c" stroke-width="2">{txt}</text>')
    for ref, ang, txt in (("I22", 0, "J14 → A"), ("F1", 0, "J5 · HP"), ("F24", 0, "J6 · CASQUE")):
        x, y = xy(h(ref))
        if ang:
            s.append(f'<text transform="translate({x - 9:.1f} {y:.1f}) rotate(-90)" font-size="6.5" text-anchor="middle" fill="#fff" font-weight="700">{txt}</text>')
        else:
            s.append(f'<text x="{x:.1f}" y="{y - (24 if ref == "F24" else 13 if ref == "F1" else -2):.1f}" font-size="6.5" text-anchor="middle" fill="#fff" font-weight="700" '
                     f'paint-order="stroke" stroke="#2f6b3c" stroke-width="2">{txt}</text>')
    # Les broches utiles des DFPlayer
    for t, (c, b, _) in pads.items():
        if c.endswith("-U"):
            x, y = xy(t)
            s.append(f'<text x="{x - 4.6:.1f}" y="{y + 1.6:.1f}" font-size="4.4" text-anchor="end" fill="#fde9b0" font-weight="700" '
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
    for ref, dx, dy, txt, sig, et in [("A16", 0, -4.5, "rail 5 V", "5V", 1), ("H16", 0, 10, "rail de masse", "GND", 1)]:
        if etat(et) != "vif":
            continue
        x, y = xy(h(ref), True)
        anc = "middle" if dx == 0 else ("start" if dx > 0 else "end")
        s.append(f'<text x="{x + dx:.1f}" y="{y + dy:.1f}" font-size="6.5" text-anchor="{anc}" font-weight="700" fill="{coul(sig)}" '
                 f'paint-order="stroke" stroke="#fff" stroke-width="2.2">{txt}</text>')
    return s



def svg(elements, titre, haut=46):
    w, hh = LARGE + 2 * MARGE, HAUTE + 2 * MARGE
    return (f'<svg viewBox="0 0 {w:.1f} {hh:.1f}" style="display:block;margin:0 auto;width:100%;max-height:{haut}mm" role="img" aria-label="{titre}">' + "".join(elements) + "</svg>")


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


def pads_de(cid):
    return [t for t, p in pads.items() if p[0] == cid]


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
                desc = f"barrette femelle 1 × {len(pads_de(cid))}"
            elif genre == "male":
                desc = f"barrette mâle 1 × {len(trous)}"
            elif genre == "bornier":
                desc = f"bornier à vis {len(trous)} plots, entrées des fils vers le bord"
            elif genre == "resistance":
                desc = "à plat sous le DFPlayer du casque, pattes à 4 trous d'écart"
            else:
                desc = "couché sous le DFPlayer du haut-parleur, patte − (bande claire) en B7"
            places = ", ".join(f"{nom(t)} <span class='g'>{pads[t][1]}</span>" if genre in ("bornier", "condo")
                               else nom(t) for t in trous)
            if genre in ("male",):
                places = f"{nom(trous[0])} (broche 1) à {nom(trous[-1])}"
            if genre == "femelle":
                places = f"{nom(trous[0])} à {nom(trous[-1])}"
            lignes.append(f"<tr><td class='q'>{len(trous)}</td><td><b>{t_}</b> — {desc}</td><td>{places}</td></tr>")
            n_pts += len(trous)
        for sig, trous, et, titre_nu in nus:
            if et != e:
                continue
            libres = [t for t in trous if t not in pads]
            lignes.append(f"<tr><td class='q'>{len(trous)}</td><td>{puce(sig)}<b>Fil nu</b> : {titre_nu}</td>"
                          f"<td>{nom(trous[0])} → {nom(trous[-1])} <span class='g'>({len(trous)} trous"
                          f"{', dont ' + str(len(trous) - len(libres)) + ' déjà soudés' if len(libres) < len(trous) else ''})</span></td></tr>")
            n_pts += len(libres)
        for n, sig, a, b, et, role, epais in fils:
            if et != e:
                continue
            lignes.append(f"<tr><td class='q'>2</td><td>{puce(sig)}<b>Fil {n}</b> · {role}</td>"
                          f"<td>{nom(a)} → {nom(b)} <span class='g'>· {'22 AWG' if epais else '26 à 30 AWG'} · ≈ {longueur(a, b)} mm</span></td></tr>")
            n_pts += 2
        total += n_pts
        tests = "".join(f"<li>{t}</li>" for t in TESTS[e])
        out.append(f"""<div class="etape saut"><h2>Étape {e} · {titre} <span class="g">— environ {n_pts} soudures</span></h2>
<div class="cote"><div>{svg(verso(e), "Verso, étape " + str(e), 46 if e == 1 else 82)}
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
    return f"""<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Plaque B trou par trou</title>
<link rel="stylesheet" href="commun.css">
<style>
  @page {{{{ size: A4 landscape; margin: 10mm 11mm 12mm; }}}}
  body {{{{ font-size: 9.2pt; }}}}
  .couverture {{{{ background: var(--vert); color: #fff; border-radius: 10px; padding: 12px 18px; margin-bottom: 8px; }}}}
  .couverture h1 {{{{ color: #fff; margin: 0; font-size: 21pt; }}}}
  .couverture p {{{{ color: #f2d9a6; margin: 3px 0 0; }}}}
  h2 {{{{ break-after: avoid; border-bottom: 2px solid var(--or); padding-bottom: 2px; margin: 10px 0 5px; }}}}
  h3 {{{{ break-after: avoid; margin: 8px 0 3px; }}}}
  .saut {{{{ break-before: page; }}}}
  table {{{{ width: 100%; border-collapse: collapse; font-size: 8.1pt; }}}}
  th {{{{ background: var(--vert); color: #fff; text-align: left; padding: 3px 5px; }}}}
  td {{{{ border-bottom: 1px solid var(--filet); padding: 2.2px 5px; vertical-align: top; }}}}
  tr {{{{ break-inside: avoid; }}}}
  td.q, th.q {{{{ width: 26px; text-align: right; color: var(--gris); }}}}
  .g {{{{ color: var(--gris); font-weight: 400; }}}}
  .c {{{{ display: inline-block; width: 9px; height: 9px; border-radius: 2px; border: 1px solid #0003; vertical-align: -1px; margin-right: 4px; }}}}
  .note {{{{ background: #fbf7ee; border-left: 4px solid var(--or); padding: 5px 10px; margin: 5px 0; font-size: 8.6pt; }}}}
  .alerte {{{{ background: #fbeceb; border-left: 4px solid var(--rouge); padding: 5px 10px; margin: 5px 0; font-size: 8.6pt; }}}}
  .test {{{{ background: #eef3ec; border-left: 4px solid var(--vert); padding: 4px 10px; margin: 5px 0 10px; font-size: 8.4pt; }}}}
  .test ul {{{{ margin: 2px 0 0 16px; padding: 0; }}}}
  .cote {{{{ display: grid; grid-template-columns: 1.25fr 1fr; gap: 12px; align-items: start; }}}}
  .cote table {{{{ font-size: 7.6pt; }}}}
  .deux {{{{ display: grid; grid-template-columns: 1.15fr 1fr; gap: 12px; align-items: start; }}}}
  .petit {{{{ font-size: 8.2pt; margin: 2px 0 5px; }}}}
  .legende span {{{{ margin-right: 12px; white-space: nowrap; }}}}
  svg text {{{{ font-family: "Source Sans", sans-serif; }}}}
</style>
</head>
<body>
<div class="couverture"><h1>Plaque B, trou par trou</h1>
<p>Le son : les deux DFPlayer Mini sur la plaque 30 × 70 mm (10 × 24 trous) — chaque composant, chaque fil, chaque soudure, en trois étapes testées une à une.</p></div>

<div class="deux">
<div>
<h2>Comment lire ce plan</h2>
<p class="petit">Comme pour la plaque A : <b>plaque à l'horizontale</b>, <b>colonnes 1 à 24</b> de gauche à droite, <b>rangées A à J</b> de haut en bas.
Une coordonnée comme <b>C13</b> désigne toujours le même trou, <b>vu du recto</b> (le côté des composants). Le verso est dessiné tel que vous le voyez
en retournant la plaque comme une page de livre : la colonne 1 passe à droite. Écrivez « 1 », « 24 », « A » et « J » dans les coins des deux faces.
Comptez les trous de votre plaque : <b>10 rangées de 24</b>. Si elle en a d'autres, ne commencez pas : dites-le.</p>
<p class="petit"><b>Fil nu</b> : cuivre étamé de 0,5 à 0,6 mm, soudé à chaque trou qu'il traverse. <b>Fil isolé numéroté</b> : soudé sur les deux pastilles
indiquées, couché à plat au verso (il peut passer par-dessus les fils nus).</p>
<div class="alerte"><b>Le sens des DFPlayer</b> : chaque module se pose <b>VCC en haut à gauche</b> (le nom des broches est imprimé au dos) ; la colonne
VCC, RX, TX… SPK_2 descend alors de B à I. Seule compte la place de VCC : la fente micro-SD tombe en haut ou en bas selon le modèle, peu importe.
<br><b>Avant toute soudure</b>, enfoncez les quatre barrettes femelles dans la plaque avec les modules dessus : la colonne VCC en <b>4</b> et en <b>13</b>,
l'autre colonne doit tomber en <b>11</b> et en <b>20</b> (6 trous libres entre les deux). Si elle tombe en <b>10</b> et en <b>19</b> (modules plus étroits),
soudez-la là : rien d'autre ne change, cette colonne ne porte aucun fil.</div>
<div class="note"><b>Les trois étapes</b> — Soudez une étape, faites son test, puis passez à la suivante. Les fils nus se soudent avant les fils isolés
de la même étape. Au total, environ <b>{total} soudures</b>. Longueurs des fils isolés indicatives (coupez 1 cm de plus, dénudez 2 mm à chaque bout).</div>
</div>
<div>
<h2>Ce qui va où</h2>
<table><tr><th>Broche</th><th>DFPlayer du haut-parleur (colonne 4)</th><th>DFPlayer du casque (colonne 13)</th></tr>
<tr><td>{puce("5V")}VCC (B)</td><td colspan="2">Rail 5 V de la rangée A, par un pont ; le 5 V arrive par J14-1</td></tr>
<tr><td>{puce("G17")}RX (C)</td><td>R1 (1 kΩ) puis J14-3 (broche 17)</td><td>R2 (1 kΩ) puis J14-5 (broche 2)</td></tr>
<tr><td>{puce("G16")}TX (D)</td><td>J14-4 (broche 16)</td><td>J14-6 (broche 36)</td></tr>
<tr><td>{puce("G5")}DAC_R, DAC_L (E, F)</td><td>libres</td><td>J6 plots 2 et 1 (voies droite et gauche)</td></tr>
<tr><td>{puce("G5")}SPK_1, SPK_2 (G, I)</td><td>J5 plots 1 et 2, par deux fils nus</td><td>libres</td></tr>
<tr><td>{puce("GND")}GND (H)</td><td colspan="2">Rail de masse de la rangée H, qui passe par les GND des deux modules et rejoint J14-2 et J6 plot 3</td></tr>
</table>
<p class="petit">Le 5 V et la masse n'arrivent que par J14 : un seul câble à six fils depuis la plaque A. Les résistances R1 et R2 protègent l'entrée RX
des DFPlayer ; elles se couchent à plat <b>sous le DFPlayer du casque</b>, et C2 sous celui du haut-parleur : posez-les avant d'enficher les modules.</p>
</div>
</div>

<h2 class="saut">Le recto : où poser chaque composant</h2>
<p class="petit legende"><span><b style="color:#e5484d">○</b> broche 1 (ou plot 1)</span><span>Bleu : bornier à vis (entrées des fils vers le bord)</span><span>Noir : barrette mâle</span>
<span>Gris foncé : barrettes femelles des DFPlayer</span><span>Pointillés : les modules au-dessus</span></p>
<div class="note" style="margin-bottom:4px"><b>J5</b> (haut-parleur) : le plot 1 en haut (G1), entrées des fils vers la gauche. <b>J6</b> (casque) : plot 1 (voie gauche) en haut (D24),
plot 3 (masse) en bas (H24), entrées vers la droite. <b>J14</b> : broche 1 en haut (B22). R1, R2 et C2 se couchent à plat sous les modules.</div>
{svg(recto(), "Recto de la plaque B", 150)}

{corps}
<h2 class="saut">Le verso terminé (pour la vérification finale)</h2>
<p class="petit legende"><span>{puce("5V")}5 V</span><span>{puce("GND")}masse</span><span>{puce("G5")}les signaux</span></p>
{svg(verso(), "Verso de la plaque B", 130)}
<div class="note"><b>Ce plan a été vérifié automatiquement</b> : chaque signal forme un seul morceau, aucun fil nu ne passe par la pastille d'un autre
signal, et aucune pastille ne reçoit deux fils. Les broches libres des DFPlayer sont soudées (elles tiennent le module) mais ne reçoivent rien.</div>
</body>
</html>
"""


if __name__ == "__main__":
    (ICI / "plaque-b.html").write_text(page())
    print("✓ plaque-b.html")
