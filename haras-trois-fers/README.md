# Les Mystères du Haras des Trois Fers

Escape game de table pour adultes (2 à 6 joueurs), autour d'une console ESP32
(écran, lecteur RFID, boutons, molette, son, LED). Le thème est équestre, mais
**aucune connaissance du cheval n'est nécessaire** : tout ce qu'il faut savoir
est dans le jeu.

## Les missions

| # | Mission | Difficulté | Durée | Statut |
|---|---|---|---|---|
| 1 | [La Disparition d'Éclipse](mission-1-disparition-eclipse.md) · [déroulé](mission-1-deroule.md) | 🟢 Accessible | 60 min | Scénario, déroulé et voix écrits |
| 2 | Course Truquée à Longchamp | 🟠 Intermédiaire | 60 min | À écrire |
| 3 | Le Testament du Fondateur | 🔴 Difficile | 75 min | À écrire |

## Matériel commun

- **9 cartes Lieu** : Écurie, Sellerie, Bureau, Manège, Carrière, Pré du Bas,
  Forge, Sentier des bois, Vieille Grange
- **6 cartes Rôle** (joueurs) : Maréchal-ferrant, Vétérinaire, Journaliste,
  Expert-comptable, Parieur, Ancien gendarme
- **Cartes Personnage** et **cartes Objet** : propres à chaque mission
- **1 carte « Papy Marcel »** : les indices

## Console (ESP32 WROOM DevKit)

| Composant | Broches ESP32 |
|---|---|
| Bus SPI commun | SCK 18, MOSI 23, MISO 19 |
| Écran ILI9488 3,5" | CS 5, DC 21, RST → EN (MISO écran non branché) |
| SD de l'écran | CS 4 |
| RFID RC522 (3,3 V !) | SDA 13, RST 22 |
| DFPlayer Mini | TX ESP 17 (via 1 kΩ) → RX, RX ESP 16 ← TX |
| Anneau WS2812B 12 LED | DIN 25 (via 330 Ω) |
| Encodeur KY-040 | CLK 34, DT 35, SW 32 |
| Boutons rouge / bleu / jaune / vert | 33 / 26 / 27 / 14 (vers GND) |
| Gros bouton « Accusation » | 39 (vers GND, 10 kΩ vers 3,3 V) |

## Voix

Toutes les répliques sont pré-enregistrées avec ElevenLabs : voir [audio/](audio/README.md).
