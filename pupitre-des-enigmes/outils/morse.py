#!/usr/bin/env python3
"""Fabrique la balise de détresse en morse de la mission 3 (code du sas 4719).

Le rythme doit être exact, ce qu'un générateur de bruitages ne garantit pas :
le fichier est donc synthétisé ici, en WAV, format que le DFPlayer lit aussi.

    python3 morse.py            ->  sd/31/020.wav
"""
import math
import struct
import wave
from pathlib import Path

MORSE = {"0": "-----", "1": ".----", "2": "..---", "3": "...--", "4": "....-",
         "5": ".....", "6": "-....", "7": "--...", "8": "---..", "9": "----."}
FREQUENCE = 22050
UNITE = 0.12        # durée d'un point, en secondes (environ 10 mots par minute)
NOTE = 700          # hauteur du bip, en Hz
CODE = "4719"
REPETITIONS = 4


def ton(duree):
    n = int(FREQUENCE * duree)
    # Une rampe de 5 ms en début et en fin évite les claquements.
    rampe = int(FREQUENCE * 0.005)
    return [math.sin(2 * math.pi * NOTE * i / FREQUENCE) * min(1, i / rampe, (n - i) / rampe) for i in range(n)]


def silence(duree):
    return [0.0] * int(FREQUENCE * duree)


def balise():
    echantillons = []
    for _ in range(REPETITIONS):
        for chiffre in CODE:
            for signe in MORSE[chiffre]:
                echantillons += ton(UNITE if signe == "." else 3 * UNITE) + silence(UNITE)
            echantillons += silence(2 * UNITE)          # espace entre deux chiffres (3 unités au total)
        echantillons += silence(2.5)                    # pause avant la répétition
    return echantillons


def main():
    sortie = Path(__file__).resolve().parent.parent / "sd" / "31" / "020.wav"
    sortie.parent.mkdir(parents=True, exist_ok=True)
    with wave.open(str(sortie), "wb") as f:
        f.setnchannels(1)
        f.setsampwidth(2)
        f.setframerate(FREQUENCE)
        f.writeframes(b"".join(struct.pack("<h", int(e * 0.6 * 32767)) for e in balise()))
    print(f"✓ {sortie}")


if __name__ == "__main__":
    main()
