# La console

## 1. Vue d'ensemble

Vue de dessus, l'arrière en haut. Les cartes se **glissent debout dans une fente** ;
un **lecteur à plat**, caché sous le contreplaqué, lit ce qu'on **pose** dessus : une
carte couchée, ou un objet qui cache une puce NFC. Le jeu ne tourne pas qu'autour
des cartes : clavier, voyants, capteur de couleur, œil (gestes et lumière), forge
(souffle), enclume (aimant), horloge et lumière sous la boîte servent aussi aux missions.

```
 ┌──────────────────────────── façade aimantée 38 × 26,2 cm ────────────────────────────┐
 │ ┌─────────┐                                       ┌───────────────────────┐          │
 │ │ 1 2 3 A │    ∴∴∴∴         ╭──────╮              │                       │          │
 │ │ 4 5 6 B │   ∴∴∴∴∴∴        │ ○○○○ │              │      ÉCRAN 3,5"       │          │
 │ │ 7 8 9 C │    ∴∴∴∴         ╰──────╯              │                       │          │
 │ │ * 0 # D │ haut-parleur   anneau 12 LED          └───────────────────────┘  ⊘ clé    │
 │ └─────────┘                                                              ◉ couleur   │
 │  ● ● ● ● ●                                     ┌──────────────┐                     │
 │  voyants 1-5               ▭▭▭▭▭▭▭▭▭▭          │  lecteur à   │                     │
 │  ● ● ● ● ●               fente à cartes        │  plat (objet │          ◎ casque   │
 │  voyants 6-10                                  │    posé)     │                     │
 │   ⁘ forge  ⌂ enclume    [88:88] horloge   ◌ œil └──────────────┘                     │
 │   🔴   🔵   🟡   🟢              ( molette )             ⭕ gros bouton               │
 └──────────────────────────────────────────────────────────────────────────────────────┘
                          sous la boîte : un ruban de LED tourné vers la table
```

- **Façade aimantée** : une plaque fine (2 ou 3 mm : contreplaqué, laiton, alu
  brossé, PVC…) découpée autour de l'écran, du clavier, de la fente et des boutons,
  et tenue par 4 paires d'aimants néodyme. Il y en a une par monde : le plateau du
  haras (mission 1), le plateau du Narval (mission 4). La façade **montre ou cache** le panneau de
  voyants et le capteur de couleur selon la mission, et marque l'emplacement du
  lecteur à plat.
- **La fente** : la carte entre debout, par son bord inférieur, jusqu'à la butée au
  fond d'un puits de 50 mm. Le lecteur RC522 est collé contre la paroi du puits,
  tout au fond, face à la puce de la carte. Le haut de la carte (son nom et son
  pictogramme) dépasse de la fente.
- **Le lecteur à plat** : un second RC522 collé sous le couvercle, à plat. Il lit à
  travers le contreplaqué une carte couchée sur la zone marquée, ou un **objet**
  (figurine, clé, fiole, pierre…) qui cache un autocollant NFC sous sa base.
- **Le clavier 4×4** (membrane ultrasouple) : codes, mots de passe, menus.
- **Le panneau de 10 voyants** : deux rangées de 5 LED, que chaque mission allume à sa
  façon (état du sous-marin, disjoncteurs à réarmer…), ou que la façade cache.
- **Le capteur de couleur** (TCS34725) : sous une petite fenêtre, il reconnaît la
  couleur d'un objet posé dessus (jeton, pierre, fiole…).
- **Le ruban sous la boîte** : la lumière d'ambiance sur la table, aux couleurs du
  monde ; une mission peut aussi s'en servir (alerte, lumière qui baisse…).
- **L'interrupteur à clé** (métal, Ø 12 mm) : une vraie clé à tourner (console de
  commandement, coffre, mise à feu…).
- **L'œil** (APDS-9960) : sous une fenêtre de Ø 10 mm, il reconnaît la main qui passe
  au-dessus (vers le haut, le bas, la gauche ou la droite) et une lampe braquée sur lui.
- **La forge** (BME280) : sous une grille de petits trous, il sent un souffle (l'humidité
  de l'haleine qui monte).
- **L'enclume** (magnétomètre QMC5883L) : collée sous le couvercle, sans trou, elle sent
  un aimant posé dessus, à travers le bois (un fer à cheval, une figurine aimantée…).
- **L'horloge** (afficheur 4 chiffres à HT16K33) : derrière une fenêtre, l'heure du jeu,
  ou les chiffres d'un code pendant qu'on le tape.

Chaque mission se sert de **tout ou partie** de ces éléments. Sa façade cache ceux
dont elle n'a pas besoin (§ 4.5), et son script les déclare (`"materiel"`, voir
`firmware/SCRIPTS.md`), pour que l'écran ne parle pas d'un élément caché.

### Comment les missions voient les deux lecteurs

Les scripts parlent toujours de trois zones, **QUI**, **OÙ** et **QUOI** ; le pupitre
range chaque carte dans la zone de son type :

| On glisse ou on pose… | Elle va sur | Remarque |
|---|---|---|
| un **lieu** (fente) | OÙ | On **reste** dans ce lieu après avoir retiré sa carte, jusqu'au lieu suivant. |
| un **personnage** (fente, ou couché sur le plateau) | QUI | |
| un **objet** (fente ou plateau) | QUOI | Mission 4 : la fente est la main gauche (QUI), le plateau la main droite (QUOI). |
| une **carte Rôle** | la zone laissée libre | Elle vise ce que porte l'autre lecteur : le personnage de la fente, l'objet du plateau. |
| la carte **Monde**, la carte **Aide** | — | Dans la fente. |

Interroger un personnage avec un objet : le personnage dans la fente, l'objet sur
le plateau. Accuser : on se rend sur le lieu (on glisse sa carte puis on la
reprend), on glisse le suspect, on pose l'objet, et l'on appuie sur le gros bouton.
Un pupitre à **trois lecteurs** (QUI, OÙ, QUOI) reste possible : `NB_LECTEURS = 3`
dans `firmware/src/broches.h`.

## 2. Liste des composants

| Composant | Modèle | Qté | Prix approx. |
|---|---|---|---|
| Microcontrôleur | ESP-32S (module ESP32-WROOM-32), USB-C, puce USB CH340 | 1 | (déjà là) |
| Écran | TFT SPI 3,5" ILI9488 480×320, avec lecteur SD | 1 | 12-15 € |
| Lecteurs RFID | RC522 13,56 MHz : la fente et le lecteur à plat | 2 | 6 € |
| Puces | Autocollants NTAG213 ronds de 25 mm (lot de 100) : cartes et objets | 1 | 15 € |
| Audio haut-parleur | DFPlayer Mini, micro-SD et HP 3 W / 4 Ω | 1 | 6 € |
| Audio casque | DFPlayer Mini, micro-SD et **embase jack 3,5 mm stéréo** | 1 | 5 € |
| Casque | Casque filaire jack, de préférence fermé (pour l'immersion) | 1 | 10-15 € |
| Boutons | Arcade 30 mm (rouge, bleu, jaune, vert) | 4 | 6 € |
| Gros bouton | Champignon 60 mm | 1 | 4 € |
| Molette | Encodeur KY-040 | 1 | 2 € |
| Clavier | Clavier membrane 4×4 (nappe de 8 fils, adhésif au dos) | 1 | 2 € |
| Modules I2C | PCF8574 (carte « IO expansion », adresse réglable) : le clavier, les 4 boutons de couleur | 2 | 4 € |
| Capteur de couleur | TCS34725 (module avec LED blanche) | 1 | 4 € |
| Œil | APDS-9960 (gestes, proximité, lumière) | 1 | 4 € |
| Forge | BME280 (humidité, température) : **pas un BMP280**, qui n'a pas d'humidité | 1 | 5 € |
| Enclume | QMC5883L (module « GY-271 ») | 1 | 3 € |
| Horloge | Afficheur 4 chiffres 7 segments 0,56" avec HT16K33 (câblage Adafruit) | 1 | 10 € |
| LED | Anneau WS2812B de 12 LED, 2 LED WS2812B individuelles (fente, plateau) | 1 + 2 | 6 € |
| Voyants | Ruban WS2812B 60 LED/m : 2 morceaux de 5 LED | 10 LED | 3 € |
| Ruban sous la boîte | Ruban WS2812B : 6 à 10 LED | 8 LED | (même ruban) |
| Alimentation | Chargeur USB-C 5 V / 3 A et embase USB-C à visser (alimentation seule) | 1 + 1 | 12 € |
| Interrupteur | Interrupteur à bascule, à encastrer | 1 | 1 € |
| Interrupteur à clé | Métal, perçage Ø 12 mm, 2 positions (repos, tourné), avec 2 clés | 1 | 4 € |
| Façades | Aimants néodyme 10 × 3 mm | 8 par façade | 4 € |
| Divers | Résistances 1 kΩ, 330 Ω, 10 kΩ, condensateur 1000 µF, fils, plaque à souder | — | 6 € |
| **Total** | | | **≈ 120 €** |

## 3. Câblage (ESP-32S, USB-C, CH340)

| Fonction | Broche du composant | GPIO |
|---|---|---|
| **Bus SPI commun** | SCK / MOSI / MISO | **18 / 23 / 19** |
| Écran ILI9488 | CS / DC / RST | 5 / 21 / EN |
| | MISO de l'écran | ⚠️ non branché |
| | LED (rétroéclairage) | 12 (luminosité réglable) |
| | Broches du tactile (T_CLK, T_CS, T_DIN, T_DO, T_IRQ) | ⚠️ non branchées |
| SD de l'écran | CS | 4 |
| **RFID de la fente** | SDA (CS) | 13 |
| **RFID à plat** | SDA (CS) | 22 |
| RFID (×2) | RST | **3,3 V** (réinitialisation logicielle) |
| RFID (×2) | VCC | ⚠️ **3,3 V uniquement** |
| **DFPlayer haut-parleur** (UART2) | RX ← / TX → | TX 17 (via 1 kΩ) / RX 16 |
| **DFPlayer casque** (UART1) | RX ← / TX → | TX 2 (via 1 kΩ) / RX 36 |
| DFPlayer casque | DAC_L / DAC_R / GND | embase jack (pointe / anneau / manchon) |
| **LED** (une seule chaîne) | DIN | 25 (via 330 Ω) : anneau (12), LED de la fente, LED du plateau, voyants (10), ruban sous la boîte (8) |
| Molette KY-040 | CLK / DT / SW | 34 / 35 / 32 |
| Gros bouton | → GND (10 kΩ vers 3,3 V) | 39 |
| **Interrupteur à clé** | → GND quand la clé est tournée (tirage interne) | 33 |
| **Bus I2C** | SDA / SCL | **26 / 27** |
| PCF8574 n° 1 : **le clavier** (adresse 0x20 : A0, A1, A2 à la masse) | P0 à P7 ← nappe du clavier, fils 1 à 8 (lignes 1-4, puis colonnes 1-4) | I2C |
| PCF8574 n° 2 : **les boutons** (adresse 0x21 : A0 sur VCC) | P0 / P1 / P2 / P3 ← boutons rouge / bleu / jaune / vert, l'autre patte à la masse | I2C |
| **Capteur de couleur** TCS34725 (adresse 0x29) | SDA / SCL, VIN 3,3 V, LED : laisser en l'air (allumée) | I2C |
| **Œil** APDS-9960 (0x39) | SDA / SCL, VCC 3,3 V (INT : en l'air) | I2C |
| **Forge** BME280 (0x76, ou 0x77 selon la soudure SDO) | SDA / SCL, VIN 3,3 V | I2C |
| **Enclume** QMC5883L (0x0D) | SDA / SCL, VCC 3,3 V (DRDY : en l'air) | I2C |
| **Horloge** HT16K33 (0x70) | SDA / SCL, VCC 3,3 V ; l'afficheur lui-même en 5 V si le module a une broche V+ séparée | I2C |
| Modules I2C | VCC / GND | ⚠️ **3,3 V** (leurs résistances de tirage I2C sont reliées à VCC) |
| (libres) | — | 14 et 15 |

**L'ordre de la chaîne de LED compte** : le fil de données part de la GPIO 25, entre
dans l'anneau, sort vers la LED de la fente, puis celle du plateau, puis les 10
voyants (la rangée 1-5, puis la rangée 6-10), et finit dans le ruban sous la boîte.
Le nombre de LED de ce ruban se règle dans `NB_LED_SOCLE` (`firmware/src/broches.h`,
8 par défaut). Comptez 60 mA par LED au maximum ; la luminosité des LED est limitée
par le menu des réglages, et le bloc de 3 A suffit largement.

**Pourquoi les boutons de couleur sont passés sur l'I2C :** l'ESP-32S n'avait plus
une seule GPIO libre pour le bus (il ne restait que 0, 1 et 3, réservées au
démarrage et à l'USB). Les quatre boutons de couleur rejoignent donc un module
PCF8574, ce qui libère les GPIO 26 et 27 pour l'I2C. Le pupitre lit ces boutons
plusieurs centaines de fois par seconde : rythmes, séquences et « quatre boutons
ensemble » ne voient pas la différence. Le gros bouton (la dynamo) reste en direct.

**Le clavier** : le pupitre écrit au journal série chaque touche lue (*« Clavier :
7 »*). Si les touches sont mélangées (nappe branchée à l'envers, autre modèle de
clavier), corrigez le tableau `TOUCHES` dans `firmware/src/broches.h`. Un module
**PCF8574A** répond à 0x38 et 0x39 au lieu de 0x20 et 0x21 : le pupitre le trouve
tout seul. Au démarrage, le journal indique les modules qui répondent.

**Le capteur de couleur** : au démarrage, il mesure la lumière « à vide » ; un objet
posé sur sa fenêtre la change nettement (plus claire grâce à sa LED, ou plus sombre
pour un objet noir), et le pupitre annonce sa couleur : rouge, orange, jaune, vert,
cyan, bleu, violet, rose, blanc ou noir. Le journal série affiche chaque mesure :
si une couleur est mal reconnue, ajustez `CAPTEUR_BALANCE` et les seuils dans
`firmware/src/broches.h`. Sans capteur branché, rien ne change.

**L'œil, la forge, l'enclume et l'horloge** sont facultatifs : au démarrage, le journal
série dit lesquels répondent, et chacun écrit ses mesures quand il change d'état
(*« Œil : geste gauche »*, *« Forge : souffle (71.2, repos 48.5 % d'humidité) »*). Les
réglages sont dans `firmware/src/broches.h` :
- **`OEIL_ROTATION`** : si l'œil est monté tourné, les gestes sortent décalés (la main vers
  le haut lue « droite ») ; mettez 90, 180 ou 270 jusqu'à ce qu'ils tombent juste.
- **`OEIL_LAMPE`** : une lampe compte quand la lumière dépasse 3 fois celle de la pièce.
- **`FORGE_HUMIDITE`** : un souffle, c'est l'humidité qui monte de 6 points. Un module
  **BMP280** vendu pour un BME280 (même allure, puce 0x58) n'a pas d'humidité : le pupitre
  suit alors la température (+ 0,8 °C), moins sûr. Vérifiez le journal.
- **`ENCLUME_SEUIL`** : l'écart du champ magnétique qui signale un aimant. L'enclume doit
  rester **à 8 cm au moins** du haut-parleur, des aimants de la façade et de tout fil
  d'alimentation épais. Certains modules « GY-271 » portent un HMC5883L (adresse 0x1E) au
  lieu du QMC5883L : ils ne sont pas reconnus.
- **L'horloge** suit le câblage des afficheurs Adafruit (chiffres aux adresses 0, 2, 6, 8,
  deux-points à l'adresse 4). Si votre module affiche des chiffres décalés, dites-le : la
  table `POSITIONS` de `firmware/src/capteurs_i2c.cpp` s'adapte.

**Alimentation 5 V** (par l'embase USB-C de la face arrière) : l'ESP32 (VIN), les
deux DFPlayer et toutes les LED, avec le condensateur de 1000 µF près de l'anneau.
**3,3 V** (fourni par l'ESP32) : l'écran, les 2 lecteurs RFID, la molette, les deux
PCF8574, le capteur de couleur, l'œil, la forge, l'enclume et l'horloge.

**La carte ESP-32S** : c'est un module ESP32-WROOM-32 (4 Mo de flash) sur une
carte de développement. Toutes les GPIO du tableau existent sur les versions
**30 broches** comme **38 broches** : on suit les numéros GPIO imprimés à côté des
broches (« D25 », « P25 » ou « 25 » selon la sérigraphie), pas leur position.
- Broche **VIN** (parfois « 5V ») : l'entrée 5 V. Broche **3V3** : la sortie 3,3 V.
- **VP** = GPIO 36 (RX du DFPlayer casque) et **VN** = GPIO 39 (gros bouton).
- La LED bleue de la carte est sur la GPIO 2 : elle clignote quand le pupitre
  parle au DFPlayer casque. C'est normal.
- Le régulateur 3,3 V de la carte (AMS1117) alimente l'ESP32, les lecteurs RFID, la
  molette et les modules I2C. Si le module écran accepte le 5 V sur VCC (la plupart
  des modules ILI9488 3,5" ont leur propre régulateur : vérifier sa fiche), le
  brancher sur le 5 V soulage ce régulateur. Ses signaux restent en 3,3 V.

**Pièges à éviter :**
- La GPIO 0 reste libre, pour ne pas gêner le démarrage.
- La GPIO 12 (rétroéclairage) doit être à l'état bas quand la carte démarre : c'est
  le cas avec la broche LED d'un module ILI9488 ordinaire. Si la carte ne démarre
  plus une fois l'écran branché (redémarrages en boucle, *« flash read err »* dans
  le journal), rebrancher la broche LED de l'écran sur le 3,3 V et mettre
  `ECRAN_LED = -1` dans `firmware/src/broches.h` : tout fonctionne, sauf le
  réglage de la luminosité de l'écran.
- La GPIO 2 sert de TX vers le DFPlayer casque. Si le téléversement du programme
  échoue, débranchez ce fil le temps de flasher.
- Les GPIO 34, 35, 36 et 39 sont des entrées seules, sans résistance de tirage
  interne : celles du KY-040 et la résistance de 10 kΩ y suppléent.
- Les modules I2C ont leurs propres résistances de tirage sur SDA et SCL : rien à
  ajouter, mais **alimentez-les en 3,3 V**, jamais en 5 V.
- Les deux lecteurs sont interrogés **l'un après l'autre**, l'antenne de l'autre
  coupée pendant chaque lecture : ils ne se brouillent pas.

## 4. Fabrication du boîtier

Boîtier en contreplaqué de 5 mm, de **38,0 × 26,0 cm**, dont le dessus est incliné
vers le joueur (5 cm de haut à l'avant, 8 cm à l'arrière : pente de 11,5 %, soit
6,6°). Le gabarit de perçage, à l'échelle 1, est dans `pdf/gabarit-facade.pdf`.

### 4.1 Liste de débit (contreplaqué 5 mm)

| Pièce | Qté | Dimensions (cm) | Rôle, assemblage |
|---|---|---|---|
| Couvercle fixe | 1 | 38,0 × 26,2 | Dessus incliné. Recouvre toutes les parois verticales. Percé comme la façade. |
| Face avant | 1 | 38,0 × 4,5 | Hauteur brute : 5,0 cm une fois le couvercle posé. |
| Face arrière | 1 | 38,0 × 7,5 | Hauteur brute : 8,0 cm une fois le couvercle posé. Alimentation et interrupteur. |
| Côtés (trapèzes) | 2 | 25,0 × (7,5 / 4,5) | Se glissent entre l'avant et l'arrière. |
| Fond amovible | 1 | 37,0 × 25,0 | Se glisse à l'intérieur, sur 4 tasseaux, vissé par le dessous. |
| Tasseaux | 4 | 15 × 15 mm | Dans les coins intérieurs, remontés de 5 mm par rapport à la base. |
| Puits de la fente | 1 | voir § 4.3 | Petite goulotte sous la fente (contreplaqué 3 mm, carton fort ou impression 3D). |

### 4.2 Les découpes de la façade et du couvercle

Façade de **38,0 × 26,2 cm** (épaisseur 2 ou 3 mm). Le couvercle fixe reçoit les
mêmes découpes, alignées. Cotes du **centre** de chaque élément, depuis le **coin
avant gauche** (X vers la droite, Y vers l'arrière) :

| Élément | X (cm) | Y (cm) | Découpe |
|---|---|---|---|
| Clavier 4×4 | 5,8 | 20,0 | Fenêtre 70 × 78 mm dans la façade. Le clavier se colle sur le couvercle, qui a une fente de 25 × 3 mm sous son bord avant pour la nappe. |
| Haut-parleur (« interphone ») | 13,3 | 20,0 | Grille de trous Ø 3 mm dans un cercle Ø 40 mm |
| Anneau 12 LED (« lumière du bord ») | 19,0 | 20,0 | Cercle Ø 38 mm (un disque de plexiglas dépoli en diffuseur) |
| Écran TFT 3,5" | 28,5 | 20,0 | Fenêtre rectangulaire selon le gabarit du vendeur (zone visible ≈ 74 × 49 mm) |
| Voyants 1 à 5 | 4,67 · 6,33 · 8,0 · 9,67 · 11,33 | 14,6 | Ø 6 mm (ruban à 60 LED/m : 16,7 mm d'axe en axe). Façade sans voyants : pas de trou. |
| Voyants 6 à 10 | mêmes X | 11,4 | Ø 6 mm |
| Fente à cartes | 19,0 | 13,0 | 68 × 5 mm, dans le sens de la largeur |
| Lecteur à plat | 29,5 | 12,5 | Rien à découper : une zone de 76 × 76 mm marquée sur la façade (le RC522 est dessous, à plat contre le couvercle) |
| Capteur de couleur | 35,8 | 15,5 | Ø 12 mm (le module est dessous, capteur et LED tournés vers le haut). Façade qui le cache : pas de trou. |
| Prise jack 3,5 mm (« téléphone ») | 35,8 | 9,5 | Ø 6 mm (selon l'embase) |
| Interrupteur à clé | 35,8 | 22,0 | Ø 12 mm |
| Forge (BME280) | 6,0 | 7,9 | 9 trous Ø 2 mm dans un cercle Ø 14 mm, dans la façade et le couvercle ; le module juste dessous |
| Enclume (QMC5883L) | 10,5 | 7,9 | Rien : le module est collé sous le couvercle, à plat ; une zone Ø 24 mm marquée sur la façade |
| Horloge (HT16K33) | 17,5 | 8,2 | Fenêtre 50 × 19 mm (selon l'afficheur), l'afficheur affleurant sous le couvercle |
| Œil (APDS-9960) | 23,5 | 8,2 | Ø 10 mm ; le module juste dessous, capteur tourné vers le haut, son côté « haut » vers l'arrière du pupitre |
| 4 boutons d'arcade | 3,5 · 7,5 · 11,5 · 15,5 | 4,0 | 4 × Ø 30 mm, à 40 mm d'axe en axe |
| Encodeur rotatif (molette) | 19,0 | 4,0 | Ø 7 mm (axe du KY-040) |
| Bouton champignon (dynamo) | 29,5 | 4,0 | Selon le bouton : Ø 22 mm pour un champignon à visser, Ø 45 à 60 mm pour un gros dôme d'arcade |
| Aimants (×4) | 1,5 et 36,5 | 1,5 et 24,7 | Ø 10 mm : collés **en surface** du couvercle (ils servent de cales de 3 mm, § 4.5) ; sous la façade, un aimant encastré en face (polarités opposées) ou une rondelle d'acier |

**Par rapport au premier plan (trois fentes) :** une seule fente, au centre ; le
lecteur à plat prend la place de la fente de droite ; les deux rangées de voyants
prennent celle de gauche. Le clavier occupe le coin arrière gauche (seule zone
assez grande), ce qui décale le haut-parleur de 7,5 à 13,3 cm. Les 4 boutons
d'arcade sont centrés sur 9,5 cm (3,5 · 7,5 · 11,5 · 15,5 cm) : centrés sur 7,5 cm,
le premier trou de 30 mm aurait touché la paroi.

### 4.3 La fente, son puits, et le lecteur à plat

- Sous la fente, un **puits** étanche de **50 mm de profondeur** (intérieur
  68 × 5 mm environ) : la carte y descend jusqu'à la butée. Les fentes de la façade
  (2-3 mm) et du couvercle (5 mm) doivent être alignées à 100 %.
- **Le RC522 de la fente** se colle **verticalement** contre une paroi du puits,
  tout au fond, antenne face à la carte. Il fait 40 × 60 mm : posez-le couché
  (60 mm dans le sens de la fente, 40 mm de haut), son bord inférieur au niveau de
  la butée. La **puce NFC est en bas de la carte**, au dos : elle est lue dès que
  la carte arrive en butée. Enfoncée de 57 mm environ, une carte de 88 mm laisse
  dépasser ses 30 mm du haut, où sont imprimés son nom et son pictogramme.
- Hauteur disponible au droit de la fente (Y = 13 cm) : environ 5 cm sous le
  couvercle, fond remonté compris. Le puits touche presque le fond : laissez-le
  ouvert en bas pour le câble du lecteur, ou faites sortir le câble par le côté.
- **Le RC522 à plat** se colle sous le couvercle, centré sous la zone marquée,
  antenne vers le haut. Il lit à travers 8 mm de bois environ (couvercle et
  façade) : une carte couchée (puce en bas, à placer sur la cible marquée) ou un
  objet dont la puce est collée **sous la base**. Évitez le métal entre la puce et
  le lecteur (pièces de monnaie, socles métalliques).

### 4.4 La face arrière et le dessous

- Face arrière (38,0 × 7,5 cm), éléments regroupés à droite : **prise USB-C**
  (alimentation) à X = 34,0 cm, Y = 3,75 cm ; **interrupteur général** à X = 30,0 cm,
  Y = 3,75 cm.
- **Fond** : posé sur les 4 tasseaux, vissé par le dessous, avec des patins
  caoutchouc de 5 à 8 mm de haut.
- **Ruban sous la boîte** : collé sous le fond, près du bord avant, tourné vers la
  table ; ses fils remontent par un trou de 6 mm dans le fond. Les patins laissent
  la lumière s'étaler sur la table.

### 4.5 Une façade par mission : montrer ou cacher

Chaque mission a sa façade : **2 mm de contreplaqué**, découpée et décorée pour
elle. Elle repose sur les **aimants collés en surface du couvercle** (3 mm
d'épaisseur) : elle flotte donc 3 mm au-dessus, ce qui laisse la place de cacher
ce qui ne dépasse pas, et ce dont la mission ne se sert pas reste sous la plaque.

| Élément | Si la mission s'en sert | Sinon |
|---|---|---|
| Écran | Fenêtre (toujours) | — |
| Clavier (membrane, ≈ 1 mm) | Fenêtre 70 × 78 mm | Caché |
| Voyants, anneau de LED | Trous Ø 6 mm, cercle Ø 38 mm | Cachés (la lumière ne traverse pas 2 mm de bois peint) |
| Capteur de couleur | Trou Ø 12 mm | Caché |
| Œil | Trou Ø 10 mm | Caché |
| Forge | 9 trous Ø 2 mm | Cachée (le souffle ne passe plus) |
| Enclume | Zone marquée (rien à découper) | Rien de marqué : elle ne gêne pas |
| Horloge (afficheur à fleur du couvercle) | Fenêtre 50 × 19 mm | Cachée |
| Fente à cartes | Fente 68 × 5 mm | Plaque pleine : la fente est fermée |
| Lecteur à plat | Zone marquée (rien à découper) | Rien de marqué : il ne gêne pas |
| Haut-parleur | Grille de trous | Toujours percé (le son passe mal à travers le bois) |
| Prise jack | Trou Ø 6 mm | Caché si l'écrou dépasse de moins de 3 mm |
| Interrupteur à clé (≈ 10 mm de relief) | Trou Ø 12 mm | Trou Ø 22 mm et un cache rond, ou un cache en relief sur la façade |
| Boutons d'arcade, molette, gros bouton | Trous | Toujours découpés : ils dépassent trop pour être cachés |

Le gabarit (`pdf/gabarit-facade.pdf`) donne toutes les découpes ; chaque façade
n'en reprend que ce qu'il lui faut.

## 5. Les cartes et les objets

Chaque carte porte un autocollant NTAG213, **collé au dos, en bas de la carte**
(le cercle « PUCE NFC » des planches) : c'est le bord qui entre dans la fente, et
le point à placer sur la cible du lecteur à plat. Le pupitre ne lit que son
**identifiant unique** (UID), et chaque monde contient la table de correspondance
`UID → carte`.

**Les objets** : n'importe quel objet devient une « carte » en cachant un
autocollant NTAG213 sous sa base (figurine, fiole, clé en bois, coquillage…). Il
s'enregistre comme une carte (menu « Enregistrer les cartes de ce monde ») et se
pose sur le lecteur à plat.

| Type | Où | Rôle |
|---|---|---|
| **Monde** | Dans la fente, au démarrage | Charge le monde |
| **Lieu** | Dans la fente (on y reste ensuite) | Se rendre quelque part |
| **Personnage** | Dans la fente | Interroger |
| **Objet** | Sur le plateau (ou dans la fente) | Montrer, examiner, utiliser |
| **Rôle** | Dans la fente ou sur le plateau, avec sa cible sur l'autre lecteur | Utiliser un pouvoir |
| **Aide** (Papy Marcel, Fernand, HÉLIOS…) | Dans la fente | Demander un indice |

## 6. Le clavier

| Où | Touches |
|---|---|
| Menus | **1, 2, 3…** : la ligne du même numéro (affiché à l'écran) · **#** : valider · **\*** : retour |
| Codes | Les **chiffres** remplissent les cases ; au dernier chiffre, le code est essayé · **\*** : effacer · **#** : valider |
| Mots de passe (lettres) | Comme sur un téléphone : **2** = A, **22** = B, **222** = C… ; une autre touche, ou une pause d'une seconde, passe à la lettre suivante ; **A** à **D** s'écrivent directement · **#** : valider |
| Choix à 4 couleurs | **1** rouge · **2** bleu · **3** jaune · **4** vert (ou **A** à **D**) |
| Répartition de l'énergie | Un chiffre : la valeur du module, puis le module suivant · **#** : valider |
| Interrupteurs (voyants) | **1** à **9**, puis **0** pour le dixième : basculer · **#** : vérifier · **\*** : tout remettre |
| En jeu, hors épreuve | **#** : le tableau de bord (comme un appui sur la molette) |
| « Appuyez sur la molette » | **#** marche aussi |

## 7. Les cartes micro-SD

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
| `40/` · `41/` | Sous-marin Narval : voix · bruitages |

La SD de l'écran contient les **mondes** : un dossier par monde, avec la table
des cartes, le thème et les scripts des missions. Elle se prépare avec
`python3 outils/preparer_sd.py` (voir [`firmware/`](../firmware/)).

```
/pupitre/repliques.json          sous-titres de la voix du pupitre
/mondes/index.json               la liste des mondes
/mondes/haras/monde.json         nom, couleurs, carte Monde, carte Aide, missions
/mondes/haras/cartes.json        UID de la puce → carte (rempli par l'enregistrement)
/mondes/haras/mission-1.json     le script : lieux, personnages, épreuves, imprévus…
/mondes/haras/repliques-1.json   les sous-titres des répliques
/mondes/haras/scores.json        les parties gagnées
/sauvegarde.json                 partie en cours (reprise après coupure)
```

## 8. Le casque

- Quand une réplique est marquée **🎧 casque**, le pupitre joue un petit carillon
  dans le haut-parleur, affiche *« 🎧 Un seul joueur met le casque »* et attend
  que quelqu'un appuie sur la molette pour lancer le message dans le casque.
- Pendant ce temps, l'écran n'affiche **aucun sous-titre** : seul le joueur
  au casque sait ce qui est dit.
- Certains messages ne passent **qu'une fois**. L'écran le précise avant la
  diffusion.

## 9. Le panneau du maître du jeu (Wi-Fi)

La console crée un réseau Wi-Fi **« Pupitre-des-Enigmes »** (mot de passe
`enigmes123`, modifiable dans `firmware/src/broches.h`). Depuis un téléphone
connecté à ce réseau, la page **http://192.168.4.1** montre le chrono, les cartes
en place et les étapes franchies, et permet de mettre en pause, d'ajouter ou de
retirer du temps, de donner un indice ou d'abandonner.
