"""Rejoue une partie de test écrite pour trois zones (pose/retire QUI|OU|QUOI) avec les gestes du
pupitre à deux lecteurs : la fente et le lecteur à plat (commandes « fente » et « plateau »).

    python3 deux_lecteurs.py partie.txt > partie-2-lecteurs.txt

Les joueurs font ce que la fiche leur dit : le lieu, le personnage, l'aide et le rôle vont dans la
fente, l'objet sur le plateau (mission 4 : l'objet de la main gauche dans la fente). Une carte qui
doit entrer dans un lecteur occupé en fait sortir la précédente ; le lieu reste en mémoire."""
import json
import pathlib
import re
import sys

JEU = pathlib.Path(__file__).resolve().parents[2]

types = {}                                   # identifiant de carte -> type, d'après le script
tient = {"fente": None, "plateau": None}     # lecteur -> (zone de la partie, carte)
sortie = []
dernier = None                               # la dernière carte posée
suspect = None                               # (lecteur, zone, carte) écartée de la fente par un lieu


def charger_cartes(monde):
    for script in (JEU / "mondes").glob(f"*/pupitre/mission-{monde[1:]}.json"):
        types.update({k: v["type"] for k, v in json.loads(script.read_text())["cartes"].items()})


def type_de(carte):
    if re.fullmatch(r"M\d+", carte):
        return "monde"
    if re.fullmatch(r"A\d+", carte):
        return "aide"
    return types.get(carte, "role")


def geste(lecteur, carte, note):
    sortie.append(f"{lecteur} {carte}   # {note}")


def lecteur_pour(zone, carte):
    """Le lecteur où va la carte, après en avoir retiré ce qui doit l'être."""
    global suspect
    t = type_de(carte)
    # La zone est déjà tenue : on change de carte sur ce lecteur.
    for l in tient:
        if tient[l] and tient[l][0] == zone and not (t == "lieu" and l == "plateau"):
            geste(l, "-", f"(on reprend {tient[l][1]})")
            tient[l] = None
            return l
    if t in ("lieu", "perso", "monde", "aide"):
        voulu = "fente"
    elif t == "objet":
        voulu = "fente" if zone == "QUI" else "plateau"
    else:
        # Un rôle va dans la fente ; si elle tient le lieu qu'on vient d'y glisser (une épreuve
        # peut être en cours), on le pose sur le plateau.
        voulu = "fente" if tient["fente"] is None else "plateau"
        if tient["fente"] and tient["fente"][0] == "OU" and dernier != tient["fente"][1]:
            voulu = "fente"
    if tient[voulu] is not None:
        z, ancien = tient[voulu]
        if z == "OU" or type_de(ancien) == "role" or t in ("lieu", "objet", "aide"):
            geste(voulu, "-", f"(on reprend {ancien})")
            tient[voulu] = None
            if t == "lieu" and type_de(ancien) == "perso":
                suspect = (voulu, z, ancien)
        else:
            autre = "plateau" if voulu == "fente" else "fente"
            if tient[autre] is not None:
                sys.exit(f"impossible : {zone} {carte} alors que fente={tient['fente']} plateau={tient['plateau']}")
            voulu = autre
    return voulu


def traduire(ligne):
    global dernier, suspect
    m = re.match(r"\s*(pose|retire)\s+(QUI|OU|QUOI)\s*(\S*)", ligne)
    if not m:
        # Avant une accusation, le suspect écarté par un lieu revient dans la fente.
        if ligne.strip() == "bouton GROS" and suspect and not any(c and c[0] == "QUI" for c in tient.values()):
            l, z, carte = suspect
            if tient[l]:
                geste(l, "-", f"(on reprend {tient[l][1]} : le lieu reste en mémoire)")
            geste(l, carte, f"(on remet {carte})")
            tient[l] = (z, carte)
            suspect = None
        sortie.append(ligne)
        return
    commande, zone, carte = m.groups()
    if zone == "QUI":
        suspect = None
    if commande == "pose":
        if type_de(carte) == "monde" and not types:
            charger_cartes(carte)
        l = lecteur_pour(zone, carte)
        tient[l] = (zone, carte)
        dernier = carte
        geste(l, carte, ligne.strip())
        return
    for l in tient:
        if tient[l] and tient[l][0] == zone:
            tient[l] = None
            geste(l, "-", ligne.strip())
            return
    sortie.append(f"# {ligne.strip()} (carte déjà reprise)")


for ligne in pathlib.Path(sys.argv[1]).read_text().splitlines():
    traduire(ligne)
print("\n".join(sortie))
