#!/usr/bin/env python3
"""Génère les voix et les bruitages du Pupitre des Énigmes avec l'API ElevenLabs.

Chaque fichier JSON passé en argument est soit une liste de répliques (clé
"lignes", avec un voix.json dans le même dossier), soit une liste de bruitages
(clé "sons"). Les MP3 sont rangés comme le DFPlayer Mini les attend :
    sd/10/001.mp3   (réplique 1 du monde Haras)
    sd/90/002.mp3   (bruitage 2 du pupitre)

Utilisation :
    export ELEVENLABS_API_KEY=sk_...
    python3 generer_audio.py ../console/audio/*.json ../mondes/haras/audio/*.json
    python3 generer_audio.py ../mondes/haras/audio/mission-1.json --seulement 120-129
    python3 generer_audio.py ../mondes/haras/audio/mission-1.json --essai

Un fichier déjà présent n'est pas régénéré (sauf avec --forcer).
Le même dossier sd/ se copie sur les deux cartes micro-SD (haut-parleur et casque).
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
RACINE = Path(__file__).resolve().parent.parent


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


def taches_voix(fichier, contenu, numeros):
    casting = charger(Path(fichier).parent / "voix.json")
    modele = casting["modele_par_defaut"]
    manquantes = set()
    for ligne in contenu["lignes"]:
        if numeros and ligne["n"] not in numeros:
            continue
        voix = casting["voix"][ligne["voix"]]
        if not voix["voice_id"]:
            manquantes.add(ligne["voix"])
            continue
        corps = {"text": ligne["texte"], "model_id": voix.get("modele", modele), "voice_settings": voix["reglages"]}
        casque = " 🎧" if ligne.get("sortie") == "casque" else ""
        yield contenu["dossier"], ligne["n"], f"{ligne['voix']}{casque} : {ligne['texte'][:60]}", \
            f"{API}/text-to-speech/{voix['voice_id']}", corps
    if manquantes:
        print(f"⚠️  {fichier} : voice_id manquant pour {', '.join(sorted(manquantes))}")


def taches_sons(contenu, numeros):
    for son in contenu["sons"]:
        if numeros and son["n"] not in numeros:
            continue
        if "outil" in son:  # fabriqué par un script du dépôt (rythme exact), pas par l'API
            print(f"ℹ️  {contenu['dossier']}/{son['n']:03d} ({son['nom']}) : lancez {son['outil']}")
            continue
        corps = {"text": son["prompt"], "duration_seconds": max(son["duree"], 0.5), "prompt_influence": 0.5}
        yield contenu["dossier"], son["n"], f"bruitage : {son['nom']}", f"{API}/sound-generation", corps


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("fichiers", nargs="+", help="fichiers de répliques ou de bruitages (.json)")
    parser.add_argument("--seulement", type=plage, default=set(), help="numéros à générer, ex. 120-129,135")
    parser.add_argument("--sortie", type=Path, default=RACINE / "sd", help="dossier de sortie (défaut : sd/ à la racine)")
    parser.add_argument("--forcer", action="store_true", help="régénérer les fichiers existants")
    parser.add_argument("--essai", action="store_true", help="afficher sans appeler l'API")
    args = parser.parse_args()

    taches = []
    for fichier in args.fichiers:
        if Path(fichier).name == "voix.json":
            continue
        contenu = charger(fichier)
        if "lignes" in contenu:
            taches += taches_voix(fichier, contenu, args.seulement)
        elif "sons" in contenu:
            taches += taches_sons(contenu, args.seulement)
        else:
            sys.exit(f"{fichier} : ni répliques ni bruitages.")

    cle = os.environ.get("ELEVENLABS_API_KEY")
    if not cle and not args.essai:
        sys.exit("Définissez la variable ELEVENLABS_API_KEY.")

    faits = 0
    for dossier, numero, libelle, url, corps in taches:
        cible = args.sortie / f"{dossier:02d}" / f"{numero:03d}.mp3"
        if cible.exists() and not args.forcer:
            continue
        print(f"{dossier:02d}/{numero:03d}.mp3  {libelle}")
        if args.essai:
            continue
        cible.parent.mkdir(parents=True, exist_ok=True)
        cible.write_bytes(appeler(url, corps, cle))
        faits += 1
    print(f"Terminé : {faits} fichier(s) généré(s).")


if __name__ == "__main__":
    main()
