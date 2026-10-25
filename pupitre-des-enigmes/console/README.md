# La console

## 1. Vue d'ensemble

```
   ┌──────────────────────── ÉCRAN 3,5" ───────────────────────┐
   │                                                           │
   └───────────────────────────────────────────────────────────┘
    (anneau chrono)                                  (haut-parleur)
      ┌─────────┐          ┌─────────┐          ┌─────────┐
      │ 👤 QUI  │          │ 📍 OÙ   │          │ 🔍 QUOI │
      └────•────┘          └────•────┘          └────•────┘   • = LED de zone
     🔴 🔵 🟡 🟢            ( molette )            ⭕ gros bouton     🎧 prise casque
```

- **Façade aimantée** : une plaque fine (bois, laiton, alu brossé…), découpée
  autour de l'écran, des zones et des boutons, et tenue par 4 aimants néodyme
  collés dans le boîtier. Il y en a une par monde.
- Les zones QUI / OÙ / QUOI sont repérées par des **pictogrammes universels**
  (silhouette, repère, loupe). La façade de chaque monde les reprend dans son style.
- Les lecteurs RFID sont **espacés d'au moins 10 cm**, d'antenne à antenne, pour
  ne pas se brouiller : le pupitre fait environ 38 × 26 cm.

## 2. Liste des composants

| Composant | Modèle | Qté | Prix approx. |
|---|---|---|---|
| Microcontrôleur | ESP32 WROOM DevKit | 1 | (déjà là) |
| Écran | TFT SPI 3,5" ILI9488 480×320, avec lecteur SD | 1 | 12-15 € |
| Lecteurs RFID | RC522 13,56 MHz | 3 | 9 € |
| Cartes | Autocollants NTAG213 (lot de 100) | 1 | 15 € |
| Audio haut-parleur | DFPlayer Mini, micro-SD et HP 3 W / 4 Ω | 1 | 6 € |
| Audio casque | DFPlayer Mini, micro-SD et **embase jack 3,5 mm stéréo** | 1 | 5 € |
| Casque | Casque filaire jack, de préférence fermé (pour l'immersion) | 1 | 10-15 € |
| Boutons | Arcade 30 mm (rouge, bleu, jaune, vert) | 4 | 6 € |
| Gros bouton | Champignon 60 mm | 1 | 4 € |
| Molette | Encodeur KY-040 | 1 | 2 € |
| LED | Anneau WS2812B de 12 LED et 3 LED WS2812B individuelles | 1 + 3 | 6 € |
| Alimentation | Bloc 5 V / 3 A | 1 | 9 € |
| Façades | Aimants néodyme 10 × 3 mm | 8 | 4 € |
| Divers | Résistances 1 kΩ, 330 Ω, 10 kΩ, condensateur 1000 µF, fils, plaque à souder | — | 6 € |
| **Total** | | | **≈ 95 €** |

## 3. Câblage (ESP32 WROOM DevKit)

| Fonction | Broche du composant | GPIO |
|---|---|---|
| **Bus SPI commun** | SCK / MOSI / MISO | **18 / 23 / 19** |
| Écran ILI9488 | CS / DC / RST | 5 / 21 / EN |
| | MISO de l'écran | ⚠️ non branché |
| SD de l'écran | CS | 4 |
| **RFID QUI** | SDA (CS) | 13 |
| **RFID OÙ** | SDA (CS) | 22 |
| **RFID QUOI** | SDA (CS) | 15 |
| RFID (×3) | RST | **3,3 V** (réinitialisation logicielle) |
| RFID (×3) | VCC | ⚠️ **3,3 V uniquement** |
| **DFPlayer haut-parleur** (UART2) | RX ← / TX → | TX 17 (via 1 kΩ) / RX 16 |
| **DFPlayer casque** (UART1) | RX ← / TX → | TX 2 (via 1 kΩ) / RX 36 |
| DFPlayer casque | DAC_L / DAC_R / GND | embase jack (pointe / anneau / manchon) |
| LED (anneau puis 3 zones) | DIN | 25 (via 330 Ω), chaînées : 12 + 3 = 15 LED |
| Molette KY-040 | CLK / DT / SW | 34 / 35 / 32 |
| Boutons rouge / bleu / jaune / vert | → GND | 33 / 26 / 27 / 14 |
| Gros bouton | → GND (10 kΩ vers 3,3 V) | 39 |

**Alimentation 5 V** : l'ESP32 (VIN), les deux DFPlayer et les LED, avec le
condensateur de 1000 µF près des LED. **3,3 V** (fourni par l'ESP32) : l'écran,
les 3 lecteurs RFID et la molette.

**Pièges à éviter :**
- GPIO 0 et 12 restent libres, pour ne pas gêner le démarrage.
- La GPIO 2 sert de TX vers le DFPlayer casque. Si le téléversement du programme
  échoue, débranchez ce fil le temps de flasher.
- Les GPIO 34, 35, 36 et 39 sont des entrées seules, sans résistance de tirage
  interne : celles du KY-040 et la résistance de 10 kΩ y suppléent.
- Pour limiter le brouillage, les 3 lecteurs sont interrogés **l'un après
  l'autre**, et l'antenne des deux autres est coupée pendant chaque lecture.

## 4. Les cartes

Chaque carte porte un autocollant NTAG213. Le pupitre ne lit que son
**identifiant unique** (UID), et chaque monde contient la table de
correspondance `UID → carte`.

| Type | Où la poser | Rôle |
|---|---|---|
| **Monde** | N'importe quel lecteur, au démarrage | Charge le monde |
| **Lieu** | OÙ | Se rendre quelque part |
| **Personnage** | QUI | Interroger |
| **Objet** | QUOI | Montrer, examiner |
| **Rôle** | Un lecteur libre | Utiliser un pouvoir |
| **Aide** (Papy Marcel, Fernand, HÉLIOS…) | N'importe quel lecteur | Demander un indice |

## 5. Les cartes micro-SD

Les **deux DFPlayer ont la même carte** : on copie le même dossier `sd/` sur les
deux, et le programme choisit le lecteur (haut-parleur ou casque) selon la
réplique.

| Dossier | Contenu |
|---|---|
| `90/` | Bruitages du pupitre (bip, erreur, imprévu, casque…) |
| `91/` | Voix du pupitre (démarrage, choix du monde, casque, pause…) |
| `10/` · `11/` | Haras : voix · bruitages |
| `20/` · `21/` | Monte-Carlo 1928 : voix · bruitages |
| `30/` · `31/` | Station Kepler-9 : voix · bruitages |

La SD de l'écran contient les **mondes** : un dossier par monde, avec la table
des cartes, les scénarios en JSON, les images et le thème.

```
/mondes/haras/monde.json        nom, couleurs, polices, dossiers audio
/mondes/haras/cartes.json       UID → carte
/mondes/haras/mission-1.json    lieux, personnages, réactions, énigmes, imprévus
/mondes/haras/images/...        illustrations 16 bits
/sauvegarde.json                partie en cours (reprise après coupure)
```

## 6. Le casque

- Quand une réplique est marquée **🎧 casque**, le pupitre joue un petit carillon
  dans le haut-parleur, affiche *« 🎧 Un seul joueur met le casque »* et attend
  que quelqu'un appuie sur la molette pour lancer le message dans le casque.
- Pendant ce temps, l'écran n'affiche **aucun sous-titre** : seul le joueur
  au casque sait ce qui est dit.
- Certains messages ne passent **qu'une fois**. L'écran le précise avant la
  diffusion.

## 7. Le panneau du maître du jeu (Wi-Fi)

La console crée un réseau Wi-Fi **« Pupitre-des-Enigmes »**. Depuis un téléphone,
on y suit la partie et on peut envoyer un indice, mettre en pause ou ajouter du
temps.
