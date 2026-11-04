# Génère plaque-c.html : le plan trou par trou de la plaque C (le bus I2C : onze barrettes
# 1 × 4 reliées broche à broche), recto et verso, avec ses soudures étape par étape et la même
# vérification automatique que pour les plaques A et B.
#
#   python3 plaque_c.py            (écrit plaque-c.html à côté de ce fichier)
#
# La plaque : 20 × 80 mm, 8 × 28 trous, pastilles des deux côtés, tenue à l'horizontale.
# Coordonnées : colonnes 1 à 28 de gauche à droite VUES DU RECTO, rangées A à H de haut en bas.
import sys
from pathlib import Path

ICI = Path(__file__).parent
RANGS = "ABCDEFGH"
NC, NR = 28, 8


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


# Onze barrettes 1 × 4 en travers, une colonne libre entre deux : J15 (l'arrivée depuis la
# plaque A), puis les départs J11a à J11h (les modules) et J11i, J11j (la réserve).
BUS = ["3V3", "GND", "SDA", "SCL"]
BUS_N = ["3,3 V", "GND", "SDA", "SCL"]
DEPARTS = [("J15", "J15 · arrivée depuis la plaque A", 1)] + [
    ("J11" + l, f"J11{l} · {m}", 1) for l, m in zip("abcdefghij", [
        "clavier (PCF8574)", "tiroir (PCA9685)", "boutons (PCF8574)", "forge (BME280)", "enclume (QMC5883L)",
        "horloge (HT16K33)", "œil (APDS-9960)", "couleur (TCS34725)", "réserve", "réserve"])]
for k, (cid, titre, et) in enumerate(DEPARTS):
    poser(cid, titre, "male", 2, 4 + 2 * k, BUS, vertical=True, noms=BUS_N, etape=et)

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


nu("3V3", "C4 C24", 2, "rail 3,3 V (rangée C, broches 1)")
nu("GND", "D4 D24", 2, "rail de masse (rangée D, broches 2)")
nu("SDA", "E4 E24", 2, "rail SDA (rangée E, broches 3)")
nu("SCL", "F4 F24", 2, "rail SCL (rangée F, broches 4)")

fils = []   # (n°, signal, de, à, étape, rôle, épais)
_n = [0]


def fil(signal, a, b, etape, role, epais=False):
    _n[0] += 1
    fils.append((_n[0], signal, h(a), h(b), etape, role, epais))


# Étape 1
ETAPES = {
    1: ("Les onze barrettes", "J15 et J11a à J11j, en travers, broche 1 (3,3 V) en haut, une colonne libre entre deux."),
    2: ("Les quatre rails", "Quatre fils nus, un par rangée de broches, de J15 à J11j."),
}
TESTS = {
    1: ["Les onze barrettes sont droites et à la même hauteur ; la broche 1 de chacune est en rangée C (point de peinture du même côté)."],
    2: ["Bip entre C4 (J15-1) et C24 (J11j-1) ; de même entre D4 et D24, E4 et E24, F4 et F24.",
        "<b>Pas de bip</b> entre deux rails voisins (C et D, D et E, E et F), ni entre C et F.",
        "Branchez le câble J15 depuis la plaque A et allumez : 3,2 à 3,4 V entre C et D sur un départ quelconque.",
        "Branchez le clavier sur J11a : le journal série de l'ESP32 trouve le module 0x20. Puis les autres modules, un par un."],
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
LARGE, HAUTE = 80 / 2.54 * P, 20 / 2.54 * P
X0 = (LARGE - (NC - 1) * P) / 2
Y0 = (HAUTE - (NR - 1) * P) / 2
MARGE = 16

COULEURS = {"SDA": "#d4a20c", "SCL": "#2f9a4a", "5V": "#d23b2e", "GND": "#222", "3V3": "#f08a24", "MOSI": "#2e6fd1", "MISO": "#2f9a4a", "SCK": "#8a5a2b",
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
    for cid, titre, genre, trous, _ in composants:
        x, y = xy(trous[0])
        court = cid.replace("J11", "")
        s.append(f'<text x="{x:.1f}" y="{y - 9:.1f}" font-size="{6.5 if cid == "J15" else 7.5}" text-anchor="middle" fill="#fff" font-weight="700" '
                 f'paint-order="stroke" stroke="#2f6b3c" stroke-width="2">{court}</text>')
        if cid == "J11i":
            s.append(f'<text x="{x + P:.1f}" y="{xy(trous[-1])[1] + 11:.1f}" font-size="5.5" text-anchor="middle" fill="#fde9b0" font-weight="700">réserve</text>')
    for t in [h("C4"), h("D4"), h("E4"), h("F4")]:
        x, y = xy(t)
        s.append(f'<text x="{x - 6:.1f}" y="{y + 1.8:.1f}" font-size="5" text-anchor="end" fill="#fde9b0" font-weight="700" '
                 f'paint-order="stroke" stroke="#2f6b3c" stroke-width="1.6">{pads[t][1]}</text>')
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
    for ref, dx, dy, txt, sig, et in [("B14", 0, 2, "rail 3,3 V (C)", "3V3", 2), ("G14", 0, 2, "SDA (E) · SCL (F) · masse (D)", "SDA", 2)]:
        if etat(et) != "vif":
            continue
        x, y = xy(h(ref), True)
        anc = "middle" if dx == 0 else ("start" if dx > 0 else "end")
        s.append(f'<text x="{x + dx:.1f}" y="{y + dy:.1f}" font-size="6.5" text-anchor="{anc}" font-weight="700" fill="{coul(sig)}" '
                 f'paint-order="stroke" stroke="#fff" stroke-width="2.2">{txt}</text>')
    return s



def svg(elements, titre, haut=72):
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
<div class="cote"><div>{svg(verso(e), "Verso, étape " + str(e), 80)}
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
    lignes = "".join(f"<tr><td><b>{cid}</b></td><td>{nom(t[0])} à {nom(t[-1])}</td><td>{titre.split(' · ')[1]}</td></tr>"
                     for cid, titre, _, t, _ in composants)
    return f"""<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Plaque C trou par trou</title>
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
<div class="couverture"><h1>Plaque C, trou par trou</h1>
<p>Le bus I2C : onze barrettes 1 × 4 reliées broche à broche sur la plaque 20 × 80 mm (8 × 28 trous), en deux étapes.</p></div>

<div class="deux">
<div>
<h2>Comment lire ce plan</h2>
<p class="petit">Comme pour les plaques A et B : <b>plaque à l'horizontale</b>, <b>colonnes 1 à 28</b> de gauche à droite, <b>rangées A à H</b> de haut en bas,
coordonnées <b>vues du recto</b> (le côté des barrettes). Au verso, la colonne 1 passe à droite. Comptez les trous de votre plaque : <b>8 rangées de 28</b>.
Si elle en a d'autres, gardez le principe (barrettes en travers, une colonne libre entre deux, quatre rails) et centrez-les.</p>
<p class="petit">Chaque barrette porte les mêmes quatre signaux, dans le même ordre, de haut en bas : <b>1 · 3,3 V</b> (rangée C), <b>2 · masse</b> (D),
<b>3 · SDA</b> (E), <b>4 · SCL</b> (F). Quatre fils nus, un par rangée, les relient toutes : un module branché sur n'importe quel départ est sur le bus.</p>
<div class="alerte"><b>Deux rails voisins ne sont qu'à 2,5 mm</b> : tendez bien chaque fil nu, soudez-le à chaque trou, et contrôlez qu'aucune goutte d'étain
ne touche le rail d'à côté. Les colonnes 1 à 3 et 25 à 28 restent libres : les trous des coins servent aux entretoises.</div>
<div class="note"><b>Les deux étapes</b> — Les barrettes d'abord (le rail passe ensuite par leurs pastilles déjà soudées), puis les rails.
Au total, environ <b>{total} soudures</b>. La plaque se fixe sous le couvercle, <b>barrettes vers le bas</b>, sur deux entretoises.</div>
</div>
<div>
<h2>Les onze barrettes</h2>
<table><tr><th>Barrette</th><th>Trous</th><th>Module branché</th></tr>{lignes}</table>
</div>
</div>

<h2 class="saut">Le recto : où poser chaque barrette</h2>
<p class="petit legende"><span><b style="color:#e5484d">○</b> broche 1 (3,3 V), toutes du même côté</span><span>Noir : barrette mâle 1 × 4</span></p>
{svg(recto(), "Recto de la plaque C", 120)}

{corps}
<div class="note"><b>Ce plan a été vérifié automatiquement</b> : chaque signal forme un seul morceau et aucun fil nu ne touche la pastille d'un autre signal.</div>
</body>
</html>
"""


if __name__ == "__main__":
    (ICI / "plaque-c.html").write_text(page())
    print("✓ plaque-c.html")
