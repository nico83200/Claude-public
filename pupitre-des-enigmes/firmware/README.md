# Le programme du pupitre

Le programme de l'ESP32, et un simulateur qui fait tourner le même moteur sur un
ordinateur.

Le pupitre ne contient **aucune mission en dur**. Il exécute des **scripts JSON**
rangés sur la carte SD de l'écran : ajouter un monde ou corriger une énigme ne
demande pas de reflasher l'ESP32, seulement de recopier la carte SD.

<p>
<img src="docs/ecrans/m1-03-ecurie.png" width="32%"> <img src="docs/ecrans/m2-02-bluff-pujol.png" width="32%"> <img src="docs/ecrans/m3-03-energie.png" width="32%">
</p>

## Organisation

```
firmware/
├── platformio.ini          la configuration de compilation (PlatformIO)
├── lib/moteur/src/         le moteur de jeu, portable (aucun code Arduino)
│   ├── moteur.h / .cpp     règles, épreuves, imprévus, indices, accusation, sauvegarde
│   ├── expression.h / .cpp les conditions des scripts (« J2 && !J4 », « restant>=15 »…)
│   └── plateforme.h        ce que le moteur attend du matériel
├── src/                    le matériel
│   ├── broches.h           le câblage et les réglages (volumes, Wi-Fi, luminosité)
│   ├── main.cpp            démarrage et boucle principale
│   ├── dessin.cpp          les écrans (commun au pupitre et au simulateur)
│   ├── ecran.cpp           l'ILI9488 sur le bus SPI
│   ├── lecteurs.cpp        les 3 lecteurs RFID RC522
│   ├── audio.cpp           les 2 DFPlayer (haut-parleur, casque)
│   ├── commandes.cpp       boutons et molette
│   ├── plateforme_esp32.cpp carte SD et LED
│   └── panneau_web.cpp     le panneau Wi-Fi du maître du jeu
├── test/
│   ├── lancer.sh           compile le simulateur et rejoue toutes les parties
│   ├── ecrans.sh           idem, et dessine les écrans en images PNG
│   ├── parties/*.txt       les parties de test (victoires, défaites, reprise…)
│   └── simulateur/         le simulateur PC
├── docs/ecrans/            les captures d'écran produites par ecrans.sh
└── SCRIPTS.md              la référence du langage des scripts
```

## 1. Installer PlatformIO

Le plus simple : [Visual Studio Code](https://code.visualstudio.com/) avec
l'extension **PlatformIO IDE**, puis *Ouvrir le dossier* `firmware/`. En ligne de
commande :

```
pip install platformio
cd firmware
pio run                  # compile (télécharge la première fois l'ESP32 et les bibliothèques)
```

Bibliothèques utilisées (installées automatiquement) : LovyanGFX (écran),
MFRC522 (RFID), DFRobotDFPlayerMini (audio), FastLED (LED) et ArduinoJson.

## 2. Téléverser dans l'ESP32

1. Brancher l'ESP32 en USB.
2. `pio run -t upload` (ou la flèche → de PlatformIO).
3. Si le téléversement échoue (*« Failed to connect »*) : **débrancher le fil de
   la GPIO 2** (TX vers le DFPlayer casque) le temps de flasher, ou maintenir le
   bouton BOOT de l'ESP32 au début du téléversement.
4. `pio device monitor` affiche le journal : lecteurs RFID détectés, cartes
   posées (avec leur UID), erreurs de script.

## 3. Préparer les cartes micro-SD

Le pupitre utilise **trois** cartes micro-SD.

| Carte | Contenu | Comment |
|---|---|---|
| Écran (lecteur SD de l'ILI9488) | Les mondes et leurs scripts | `python3 outils/preparer_sd.py --sortie /media/MA_CARTE` |
| DFPlayer haut-parleur | Les MP3 : `10/001.mp3`… | `python3 outils/generer_audio.py …` (voir [`outils/`](../outils/)) |
| DFPlayer casque | **La même chose** que le haut-parleur | Copier le même dossier `sd/` |

- Les cartes doivent être formatées en **FAT32**.
- `preparer_sd.py` vérifie les scripts, copie les mondes et **conserve** les
  cartes déjà enregistrées si on le relance sur la même carte SD.

## 4. Enregistrer les cartes (une fois par monde)

Chaque carte du jeu porte un autocollant NFC dont l'identifiant est unique. Il
faut apprendre au pupitre quelle puce correspond à quelle carte.

1. Sur l'écran d'accueil, **appuyer sur la molette** : *Préparer un monde*.
2. Choisir le monde à la molette, puis appuyer.
3. Le pupitre demande les cartes une par une (*« Posez la carte M1 · Le Haras des
   Trois Fers »*, puis A1, L1, L2…). Poser chaque carte sur **n'importe quel
   lecteur** : un bip confirme, la suivante s'affiche.
4. **Rouge** : passer une carte. **Vert** : terminer et enregistrer.

La table est écrite dans `/mondes/<monde>/cartes.json`. Une puce déjà attribuée
à une autre carte est refusée. On peut refaire l'enregistrement d'un monde à tout
moment depuis son menu (*« Enregistrer les cartes de ce monde »*).

## 5. Jouer

1. Allumer le pupitre, poser la **carte Monde** sur un lecteur.
2. Installer la façade du monde, choisir la mission à la molette, appuyer.
3. Chaque joueur pose sa **carte Rôle** puis la reprend. Le jeu démarre quand
   tous les rôles sont arrivés (ou au **bouton vert**).
4. Pendant la partie, une sauvegarde est écrite toutes les 15 secondes. Après une
   coupure de courant, reposer la carte Monde : le menu propose *« Reprendre la
   partie en cours »*.

Le **panneau Wi-Fi** du maître du jeu : se connecter au réseau
`Pupitre-des-Enigmes` (mot de passe `enigmes123`) et ouvrir http://192.168.4.1.

## 6. Réglages

Tout est dans [`src/broches.h`](src/broches.h) : volumes du haut-parleur et du
casque, luminosité des LED, nom et mot de passe du Wi-Fi, fréquence de l'écran.

| Symptôme | Réglage |
|---|---|
| La molette avance de deux crans à la fois, ou d'un cran sur deux | `TRANSITIONS_PAR_CRAN` : 4 ↔ 2 |
| L'écran affiche des parasites | `FREQ_ECRAN` : 27 MHz → 20 MHz |
| Les couleurs de l'écran sont inversées | `p.invert = true` dans `src/ecran.cpp` |
| Les LED ont les mauvaises couleurs | `GRB` → `RGB` dans `main.cpp` |
| Un lecteur RFID est « absent » au démarrage | Vérifier son CS et son 3,3 V (journal série) |
| Pas de son | Le journal dit *« DFPlayer absent »* : vérifier TX/RX (croisés) et la carte SD |

## 7. Le simulateur

Le simulateur fait tourner **le vrai moteur** sur un ordinateur, avec une horloge
virtuelle, et rejoue des parties écrites à l'avance. Il sert à vérifier une
mission avant de la jouer pour de vrai (il faut `g++` et Python 3).

```
cd firmware
sh test/lancer.sh              # vérifie les scripts et rejoue toutes les parties
sh test/lancer.sh --bavard     # en montrant l'écran et les répliques
sh test/ecrans.sh              # dessine les écrans dans docs/ecrans/ (après « pio pkg install »)
```

Une partie est un fichier texte, une commande par ligne :

```
pose QUI M1          # poser une carte (QUI, OU, QUOI)
retire QUI           # la retirer
bouton VERT          # ROUGE, BLEU, JAUNE, VERT, GROS
code 3,7,1,5         # saisir un code à la molette ; lettres SILENCE pour un mot
avance 10            # faire passer 10 minutes (s'arrête si un imprévu commence)
restant 17           # avancer jusqu'à ce qu'il reste 17 minutes au chrono
redemarre            # coupure de courant (la carte SD et sa sauvegarde restent)
verifie drapeau J3   # et : sans, compteur, phase, epreuve, ecran
capture m1-ecurie    # une image de l'écran (avec ecrans.sh)
```

La liste complète des commandes est en tête de
[`test/simulateur/sim.cpp`](test/simulateur/sim.cpp).

## 8. Écrire ou modifier une mission

Les scripts sont dans `mondes/<monde>/pupitre/`. Leur langage est décrit dans
[SCRIPTS.md](SCRIPTS.md). Après une modification :

```
python3 outils/verifier_scripts.py   # répliques, bruitages et cartes existent-ils ?
sh firmware/test/lancer.sh           # les parties de test passent-elles encore ?
python3 outils/preparer_sd.py --sortie /media/MA_CARTE
```
