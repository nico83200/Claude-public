#!/usr/bin/env python3
"""L'atelier des voix du Pupitre des Énigmes : une seule commande pour toutes les voix et les bruitages.

    python3 generer_audio.py              # tout ce qui manque ou a changé, après confirmation
    python3 generer_audio.py --essai      # seulement la liste (et le nombre de caractères à payer)
    python3 generer_audio.py --brouillon  # des voix provisoires, avec la voix de l'ordinateur (gratuit)

Ce que fait la commande, dans l'ordre :
1. Le classeur de suivi (suivi/suivi-audio.xlsx) et les fichiers du jeu sont mis d'accord :
   - un texte modifié dans le classeur (colonne « Script ») passe dans le jeu, sous-titres compris ;
   - un texte modifié dans le jeu passe dans le classeur ;
   - un Voice ID collé dans l'onglet « Narrateurs » passe dans les voix.json (et inversement) ;
   - une réplique nouvelle, absente du classeur, y est ajoutée.
2. Elle cherche ce qui est à faire : un MP3 absent, ou dont le texte (ou la voix) a changé depuis
   qu'il a été fabriqué. Un MP3 posé à la main (site d'ElevenLabs, votre propre voix) est gardé.
3. Elle fabrique les fichiers avec l'API d'ElevenLabs (ELEVENLABS_API_KEY), une seule fois par
   texte identique dit par la même voix, puis harmonise les volumes (ffmpeg, s'il est installé).
4. Elle met à jour le classeur : « Fait » et la date pour chaque audio fabriqué.

Les fichiers sont rangés comme les lecteurs audio les attendent (sd/40/122.mp3 : dossier 40,
réplique 122), dans le dossier sd/ à la racine, à copier sur les deux cartes SD. La liste de ce qui
a été fabriqué, et d'après quel texte, est dans sd/empreintes.json.

Autres options :
    python3 generer_audio.py ../mondes/narval/audio/mission-4.json --seulement 120-129
    python3 generer_audio.py --forcer --seulement 1-5 ../console/audio/repliques.json
    python3 generer_audio.py --sans-classeur      # sans toucher au classeur
"""
import argparse
import datetime
import hashlib
import json
import os
import platform
import re
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request
from pathlib import Path

API = "https://api.elevenlabs.io/v1"
FORMAT = "mp3_44100_128"
RACINE = Path(__file__).resolve().parent.parent
CLASSEUR = RACINE / "suivi" / "suivi-audio.xlsx"
ETAT_CLASSEUR = RACINE / "suivi" / "synchro-classeur.json"  # le dernier texte sur lequel classeur et jeu étaient d'accord
PROVISOIRES = {"brouillon", "simulation"}  # remplacés par ElevenLabs dès qu'on le lance
BROUILLON = "Brouillon : voix de l'ordinateur"
PERIME = "Le texte ou la voix a changé depuis l'enregistrement"
LUFS_VOIX, LUFS_SONS = -16, -18  # le volume moyen visé (les voix un peu devant les bruitages)


def charger(chemin):
    with open(chemin, encoding="utf-8") as f:
        return json.load(f)


def enregistrer(chemin, contenu):
    with open(chemin, "w", encoding="utf-8") as f:
        json.dump(contenu, f, ensure_ascii=False, indent=2)
        f.write("\n")


def plage(texte):
    """'120-129,135' -> {120, ..., 129, 135}"""
    numeros = set()
    for morceau in texte.split(","):
        debut, _, fin = morceau.partition("-")
        numeros.update(range(int(debut), int(fin or debut) + 1))
    return numeros


# ----------------------------------------------------------------------------------------------
# Les audios du jeu
# ----------------------------------------------------------------------------------------------

class Audio:
    """Une réplique ou un bruitage : ce qu'il faut fabriquer pour un fichier des cartes SD."""

    def __init__(self, source, contenu, element, casting):
        self.source, self.contenu, self.element, self.casting = source, contenu, element, casting
        self.dossier, self.n = contenu["dossier"], element["n"]
        self.cle = f"{self.dossier:02d}/{self.n:03d}"
        self.voix = "lignes" in contenu
        self.outil = element.get("outil")  # fabriqué par un script du dépôt (morse.py, frappe.py)

    @property
    def texte(self):
        return self.element["texte"] if self.voix else self.element.get("prompt", "")

    def personnage(self):
        return self.casting["voix"][self.element["voix"]] if self.voix else None

    def empreinte(self):
        """Ce qui, s'il change, demande un nouveau fichier : le texte, la voix et ses réglages."""
        if self.voix:
            v = self.personnage()
            base = [self.texte, v.get("voice_id", ""), v.get("modele", self.casting.get("modele_par_defaut")), v.get("reglages")]
        else:
            base = [self.texte, self.element.get("duree")]
        return hashlib.sha1(json.dumps(base, ensure_ascii=False, sort_keys=True).encode()).hexdigest()[:16]

    def empreinte_texte(self):
        """Pour un fichier fait à la main : seul un changement de texte le rend périmé."""
        return hashlib.sha1(self.texte.encode()).hexdigest()[:16]

    def libelle(self):
        if self.voix:
            casque = " 🎧" if self.element.get("sortie") == "casque" else ""
            return f"{self.element['voix']}{casque} : {self.texte[:60]}"
        return f"bruitage : {self.element.get('nom', '')}"


def fichiers_sources(arguments):
    if arguments:
        return [Path(f).resolve() for f in arguments if Path(f).name != "voix.json"]
    liste = sorted((RACINE / "console" / "audio").glob("*.json")) + sorted((RACINE / "mondes").glob("*/audio/*.json"))
    return [f for f in liste if f.name != "voix.json"]


def lire_audios(sources):
    audios, contenus, castings = [], {}, {}
    for f in sources:
        contenu = charger(f)
        if "lignes" not in contenu and "sons" not in contenu:
            sys.exit(f"{f} : ni répliques ni bruitages.")
        contenus[f] = contenu
        casting = None
        if "lignes" in contenu:
            cv = f.parent / "voix.json"
            if cv not in castings:
                castings[cv] = charger(cv)
            casting = castings[cv]
        for e in contenu.get("lignes", contenu.get("sons", [])):
            audios.append(Audio(f, contenu, e, casting))
    return audios, contenus, castings


# ----------------------------------------------------------------------------------------------
# Le classeur de suivi
# ----------------------------------------------------------------------------------------------

COL = {"ref": 1, "type": 2, "mission": 3, "usage": 4, "narrateur": 5, "script": 6, "duree": 7, "sortie": 8,
       "fichiers": 9, "statut": 10, "date": 11, "remarques": 12}


def synchroniser_classeur(classeur, audios, contenus, castings, essai):
    """Met d'accord le classeur et les fichiers du jeu. Renvoie le classeur ouvert (ou None)."""
    try:
        from openpyxl import load_workbook
    except ImportError:
        print("ℹ️  openpyxl absent (pip install openpyxl) : le classeur n'est pas mis à jour.")
        return None
    if not classeur.exists():
        print(f"ℹ️  {classeur} introuvable : le classeur n'est pas mis à jour.")
        return None
    wb = load_workbook(classeur)
    ws = wb["Suivi audio"]
    etat = charger(ETAT_CLASSEUR) if ETAT_CLASSEUR.exists() else {}
    par_cle = {a.cle: a for a in audios}
    vus, vers_jeu, vers_classeur, conflits = set(), [], [], []
    a_enregistrer = set()

    for r in range(2, ws.max_row + 1):
        cles = [f"{d}/{n}" for d, n in re.findall(r"(\d{2})/(\d{3})\.(?:mp3|wav)", str(ws.cell(r, COL["fichiers"]).value))]
        for cle in cles:
            a = par_cle.get(cle)
            if not a or a.outil:
                continue
            vus.add(cle)
            x = str(ws.cell(r, COL["script"]).value or "").strip()
            j = a.texte.strip()
            if x == j:
                etat[cle] = j
                continue
            avant = etat.get(cle)
            if avant is None or (x != avant and j == avant):  # modifié dans le classeur : il passe dans le jeu
                vers_jeu.append((cle, j, x))
                if a.voix:
                    a.element["texte"] = x
                else:
                    a.element["prompt"] = x
                    d = ws.cell(r, COL["duree"]).value
                    if isinstance(d, (int, float)):
                        a.element["duree"] = d
                a_enregistrer.add(a.source)
                etat[cle] = x
            else:  # modifié dans le jeu (ou des deux côtés : le jeu l'emporte, on le signale)
                if x != avant:
                    conflits.append((cle, x, j))
                vers_classeur.append((cle, x, j))
                ws.cell(r, COL["script"]).value = a.texte
                etat[cle] = j

    # Les Voice ID : l'onglet « Narrateurs » et les voix.json.
    ids = 0
    if "Narrateurs" in wb.sheetnames:
        wn = wb["Narrateurs"]
        lignes = {str(wn.cell(r, 1).value): r for r in range(2, wn.max_row + 1) if wn.cell(r, 1).value}
        for chemin, casting in castings.items():
            for v in casting["voix"].values():
                r = lignes.get(v.get("nom"))
                if not r:
                    continue
                x = str(wn.cell(r, 5).value or "").strip()
                if x and x != v.get("voice_id"):
                    v["voice_id"] = x
                    a_enregistrer.add(chemin)
                    ids += 1
                elif not x and v.get("voice_id"):
                    wn.cell(r, 5).value = v["voice_id"]
                    ids += 1

    # Les audios nouveaux, absents du classeur : une ligne de plus, au bout.
    nouveaux = [a for a in audios if a.cle not in vus and not a.outil]
    if nouveaux:
        ajouter_lignes(wb, ws, nouveaux)
        for a in nouveaux:
            etat[a.cle] = a.texte.strip()

    for chemin in a_enregistrer:
        if not essai:
            enregistrer(chemin, castings[chemin] if chemin in castings else contenus[chemin])
    if not essai:
        enregistrer(ETAT_CLASSEUR, dict(sorted(etat.items())))

    if vers_jeu:
        print(f"✏️  {len(vers_jeu)} texte(s) modifié(s) dans le classeur, repris dans le jeu :")
        for cle, avant, apres in vers_jeu[:20]:
            print(f"     {cle}  « {avant[:50]} » → « {apres[:50]} »")
        print("   Les sous-titres de l'écran suivent : recopiez la carte de l'écran (preparer_sd.py, ou l'onglet")
        print("   Missions du panneau Wi-Fi).")
    if vers_classeur:
        print(f"✏️  {len(vers_classeur)} texte(s) modifié(s) dans le jeu, recopié(s) dans le classeur.")
    for cle, x, j in conflits:
        print(f"⚠️  {cle} modifié des deux côtés : le jeu garde « {j[:50]} » (le classeur disait « {x[:50]} »).")
    if ids:
        print(f"🔑 {ids} Voice ID mis d'accord entre l'onglet Narrateurs et les voix.json.")
    if nouveaux:
        print(f"➕ {len(nouveaux)} audio(s) nouveau(x) ajouté(s) au classeur.")
    return wb


def ajouter_lignes(wb, ws, nouveaux):
    from copy import copy
    dernier = ws.max_row
    prefixes = {}
    for r in range(2, dernier + 1):
        ref = str(ws.cell(r, COL["ref"]).value or "")
        m = re.match(r"(.*-[VS])(\d+)$", ref)
        f = re.search(r"(\d{2})/\d{3}", str(ws.cell(r, COL["fichiers"]).value))
        if m and f:
            p = prefixes.setdefault(f.group(1), [m.group(1), 0])
            p[1] = max(p[1], int(m.group(2)))
    for a in nouveaux:
        r = ws.max_row + 1
        for c in range(1, 13):  # la mise en forme de la ligne d'au-dessus
            ws.cell(r, c)._style = copy(ws.cell(dernier, c)._style)
        p = prefixes.setdefault(f"{a.dossier:02d}", [f"D{a.dossier:02d}-{'V' if a.voix else 'S'}", 0])
        p[1] += 1
        valeurs = {
            "ref": f"{p[0]}{p[1]:03d}", "type": "Voix" if a.voix else "Bruitage",
            "mission": a.contenu.get("mission", "Toutes les missions (pupitre)"),
            "usage": a.element.get("usage", a.element.get("nom", "")),
            "narrateur": a.personnage()["nom"] if a.voix else "—", "script": a.texte,
            "duree": None if a.voix else a.element.get("duree"),
            "sortie": ("Casque" if a.element.get("sortie") == "casque" else "Haut-parleur") if a.voix else "—",
            "fichiers": f"{a.cle}.mp3", "statut": "À faire",
        }
        for k, v in valeurs.items():
            ws.cell(r, COL[k]).value = v
    # Les formules des autres onglets comptent jusqu'à la dernière ligne : elles suivent.
    for nom in wb.sheetnames:
        for ligne in wb[nom].iter_rows():
            for cellule in ligne:
                if isinstance(cellule.value, str) and cellule.value.startswith("="):
                    cellule.value = cellule.value.replace(f"${dernier})", f"${ws.max_row})").replace(f"${dernier},", f"${ws.max_row},")
    for dv in ws.data_validations.dataValidation:
        dv.sqref = type(dv.sqref)(str(dv.sqref).replace(f"{dernier}", f"{ws.max_row}"))


def lire_statuts(wb):
    """Le statut de chaque fichier dans le classeur : « Fait », « À refaire »…"""
    statuts = {}
    if wb is not None:
        ws = wb["Suivi audio"]
        for r in range(2, ws.max_row + 1):
            for d, n in re.findall(r"(\d{2})/(\d{3})\.(?:mp3|wav)", str(ws.cell(r, COL["fichiers"]).value)):
                statuts[f"{d}/{n}"] = ws.cell(r, COL["statut"]).value
    return statuts


def statuts_classeur(wb, classeur, audios, empreintes, sortie, essai, fabriques=frozenset()):
    """« Fait » et la date pour ce qui a été fabriqué ; « À refaire » pour ce qui a changé depuis."""
    if wb is None:
        return
    ws = wb["Suivi audio"]
    par_cle = {a.cle: a for a in audios}
    aujourdhui = datetime.date.today()
    faits = refaire = 0
    for r in range(2, ws.max_row + 1):
        cles = [f"{d}/{n}" for d, n in re.findall(r"(\d{2})/(\d{3})\.(?:mp3|wav)", str(ws.cell(r, COL["fichiers"]).value))]
        cles = [c for c in cles if c in par_cle]
        if not cles:
            continue
        statut = ws.cell(r, COL["statut"]).value
        etats = [etat_fichier(par_cle[c], empreintes, sortie) for c in cles]
        if all(e == "à jour" for e in etats):
            provisoire = any(empreintes[c]["source"] in PROVISOIRES for c in cles)
            remarque = ws.cell(r, COL["remarques"]).value
            if provisoire:  # une voix de brouillon ne compte pas comme faite
                if not remarque:
                    ws.cell(r, COL["remarques"]).value = BROUILLON
            else:
                # « À refaire » ne redevient « Fait » que si le fichier vient d'être refait.
                if statut in (None, "", "À faire", "En cours") or (statut == "À refaire" and set(cles) <= fabriques):
                    ws.cell(r, COL["statut"]).value = "Fait"
                    ws.cell(r, COL["date"]).value = aujourdhui
                    faits += 1
                if remarque in (BROUILLON, PERIME):
                    ws.cell(r, COL["remarques"]).value = None
        elif any(e == "périmé" for e in etats) and statut in ("Fait", "Validé"):
            ws.cell(r, COL["statut"]).value = "À refaire"
            ws.cell(r, COL["remarques"]).value = PERIME
            refaire += 1
    if not essai:
        wb.save(classeur)
    if faits or refaire:
        print(f"📒 Classeur : {faits} ligne(s) passée(s) à « Fait », {refaire} à « À refaire ».")


# ----------------------------------------------------------------------------------------------
# Les fichiers des cartes SD
# ----------------------------------------------------------------------------------------------

def chemin_existant(audio, sortie):
    for ext in (".mp3", ".wav"):
        p = sortie / f"{audio.dossier:02d}" / f"{audio.n:03d}{ext}"
        if p.exists():
            return p
    return None


def etat_fichier(audio, empreintes, sortie):
    """« absent », « à jour », ou « périmé » (le texte ou la voix a changé depuis sa fabrication)."""
    if not chemin_existant(audio, sortie):
        return "absent"
    e = empreintes.get(audio.cle)
    if e is None:  # posé à la main : on le prend tel quel, sur le texte actuel
        empreintes[audio.cle] = {"empreinte": audio.empreinte_texte(), "source": "manuel"}
        return "à jour"
    if e["source"] == "manuel":
        return "à jour" if e["empreinte"] == audio.empreinte_texte() else "périmé"
    return "à jour" if e["empreinte"] == audio.empreinte() else "périmé"


def ecrire(audio, donnees_wav_ou_mp3, extension, sortie, normaliser):
    """Range le fichier à sa place (sd/40/122.mp3), en harmonisant le volume si ffmpeg est là."""
    dossier = sortie / f"{audio.dossier:02d}"
    dossier.mkdir(parents=True, exist_ok=True)
    for ext in (".mp3", ".wav"):  # un seul fichier par numéro, sinon le lecteur audio hésite
        (dossier / f"{audio.n:03d}{ext}").unlink(missing_ok=True)
    with tempfile.TemporaryDirectory() as tmp:
        brut = Path(tmp) / f"brut{extension}"
        brut.write_bytes(donnees_wav_ou_mp3)
        if normaliser and shutil.which("ffmpeg"):
            cible = dossier / f"{audio.n:03d}.mp3"
            lufs = LUFS_VOIX if audio.voix else LUFS_SONS
            r = subprocess.run(["ffmpeg", "-y", "-loglevel", "quiet", "-i", str(brut), "-af",
                                f"loudnorm=I={lufs}:TP=-1.5:LRA=11", "-ar", "44100", "-ac", "2", "-b:a", "128k", str(cible)])
            if r.returncode == 0:
                return cible
            cible.unlink(missing_ok=True)  # pas de fichier à moitié écrit à côté de la copie brute
        cible = dossier / f"{audio.n:03d}{extension}"
        shutil.copy(brut, cible)
        return cible


# ----------------------------------------------------------------------------------------------
# Les fabricants : ElevenLabs, la voix de l'ordinateur (brouillon), ou un fichier muet (simulation)
# ----------------------------------------------------------------------------------------------

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


def elevenlabs(audio, cle):
    if audio.voix:
        v = audio.personnage()
        corps = {"text": audio.texte, "model_id": v.get("modele", audio.casting["modele_par_defaut"]),
                 "voice_settings": v["reglages"]}
        return appeler(f"{API}/text-to-speech/{v['voice_id']}", corps, cle), ".mp3"
    corps = {"text": audio.texte, "duration_seconds": max(audio.element["duree"], 0.5), "prompt_influence": 0.5}
    return appeler(f"{API}/sound-generation", corps, cle), ".mp3"


def voix_ordinateur(audio):
    """Une voix provisoire, gratuite : « say » sur Mac, espeak-ng sous Linux, la synthèse de Windows."""
    feminine = audio.personnage().get("description", "").lower().startswith("femme")
    with tempfile.TemporaryDirectory() as tmp:
        wav = Path(tmp) / "voix.wav"
        systeme = platform.system()
        if systeme == "Darwin":
            nom = "Audrey" if feminine else "Thomas"
            r = subprocess.run(["say", "-v", nom, "-o", str(wav), "--data-format=LEI16@22050", audio.texte])
            if r.returncode != 0:  # la voix Audrey n'est pas toujours installée
                subprocess.run(["say", "-v", "Amelie" if feminine else "Thomas", "-o", str(wav),
                                "--data-format=LEI16@22050", audio.texte], check=True)
        elif systeme == "Windows":
            script = ("Add-Type -AssemblyName System.Speech; $s = New-Object System.Speech.Synthesis.SpeechSynthesizer; "
                      "$s.SelectVoiceByHints([System.Speech.Synthesis.VoiceGender]::" + ("Female" if feminine else "Male") +
                      "); $s.SetOutputToWaveFile($env:FICHIER); $s.Speak($env:TEXTE); $s.Dispose()")
            subprocess.run(["powershell", "-NoProfile", "-Command", script], check=True,
                           env={**os.environ, "FICHIER": str(wav), "TEXTE": audio.texte})
        else:
            outil = shutil.which("espeak-ng") or shutil.which("espeak")
            if not outil:
                sys.exit("Brouillon : installez espeak-ng (sudo apt install espeak-ng).")
            subprocess.run([outil, "-v", "fr+f3" if feminine else "fr", "-s", "150", "-w", str(wav), audio.texte], check=True)
        return wav.read_bytes(), ".wav"


def muet(audio):
    """Simulation : un fichier muet de la bonne durée environ (pour essayer la chaîne sans compte)."""
    secondes = max(1.0, len(audio.texte) / 15) if audio.voix else max(0.5, float(audio.element.get("duree", 1)))
    n = int(22050 * secondes)
    entete = (b"RIFF" + (36 + 2 * n).to_bytes(4, "little") + b"WAVEfmt " + (16).to_bytes(4, "little") +
              (1).to_bytes(2, "little") + (1).to_bytes(2, "little") + (22050).to_bytes(4, "little") +
              (44100).to_bytes(4, "little") + (2).to_bytes(2, "little") + (16).to_bytes(2, "little") +
              b"data" + (2 * n).to_bytes(4, "little"))
    return entete + bytes(2 * n), ".wav"


# ----------------------------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("fichiers", nargs="*", help="fichiers de répliques ou de bruitages (par défaut : tous)")
    parser.add_argument("--seulement", type=plage, default=set(), help="numéros à traiter, ex. 120-129,135")
    parser.add_argument("--sortie", type=Path, default=RACINE / "sd", help="dossier des cartes SD (défaut : sd/)")
    parser.add_argument("--forcer", action="store_true", help="refaire même les fichiers à jour")
    parser.add_argument("--essai", action="store_true", help="tout montrer, sans rien fabriquer ni modifier")
    parser.add_argument("--oui", action="store_true", help="ne pas demander de confirmation")
    parser.add_argument("--brouillon", action="store_true",
                        help="voix provisoires avec la voix de l'ordinateur (gratuit ; remplacées ensuite par ElevenLabs)")
    parser.add_argument("--simulation", action="store_true", help="fichiers muets, pour essayer sans compte")
    parser.add_argument("--classeur", type=Path, default=CLASSEUR, help="le classeur de suivi (défaut : suivi/suivi-audio.xlsx)")
    parser.add_argument("--sans-classeur", action="store_true", help="ne pas lire ni modifier le classeur")
    parser.add_argument("--sans-normaliser", action="store_true", help="ne pas harmoniser les volumes")
    args = parser.parse_args()
    args.sortie = args.sortie.resolve()

    audios, contenus, castings = lire_audios(fichiers_sources(args.fichiers))
    wb = None if args.sans_classeur else synchroniser_classeur(args.classeur.resolve(), audios, contenus, castings, args.essai)

    fichier_empreintes = args.sortie / "empreintes.json"
    empreintes = charger(fichier_empreintes) if fichier_empreintes.exists() else {}

    # Ce qu'il reste à faire. Le classeur a le dernier mot : « Fait » ou « Validé » sans fichier ici,
    # c'est un audio rangé ailleurs (le Google Drive) : on ne le refait pas. « À refaire » : on le refait.
    statuts = lire_statuts(wb)
    a_faire, sans_voix, outils, ailleurs = [], {}, [], []
    for a in audios:
        if args.seulement and a.n not in args.seulement:
            continue
        if a.outil:
            if not chemin_existant(a, args.sortie):
                outils.append(a)
            continue
        etat = etat_fichier(a, empreintes, args.sortie)
        statut = statuts.get(a.cle)
        if etat == "absent" and statut in ("Fait", "Validé") and not args.forcer:
            ailleurs.append(a)
            continue
        if statut == "À refaire" and etat == "à jour" and not args.brouillon:
            etat = "périmé"
        source = empreintes.get(a.cle, {}).get("source")
        provisoire_a_remplacer = source in PROVISOIRES and not (args.brouillon or args.simulation)
        a_remplacer = etat != "à jour" or args.forcer or provisoire_a_remplacer
        if not a_remplacer:
            continue
        if args.brouillon and not a.voix:
            continue  # pas de bruitage provisoire
        if not args.brouillon and not args.simulation and a.voix and not a.personnage().get("voice_id"):
            nom = a.personnage()["nom"]
            sans_voix[nom] = sans_voix.get(nom, 0) + 1
            continue
        a_faire.append((a, etat))

    # Un texte dit plusieurs fois par la même voix n'est fabriqué qu'une fois.
    groupes = {}
    for a, etat in a_faire:
        groupes.setdefault(a.empreinte() + str(a.voix), []).append((a, etat))
    caracteres = sum(len(g[0][0].texte) for g in groupes.values() if g[0][0].voix)
    nouveaux = sum(1 for a, e in a_faire if e == "absent")
    print(f"À fabriquer : {len(a_faire)} fichier(s) ({nouveaux} absent(s), {len(a_faire) - nouveaux} à refaire), "
          f"{len(groupes)} appel(s), {caracteres} caractères de voix.")
    for a, etat in a_faire[:40]:
        print(f"  {a.cle}  {'(refaire) ' if etat == 'périmé' else ''}{a.libelle()}")
    if len(a_faire) > 40:
        print(f"  … et {len(a_faire) - 40} autre(s).")
    if ailleurs:
        dossiers = sorted({f"{a.dossier:02d}" for a in ailleurs})
        print(f"📁 {len(ailleurs)} audio(s) déjà faits d'après le classeur, mais absents de {args.sortie.name}/ "
              f"(dossiers {', '.join(dossiers)}) : ils ne sont pas refaits. Copiez-les depuis le Google Drive")
        print(f"   dans {args.sortie.name}/ pour que tout soit au même endroit (et que les volumes soient harmonisés).")
    for a in outils:
        print(f"ℹ️  {a.cle} ({a.element.get('nom')}) : lancez {a.outil}")
    if sans_voix:
        noms = sorted(sans_voix, key=lambda n: -sans_voix[n])
        print(f"⚠️  {len(noms)} voix sans Voice ID : {sum(sans_voix.values())} réplique(s) en attente. Collez leur Voice ID")
        print("   dans l'onglet « Narrateurs » du classeur (ou essayez --brouillon). Les plus utilisées :")
        for n in noms[:5]:
            print(f"     {n} ({sans_voix[n]})")
    if args.sortie.exists() and not shutil.which("ffmpeg") and not args.sans_normaliser and a_faire:
        print("ℹ️  ffmpeg absent : les volumes ne seront pas harmonisés (Mac : brew install ffmpeg).")

    if args.essai:
        statuts_classeur(wb, args.classeur, audios, empreintes, args.sortie, True)
        return
    cle = os.environ.get("ELEVENLABS_API_KEY")
    if a_faire and not (args.brouillon or args.simulation):
        if not cle:
            sys.exit("Définissez la variable ELEVENLABS_API_KEY (ou essayez --brouillon).")
        if not args.oui and input(f"Lancer ElevenLabs pour {caracteres} caractères ? [o/N] ").strip().lower() not in ("o", "oui"):
            sys.exit("Rien n'a été fabriqué.")

    faits, fabriques = 0, set()
    try:
        for groupe in groupes.values():
            premier = groupe[0][0]
            if args.simulation:
                donnees, ext = muet(premier)
            elif args.brouillon:
                donnees, ext = voix_ordinateur(premier)
            else:
                donnees, ext = elevenlabs(premier, cle)
            for a, _ in groupe:
                chemin = ecrire(a, donnees, ext, args.sortie, not (args.sans_normaliser or args.simulation))
                empreintes[a.cle] = {"empreinte": a.empreinte(),
                                     "source": "brouillon" if args.brouillon else "simulation" if args.simulation else "elevenlabs"}
                faits += 1
                fabriques.add(a.cle)
                print(f"  ✓ {chemin.relative_to(args.sortie.parent)}  {a.libelle()}")
    finally:  # même interrompu, on garde la trace de ce qui est fait
        args.sortie.mkdir(parents=True, exist_ok=True)
        enregistrer(fichier_empreintes, dict(sorted(empreintes.items())))
        statuts_classeur(wb, args.classeur, audios, empreintes, args.sortie, False, fabriques)
    print(f"Terminé : {faits} fichier(s) fabriqué(s). Copiez le dossier {args.sortie.name}/ sur les deux cartes SD "
          "des lecteurs audio (sans empreintes.json).")


if __name__ == "__main__":
    main()
