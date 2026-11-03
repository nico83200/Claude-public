# Les idées en attente

Des améliorations décidées dans leur principe, mais pas encore réalisées. Chacune se branche
sur une **voie libre du PCA9685** (servomoteurs, ou un MOSFET pour une lumière ou un petit moteur)
ou sur un **départ I2C de réserve** de la plaque C (J11i, J11j). Avant de les réaliser : ajouter
au programme les actions de script correspondantes, au simulateur, au plan de câblage, à la notice
et à la liste d'achat.

## La trappe qui s'ouvre toute seule (pont-levis)

- **Porte** d'environ 80 × 50 mm, découpée dans le **flanc droit** (le tiroir est à gauche), à la
  scie fine ; **deux mini-charnières** de 15 à 20 mm sur le bord **inférieur** : elle s'abaisse
  vers l'extérieur et dévoile une **cachette** (boîte de 80 × 50 × 40 mm collée derrière).
- **Servomoteur MG90S** sur un tasseau, au-dessus de la cachette, axe parallèle aux charnières,
  sur la **voie 1** du PCA9685 (la voie 0 reste au tiroir).
- **Tige de commande** en corde à piano de 1 mm, coudée en Z : à 10 mm de l'axe sur le palonnier,
  à 12 mm de la charnière sur une petite équerre collée derrière la porte. Porte fermée, tige et
  bras **alignés** (point mort) : la porte ne se force pas.
- Plus de 8 cm des lecteurs de cartes et de l'enclume (l'aimant du servomoteur).
- Programme : `{"trappe": "ouvrir"}` / `{"trappe": "fermer"}`, mouvement **lent et réglable**
  (environ 2 s), angles fermé/ouvert à régler, boutons dans l'onglet Atelier du panneau.
- Achats : 1 MG90S (le second du lot), 2 mini-charnières, un bout de corde à piano.

## L'aile au petit écran (OLED bicolore 0,96")

- **L'écran** : l'OLED 0,96" déjà en votre possession (SSD1306, 128 × 64, **bande jaune** des
  16 lignes du haut, bleu en dessous : le jaune sert de barre de titre). À vérifier : **4 broches**
  (I2C) ; adresse 0x3C (ou 0x3D), libre sur le bus. Branché sur le départ de réserve **J11i**.
- **Le mécanisme** : une petite aile (environ 35 × 30 mm) sur l'**arête du haut du flanc droit**.
  Repliée, plaquée contre le flanc, écran tourné vers la paroi (caché) ; elle pivote de **90°**
  et se pose **à plat, dans le prolongement du dessus**, écran vers le haut. Petite butée sous
  l'aile dépliée.
- **Le servomoteur** : MG90S en entraînement direct (palonnier vissé sur l'aile, axe sur la ligne de
  pivot, à travers le flanc), sur une voie libre du PCA9685 (la voie 2).
- **Le câble** : 4 fils silicone très souples, au plus près de l'axe, avec une boucle de mou.
- **Programme** : l'aile se replie au démarrage, en fin de partie et à l'accueil ; l'écran ne
  s'allume qu'aile dépliée ; l'image tourne de 90° ou 270° (à régler) pour se lire depuis l'avant.
  Actions : `{"aile": "deplier"}`, `{"petit_ecran": {"titre": "RADAR", "texte": "…"}}`.
- **À décider** : sa place par rapport à la trappe, sur le même flanc droit (l'une au-dessus de
  l'autre, ou l'aile seule).

## Les autres pistes

- **Cadran à aiguille** : micro-servomoteur sous un hublot de plexiglas, devant au centre
  (X = 23,5 cm, Y = 3,5 cm, fenêtre Ø 45 mm), fond de cadran neutre et habillage par façade.
- **Lampe UV** : LED 395 nm au-dessus de la fente (voie du PCA9685 + MOSFET), ou lampe de poche
  UV cachée dans la trappe.
- **Drapeau qui se lève** par une fente du couvercle (servomoteur sur une voie libre).
- **Les réserves du montage** encore à prévoir : sortie « prolongement des LED » au bout de la
  chaîne, entrées libres P5 à P7 du module des boutons ramenées sur un connecteur, troisième
  PCF8574 en 0x22 sur J11i.
- **Actions génériques** pour les scripts : `servo` (voie, angle, durée), `sortie` (voie, niveau).
