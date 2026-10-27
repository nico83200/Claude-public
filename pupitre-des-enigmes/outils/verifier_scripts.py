#!/usr/bin/env python3
"""Vérifie les scripts du pupitre (mondes/<monde>/pupitre/*.json).

    python3 outils/verifier_scripts.py

Pour chaque mission, contrôle que :
- chaque réplique dite (« dire ») existe dans audio/mission-N.json (ou dans les
  répliques du pupitre avec « pupitre »: true) ;
- chaque bruitage (« son », « ambiance ») existe dans audio/sons.json du monde ;
- chaque carte citée dans les règles et l'accusation est déclarée dans « cartes » ;
- les rôles ont une réplique d'arrivée, et les épreuves un type connu ;
- les boutons nommés (épreuves « ordre », « rythme », dynamo) existent ;
- les répliques des lecteurs renommés (« zones ») et de la dynamo existent.
Renvoie un code d'erreur s'il y a un problème (pratique avant de copier la carte SD).
"""
import json
import sys
from pathlib import Path

RACINE = Path(__file__).resolve().parent.parent
TYPES_EPREUVES = {"code", "lettres", "choix", "ensemble", "rythme", "sequence", "immobile", "molette_zero", "rondes",
                  "energie", "traitre", "appui", "ordre", "voyants", "cle", "capteur"}
COULEURS = {"rouge", "vert", "bleu", "jaune", "orange", "ambre", "blanc", "violet", "cyan", "rose", "eteint"}
MATERIEL = {"fente", "plateau", "clavier", "voyants", "couleur", "cle", "boutons", "molette", "gros_bouton", "casque", "anneau",
            "gestes", "aimant", "souffle", "afficheur", "tiroir"}
GESTES = {"haut", "bas", "gauche", "droite"}
CAPTEURS = {"souffle", "aimant", "eclaire"}
BOUTONS = {"ROUGE", "BLEU", "JAUNE", "VERT", "GROS"}
CLES_CARTES = ("lieu", "qui", "quoi", "role", "combiner")


def numeros(fichier, cle):
    d = json.loads(Path(fichier).read_text(encoding="utf-8"))
    return {x["n"] for x in d[cle]}


def verifier_mission(dossier_monde, monde, mission, erreurs):
    nom = f"{monde['id']}/{mission['script']}"
    script = json.loads((dossier_monde / "pupitre" / mission["script"]).read_text(encoding="utf-8"))
    voix = numeros(dossier_monde / "audio" / f"mission-{mission['numero']}.json", "lignes")
    voix_pupitre = numeros(RACINE / "console" / "audio" / "repliques.json", "lignes")
    sons = numeros(dossier_monde / "audio" / "sons.json", "sons")
    sons_pupitre = numeros(RACINE / "console" / "audio" / "sons.json", "sons")
    cartes = set(script.get("cartes", {})) | {monde["carte"], monde.get("aide", "")}

    def erreur(texte):
        erreurs.append(f"{nom} : {texte}")

    # Le DFPlayer ne joue que les fichiers 001 à 255 d'un dossier.
    for n in sorted(voix | sons):
        if not 1 <= n <= 255:
            erreur(f"réplique ou son n° {n} : le lecteur audio ne joue que les numéros 1 à 255")

    def dire(n, pupitre=False):
        if not isinstance(n, int):
            return
        if n not in (voix_pupitre if pupitre else voix):
            erreur(f"réplique {n} introuvable{' (pupitre)' if pupitre else ''}")

    def carte(c):
        for x in c if isinstance(c, list) else [c]:
            if x not in ("*", "-") and x not in cartes:
                erreur(f"carte « {x} » non déclarée")

    def parcourir(x, chemin=""):
        if isinstance(x, list):
            for i, y in enumerate(x):
                parcourir(y, f"{chemin}[{i}]")
            return
        if not isinstance(x, dict):
            return
        pupitre = bool(x.get("pupitre"))
        if "dire" in x:
            for n in x["dire"] if isinstance(x["dire"], list) else [x["dire"]]:
                dire(n, pupitre)
        if isinstance(x.get("son"), int) and "dossier" not in x:
            if x["son"] not in (sons_pupitre if pupitre else sons):
                erreur(f"son {x['son']} introuvable ({chemin})")
        if isinstance(x.get("ambiance"), int) and x["ambiance"] and x["ambiance"] not in sons:
            erreur(f"ambiance {x['ambiance']} introuvable")
        if isinstance(x.get("couleur"), str) and not x["couleur"].startswith("#") and x["couleur"] not in COULEURS \
                and "quand" not in chemin.split(".")[-1:]:
            erreur(f"couleur inconnue « {x['couleur']} » ({chemin})")
        if "quand" in x:
            for k in CLES_CARTES:
                if k in x["quand"]:
                    carte(x["quand"][k])
        if "type" in x and "solution" in x or x.get("type") in TYPES_EPREUVES or "epreuve" in chemin.split(".")[-1:]:
            if x.get("type") not in TYPES_EPREUVES:
                erreur(f"type d'épreuve inconnu « {x.get('type')} » ({chemin})")
            for b in x.get("ordre", []) + ([x["bouton"]] if "bouton" in x else []):
                if b not in BOUTONS:
                    erreur(f"bouton inconnu « {b} » ({chemin})")
            for g in x.get("gestes", []):
                if g not in GESTES:
                    erreur(f"geste inconnu « {g} » ({chemin})")
            if x.get("type") == "capteur" and x.get("capteur") not in CAPTEURS:
                erreur(f"capteur inconnu « {x.get('capteur')} » ({chemin})")
            boucle = x.get("boucle_casque")
            if boucle and boucle.get("dossier") == script.get("dossier_sons") and boucle.get("n") not in sons:
                erreur(f"son {boucle.get('n')} introuvable (boucle_casque, {chemin})")
        for k, v in x.items():
            if k in ("voix",) and chemin == "":
                continue
            parcourir(v, f"{chemin}.{k}")

    parcourir(script)
    for k in ("appel", "gratuit", "payant", "fin"):
        dire(script.get("aide", {}).get(k))
    for b in script.get("aide", {}).get("blocages", []):
        for n in b["niveaux"]:
            dire(n)
    acc = script.get("accusation", {})
    for k in ("qui", "ou", "quoi"):
        if k in acc:
            carte(acc[k])
    for k, v in acc.get("voix", {}).items():
        for n in v if isinstance(v, list) else [v]:
            dire(n)
    for r in script.get("fins", {}).get("rangs", []):
        dire(r["dire"])
    for m in script.get("materiel", []):
        if m not in MATERIEL:
            erreur(f"élément de « materiel » inconnu : « {m} »")
    if len(script.get("panneau", {}).get("voyants", [])) > 10:
        erreur("panneau : 10 voyants au plus")
    for k in ("dire_lieu", "dire_objet", "dire_perso"):
        dire(script.get("zones", {}).get(k))
    if len(script.get("zones", {}).get("noms", ["", "", ""])) != 3:
        erreur("zones.noms : il faut trois noms (gauche, centre, droite)")
    dynamo = script.get("dynamo")
    if dynamo:
        dire(dynamo.get("noir"))
        if dynamo.get("bouton", "GROS") not in BOUTONS:
            erreur(f"dynamo : bouton inconnu « {dynamo.get('bouton')} »")
    dire(script.get("pouvoir_epuise"))
    dire(script.get("roles_demande"))
    for rid, r in script.get("roles", {}).items():
        carte(rid)
        dire(r.get("dire"))
    for v in (script.get("soupcon", {}).get("annulation", {}).get("dire"), script.get("soupcon", {}).get("alerte", {}).get("dire")):
        dire(v)
    return len(script.get("regles", []))


def main():
    erreurs = []
    for dossier in sorted((RACINE / "mondes").iterdir()):
        fichier = dossier / "pupitre" / "monde.json"
        if not fichier.exists():
            continue
        monde = json.loads(fichier.read_text(encoding="utf-8"))
        avant = len(erreurs)
        accueil = monde.get("accueil", {})
        for m in monde["missions"]:
            n = verifier_mission(dossier, monde, m, erreurs)
            etat = "✓" if len(erreurs) == avant else "✗"
            print(f"{etat} {monde['nom']} · mission {m['numero']} : {n} règles")
        if accueil and accueil.get("dire") not in numeros(dossier / "audio" / f"mission-{monde['missions'][0]['numero']}.json", "lignes"):
            erreurs.append(f"{monde['id']}/monde.json : réplique d'accueil {accueil.get('dire')} introuvable")
    for e in dict.fromkeys(erreurs):  # sans doublons, dans l'ordre
        print("  ✗", e)
    sys.exit(1 if erreurs else 0)


if __name__ == "__main__":
    main()
