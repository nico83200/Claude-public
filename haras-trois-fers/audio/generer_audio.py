#!/usr/bin/env python3
"""Génère les voix et les bruitages du jeu avec l'API ElevenLabs.

Les fichiers sont rangés comme le DFPlayer Mini les attend :
    sd/01/001.mp3, sd/01/002.mp3, ...   (voix de la mission 1)
    sd/99/001.mp3, ...                  (bruitages communs)

Utilisation :
    export ELEVENLABS_API_KEY=sk_...
    python3 generer_audio.py mission-1.json          # toutes les répliques
    python3 generer_audio.py mission-1.json --seulement 120-129
    python3 generer_audio.py --sfx                   # les bruitages
    python3 generer_audio.py mission-1.json --essai  # liste sans rien générer

Un fichier déjà présent n'est pas régénéré (sauf avec --forcer).
"""
import argparse
import json
import os
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

API = "https://api.elevenlabs.io/v1"
FORMAT = "mp3_44100_128"
ICI = Path(__file__).resolve().parent


def charger(chemin):
    with open(chemin, encoding="utf-8") as f:
        return json.load(f)


def plage(texte):
    """'120-129,135' -> {120, ..., 129, 135}"""
    numeros = set()
    for morceau in texte.split(","):
        debut, _, fin = morceau.partition("-")
        numeros.update(range(int(debut), int(fin or debut) + 1))
    return numeros


def appeler(url, corps, cle):
    requete = urllib.request.Request(
        f"{url}?output_format={FORMAT}",
        data=json.dumps(corps).encode("utf-8"),
        headers={"xi-api-key": cle, "Content-Type": "application/json", "Accept": "audio/mpeg"},
    )
    for essai in range(4):
        try:
            with urllib.request.urlopen(requete, timeout=120) as reponse:
                return reponse.read()
        except urllib.error.HTTPError as erreur:
            if erreur.code == 429 or erreur.code >= 500:
                time.sleep(2 ** (essai + 1))
                continue
            sys.exit(f"Erreur {erreur.code} : {erreur.read().decode(errors='replace')}")
        except urllib.error.URLError:
            time.sleep(2 ** (essai + 1))
    sys.exit("Échec après 4 essais.")


def taches_voix(fichier, numeros):
    mission = charger(fichier)
    casting = charger(ICI / "voix.json")
    modele = casting["modele_par_defaut"]
    manquantes = set()
    for ligne in mission["lignes"]:
        if numeros and ligne["n"] not in numeros:
            continue
        voix = casting["voix"][ligne["voix"]]
        if not voix["voice_id"]:
            manquantes.add(ligne["voix"])
            continue
        corps = {
            "text": ligne["texte"],
            "model_id": voix.get("modele", modele),
            "voice_settings": voix["reglages"],
        }
        url = f"{API}/text-to-speech/{voix['voice_id']}"
        yield mission["dossier"], ligne["n"], f"{ligne['voix']} : {ligne['texte'][:60]}", url, corps
    if manquantes:
        print(f"⚠️  voice_id manquant dans voix.json pour : {', '.join(sorted(manquantes))}")


def taches_sfx(numeros):
    sfx = charger(ICI / "sfx.json")
    for son in sfx["sons"]:
        if numeros and son["n"] not in numeros:
            continue
        corps = {"text": son["prompt"], "duration_seconds": max(son["duree"], 0.5), "prompt_influence": 0.5}
        yield sfx["dossier"], son["n"], f"bruitage : {son['nom']}", f"{API}/sound-generation", corps


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("mission", nargs="?", help="fichier de répliques, ex. mission-1.json")
    parser.add_argument("--sfx", action="store_true", help="générer les bruitages de sfx.json")
    parser.add_argument("--seulement", type=plage, default=set(), help="numéros à générer, ex. 120-129,135")
    parser.add_argument("--sortie", type=Path, default=ICI.parent / "sd", help="dossier de sortie (défaut : ../sd)")
    parser.add_argument("--forcer", action="store_true", help="régénérer les fichiers existants")
    parser.add_argument("--essai", action="store_true", help="afficher sans appeler l'API")
    args = parser.parse_args()

    if not args.mission and not args.sfx:
        parser.error("indiquez un fichier de mission et/ou --sfx")

    taches = []
    if args.mission:
        taches += taches_voix(args.mission, args.seulement)
    if args.sfx:
        taches += taches_sfx(args.seulement)

    cle = os.environ.get("ELEVENLABS_API_KEY")
    if not cle and not args.essai:
        sys.exit("Définissez la variable ELEVENLABS_API_KEY.")

    faits = 0
    for dossier, numero, libelle, url, corps in taches:
        cible = args.sortie / f"{dossier:02d}" / f"{numero:03d}.mp3"
        if cible.exists() and not args.forcer:
            continue
        print(f"{cible.relative_to(args.sortie)}  {libelle}")
        if args.essai:
            continue
        cible.parent.mkdir(parents=True, exist_ok=True)
        cible.write_bytes(appeler(url, corps, cle))
        faits += 1
    print(f"Terminé : {faits} fichier(s) généré(s).")


if __name__ == "__main__":
    main()
