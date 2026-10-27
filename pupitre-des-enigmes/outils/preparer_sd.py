#!/usr/bin/env python3
"""Prépare le contenu de la carte micro-SD de l'ÉCRAN du pupitre (les scripts de jeu).

    python3 preparer_sd.py                 ->  ../sd-ecran/
    python3 preparer_sd.py --sortie /media/SD
    python3 preparer_sd.py --test          (cartes « UID-<id> », pour le simulateur)

Contenu produit :
    /pupitre/repliques.json            les phrases de la voix du pupitre
    /mondes/index.json                 la liste des mondes
    /mondes/<monde>/monde.json         thème, missions, carte Monde
    /mondes/<monde>/mission-N.json     le script de la mission
    /mondes/<monde>/repliques-N.json   les sous-titres (copie de audio/mission-N.json)
    /mondes/<monde>/cartes.json        puce NFC de chaque carte (rempli par l'enrôlement)

Les scripts sont d'abord vérifiés (outils/verifier_scripts.py). Les cartes déjà
enregistrées sur la carte SD de destination sont conservées.
Les fichiers audio (MP3) vont sur les cartes des DFPlayer : voir generer_audio.py.
"""
import argparse
import json
import shutil
import subprocess
import sys
from pathlib import Path

RACINE = Path(__file__).resolve().parent.parent


def lire(chemin):
    return json.loads(Path(chemin).read_text(encoding="utf-8"))


def ecrire(chemin, contenu):
    chemin.parent.mkdir(parents=True, exist_ok=True)
    chemin.write_text(json.dumps(contenu, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--sortie", type=Path, default=RACINE / "sd-ecran")
    parser.add_argument("--test", action="store_true", help="associer chaque carte à une puce fictive « UID-<id> »")
    args = parser.parse_args()
    sortie = args.sortie

    # Un script avec une réplique ou une carte inconnue ne part pas sur la carte SD.
    verification = subprocess.run([sys.executable, str(RACINE / "outils" / "verifier_scripts.py")])
    if verification.returncode:
        sys.exit("Scripts invalides : corrigez-les avant de préparer la carte SD.")

    (sortie / "pupitre").mkdir(parents=True, exist_ok=True)
    shutil.copy(RACINE / "console" / "audio" / "repliques.json", sortie / "pupitre" / "repliques.json")

    index = []
    for dossier in sorted((RACINE / "mondes").iterdir()):
        source = dossier / "pupitre" / "monde.json"
        if not source.exists():
            continue
        monde = lire(source)
        cible = sortie / "mondes" / monde["id"]
        ecrire(cible / "monde.json", monde)
        identifiants = [monde["carte"]] + ([monde["aide"]] if monde.get("aide") else [])
        for m in monde["missions"]:
            script = lire(dossier / "pupitre" / m["script"])
            # Compacté : l'ESP32 lit tout le fichier en mémoire avant de le décoder.
            (cible / m["script"]).write_text(json.dumps(script, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
            repliques = dossier / "audio" / f"mission-{m['numero']}.json"
            shutil.copy(repliques, cible / m["repliques"])
            identifiants += [i for i in script["cartes"] if i not in identifiants]

        # Les puces déjà enregistrées sont gardées ; les nouvelles cartes sont ajoutées vides.
        fichier_cartes = cible / "cartes.json"
        cartes = lire(fichier_cartes) if fichier_cartes.exists() else {}
        for i in identifiants:
            if args.test:
                cartes[i] = f"UID-{i}"
            else:
                cartes.setdefault(i, "")
        ecrire(fichier_cartes, cartes)
        index.append({"id": monde["id"], "nom": monde["nom"]})
        print(f"✓ {monde['nom']} : {len(monde['missions'])} mission(s), {len(identifiants)} cartes")

    ecrire(sortie / "mondes" / "index.json", {"mondes": index})
    print(f"Carte SD de l'écran prête dans {sortie}")


if __name__ == "__main__":
    main()
