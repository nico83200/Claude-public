#!/usr/bin/env python3
"""Fabrique les coups frappés sur la coque par l'Orque (mission 4) : le mot BRUME
en code de frappe des sous-mariniers.

Le code de frappe range l'alphabet dans une grille de 5 × 5 (K et C partagent une
case). Chaque lettre se frappe en deux séries : le numéro de la ligne, une courte
pause, puis le numéro de la colonne. Le rythme doit être exact : le son est donc
synthétisé ici, en WAV, format que le DFPlayer lit aussi.

    python3 frappe.py           ->  sd/41/020.wav
"""
import math
import random
import struct
import wave
from pathlib import Path

GRILLE = ["ABCDE", "FGHIJ", "LMNOP", "QRSTU", "VWXYZ"]
MOT = "BRUME"
FREQUENCE = 22050
ENTRE_COUPS = 0.38     # deux coups d'une même série
ENTRE_SERIES = 0.95    # la ligne, puis la colonne
ENTRE_LETTRES = 2.2
REPETITIONS = 3


def position(lettre):
    lettre = "C" if lettre == "K" else lettre
    for i, ligne in enumerate(GRILLE):
        if lettre in ligne:
            return i + 1, ligne.index(lettre) + 1
    raise ValueError(lettre)


def coup():
    """Un coup de clé contre l'acier : un « tonk » grave et métallique qui s'éteint vite."""
    n = int(FREQUENCE * 0.32)
    hasard = random.Random(7)
    sortie = []
    for i in range(n):
        t = i / FREQUENCE
        enveloppe = math.exp(-t * 14)
        corps = math.sin(2 * math.pi * 118 * t) + 0.55 * math.sin(2 * math.pi * 341 * t) + 0.3 * math.sin(2 * math.pi * 917 * t)
        attaque = (hasard.random() * 2 - 1) * math.exp(-t * 90)
        sortie.append((0.55 * corps * enveloppe + 0.45 * attaque) * 0.8)
    return sortie


def silence(duree):
    return [0.0] * int(FREQUENCE * duree)


def message():
    un_coup = coup()
    echantillons = silence(0.8)
    for _ in range(REPETITIONS):
        for lettre in MOT:
            ligne, colonne = position(lettre)
            for k in range(ligne):
                echantillons += un_coup + silence(ENTRE_COUPS - 0.32 if k < ligne - 1 else 0)
            echantillons += silence(ENTRE_SERIES)
            for k in range(colonne):
                echantillons += un_coup + silence(ENTRE_COUPS - 0.32 if k < colonne - 1 else 0)
            echantillons += silence(ENTRE_LETTRES)
        echantillons += silence(4.0)  # avant la répétition
    return echantillons


def main():
    sortie = Path(__file__).resolve().parent.parent / "sd" / "41" / "020.wav"
    sortie.parent.mkdir(parents=True, exist_ok=True)
    with wave.open(str(sortie), "wb") as f:
        f.setnchannels(1)
        f.setsampwidth(2)
        f.setframerate(FREQUENCE)
        f.writeframes(b"".join(struct.pack("<h", max(-32767, min(32767, int(e * 32767)))) for e in message()))
    duree = len(message()) / FREQUENCE
    print(f"✓ {sortie}  ({MOT} : {' '.join('%d-%d' % position(l) for l in MOT)}, {duree:.0f} s)")


if __name__ == "__main__":
    main()
