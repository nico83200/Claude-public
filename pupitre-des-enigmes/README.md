# Le Pupitre des Énigmes

Une console d'escape game de table pour adultes (2 à 6 joueurs), construite
autour d'un ESP32. Elle a un écran, **3 lecteurs RFID (QUI · OÙ · QUOI)**, des
boutons, une molette, des voix pré-enregistrées et un **casque pour les messages
privés**.

Le pupitre est **multi-mondes** : chaque univers se charge en posant sa **carte
Monde** sur un lecteur, et s'habille d'une **façade aimantée** à son image.

## Les mondes

| Monde | Mission | Difficulté | Durée |
|---|---|---|---|
| 🐴 [Le Haras des Trois Fers](mondes/haras/) | 1 · [La Disparition d'Éclipse](mondes/haras/mission-1-disparition-eclipse.md) · [déroulé](mondes/haras/mission-1-deroule.md) | 🟢 Accessible | 60 min |
| 🎰 [Monte-Carlo 1928](mondes/casse/) | 2 · [Le Casse du Siècle](mondes/casse/mission-2-casse-du-siecle.md) · [déroulé](mondes/casse/mission-2-deroule.md) | 🟠 Intermédiaire | 60 min |
| 🚀 [Station Kepler-9](mondes/kepler/) | 3 · [Signal Perdu](mondes/kepler/mission-3-signal-perdu.md) · [déroulé](mondes/kepler/mission-3-deroule.md) | 🔴 Difficile | 75 min |
| ⚓ [Sous-marin Narval](mondes/narval/) | 4 · [Dernier souffle](mondes/narval/mission-4-dernier-souffle.md) · [déroulé](mondes/narval/mission-4-deroule.md) · *escape game pour adultes, sans rôles* | 🔴 Difficile | 60 min |

Les quatre missions sont complètes : scénario, déroulé, répliques, script de
doublage, matériel à imprimer et **script du pupitre**.

## Le programme

Le [programme de l'ESP32](firmware/) exécute les missions sous forme de scripts
JSON rangés sur la carte SD de l'écran : on ajoute un monde sans reflasher la
console. Un simulateur rejoue des parties complètes sur ordinateur pour vérifier
chaque mission.

```
cd firmware && pio run -t upload                  # téléverser dans l'ESP32
python3 outils/preparer_sd.py --sortie /media/SD  # préparer la carte SD de l'écran
sh firmware/test/lancer.sh                        # rejouer les parties de test
```

Voir [firmware/README.md](firmware/README.md) (installation, enregistrement des
cartes, réglages) et [firmware/SCRIPTS.md](firmware/SCRIPTS.md) (écrire une
mission).

## Le dépôt

| Dossier | Contenu |
|---|---|
| [`console/`](console/) | Le matériel, le câblage, l'organisation des cartes SD, les sons et la voix du pupitre |
| [`mondes/`](mondes/) | Un dossier par monde : scénarios, déroulés, répliques, documents à imprimer |
| [`firmware/`](firmware/) | Le programme de l'ESP32, le simulateur PC et le langage des scripts |
| [`outils/`](outils/) | Génération des voix (ElevenLabs), fabrication des PDF, préparation et vérification de la carte SD |
| [`pdf/`](pdf/) | Les documents prêts à imprimer |

## Les documents à imprimer

Tous les PDF sont dans [`pdf/`](pdf/) et se régénèrent avec
`python3 outils/impression/construire.py` (ou `construire.py 2` pour une seule
mission).

| Commun | Pour qui |
|---|---|
| [Mode d'emploi du pupitre](pdf/mode-emploi.pdf) | Tout le monde, valable pour tous les mondes |
| [Liste d'achat](pdf/liste-achats.pdf) | Le constructeur (source : `console/achats.json`) |

| Par mission | Mission 1 · Haras | Mission 2 · Monte-Carlo | Mission 3 · Kepler-9 | Mission 4 · Narval |
|---|---|---|---|---|
| **Kit d'impression complet** (tout le matériel de jeu) | [PDF](pdf/mission-1-kit-impression.pdf) | [PDF](pdf/mission-2-kit-impression.pdf) | [PDF](pdf/mission-3-kit-impression.pdf) | [PDF](pdf/mission-4-kit-impression.pdf) |
| Fiche de mission (joueurs) | [PDF](pdf/mission-1-fiche-joueurs.pdf) | [PDF](pdf/mission-2-fiche-joueurs.pdf) | [PDF](pdf/mission-3-fiche-joueurs.pdf) | [PDF](pdf/mission-4-fiche-joueurs.pdf) |
| Cartes recto verso | [PDF](pdf/mission-1-cartes.pdf) | [PDF](pdf/mission-2-cartes.pdf) | [PDF](pdf/mission-3-cartes.pdf) | [PDF](pdf/mission-4-cartes.pdf) |
| Documents secrets des rôles | [PDF](pdf/mission-1-documents-secrets.pdf) | [PDF](pdf/mission-2-documents-secrets.pdf) | [PDF](pdf/mission-3-documents-secrets.pdf) | — (pas de rôles) |
| Enveloppes (contenu et étiquettes) | [PDF](pdf/mission-1-enveloppes.pdf) | [PDF](pdf/mission-2-enveloppes.pdf) | [PDF](pdf/mission-3-enveloppes.pdf) | [PDF](pdf/mission-4-enveloppes.pdf) |
| Fiche d'enquête (notes de l'équipe) | [PDF](pdf/mission-1-fiche-enquete.pdf) | [PDF](pdf/mission-2-fiche-enquete.pdf) | [PDF](pdf/mission-3-fiche-enquete.pdf) | [Carnet de bord](pdf/mission-4-carnet-de-bord.pdf) |
| Déroulé du maître du jeu (solutions) | [PDF](pdf/mission-1-deroule-maitre-du-jeu.pdf) | [PDF](pdf/mission-2-deroule-maitre-du-jeu.pdf) | [PDF](pdf/mission-3-deroule-maitre-du-jeu.pdf) | [PDF](pdf/mission-4-deroule-maitre-du-jeu.pdf) |
| Script de doublage (ElevenLabs) | [PDF](pdf/mission-1-script-doublage.pdf) | [PDF](pdf/mission-2-script-doublage.pdf) | [PDF](pdf/mission-3-script-doublage.pdf) | [PDF](pdf/mission-4-script-doublage.pdf) |
