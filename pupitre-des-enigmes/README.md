# Le Pupitre des Énigmes

Une console d'escape game de table pour adultes (2 à 6 joueurs), construite
autour d'un ESP32. Elle a un écran, **3 lecteurs RFID (QUI · OÙ · QUOI)**, des
boutons, une molette, des voix pré-enregistrées et un **casque pour les messages
privés**.

Le pupitre est **multi-mondes** : chaque univers se charge en posant sa **carte
Monde** sur un lecteur, et s'habille d'une **façade aimantée** à son image.

## Les mondes

| Monde | Mission | Difficulté | Durée | Statut |
|---|---|---|---|---|
| 🐴 [Le Haras des Trois Fers](mondes/haras/) | 1 · [La Disparition d'Éclipse](mondes/haras/mission-1-disparition-eclipse.md) · [déroulé](mondes/haras/mission-1-deroule.md) | 🟢 Accessible | 60 min | Scénario, déroulé, voix, PDF |
| 🎰 [Monte-Carlo 1928](mondes/casse/) | 2 · Le Casse du Siècle | 🟠 Intermédiaire | 60 min | Pitch validé |
| 🚀 [Station Kepler-9](mondes/kepler/) | 3 · Signal Perdu | 🔴 Difficile | 75 min | Pitch validé |

## Le dépôt

| Dossier | Contenu |
|---|---|
| [`console/`](console/) | Le matériel, le câblage, l'organisation des cartes SD, les sons et la voix du pupitre |
| [`mondes/`](mondes/) | Un dossier par monde : scénarios, déroulés, répliques, documents à imprimer |
| [`outils/`](outils/) | Génération des voix (ElevenLabs) et fabrication des PDF |
| [`pdf/`](pdf/) | Les documents prêts à imprimer |

## Les documents à imprimer

| PDF | Pour qui |
|---|---|
| [Liste d'achat](pdf/liste-achats.pdf) | Le constructeur (source : `console/achats.json`) |
| [Mode d'emploi du pupitre](pdf/mode-emploi.pdf) | Tout le monde, valable pour tous les mondes |
| [Mission 1 · fiche de mission](pdf/mission-1-fiche-joueurs.pdf) | Les joueurs |
| [Mission 1 · déroulé](pdf/mission-1-deroule-maitre-du-jeu.pdf) | Le maître du jeu (contient les solutions) |
| [Mission 1 · script de doublage](pdf/mission-1-script-doublage.pdf) | Celui qui enregistre les voix avec ElevenLabs |
