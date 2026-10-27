# Les cartes micro-SD, sans script

Le pupitre a **trois** cartes micro-SD, toutes formatées en **FAT32** (32 Go au plus ;
au-delà, l'outil de formatage de Windows ne propose plus FAT32 : prenez une carte de 8 ou
16 Go).

## 1. La carte de l'écran : les mondes et les scripts

Tout est déjà prêt dans `carte-ecran/` (et dans l'archive `carte-sd-ecran.zip`, le même
contenu).

1. Formatez la carte en FAT32.
2. Décompressez `carte-sd-ecran.zip` et copiez **son contenu** à la racine de la carte :
   les dossiers `mondes` et `pupitre` doivent être tout en haut, pas dans un sous-dossier.
3. Mettez la carte dans le lecteur SD de l'écran, puis enregistrez les cartes du jeu
   (menu du monde, « Enregistrer les cartes de ce monde »).

```
carte de l'écran
├── mondes/
│   ├── index.json
│   ├── haras/   (monde.json, mission-1.json, repliques-1.json, cartes.json)
│   ├── casse/   …
│   ├── kepler/  …
│   ├── narval/  …
│   └── atelier/ …  (la mission 5, de test)
└── pupitre/
    └── repliques.json
```

**Pour une nouvelle version plus tard**, copiez tout **sauf** les fichiers
`mondes/*/cartes.json` : ils contiennent les puces NFC que vous avez enregistrées. Les
écraser oblige à refaire l'enregistrement des cartes. Le classement des équipes
(`classement.json`, à la racine) et les réglages (`reglages.json`) ne sont pas dans
l'archive : ils restent en place.

## 2. Les deux cartes des lecteurs audio (haut-parleur et casque)

Les deux cartes ont **exactement le même contenu** : des dossiers numérotés, et dans
chacun des MP3 numérotés sur trois chiffres.

| Dossier | Contenu | Fichiers |
|---|---|---|
| `90` | Bruitages du pupitre | `001.mp3` à `009.mp3` |
| `91` | Voix du pupitre | `001.mp3` à `017.mp3` |
| `10` · `11` | Haras (mission 1) : voix · bruitages | 186 voix · 16 bruitages |
| `20` · `21` | Monte-Carlo (mission 2) : voix · bruitages | 172 · 17 |
| `30` · `31` | Kepler-9 (mission 3) : voix · bruitages | 153 · 17 |
| `40` · `41` | Narval (mission 4) : voix · bruitages | 178 · 26 |
| `50` · `51` | Atelier (mission 5, le banc d'essai) : voix · bruitages | 76 · 9 |

Le numéro de chaque réplique et son texte sont dans le **script de doublage** de chaque
mission (`pdf/mission-N-script-doublage.pdf`, colonne « fichier ») ; les bruitages sont
décrits dans `mondes/<monde>/audio/sons.json` et `console/audio/sons.json`.

**Ces MP3 ne sont pas fournis** : les voix sont à produire. Trois façons :
- **Le script** `outils/generer_audio.py` avec un compte ElevenLabs : il fait tout, et
  range les fichiers comme ci-dessus.
- **Le site d'ElevenLabs**, à la main : collez chaque texte du script de doublage, téléchargez
  le MP3 et renommez-le (`020.mp3` pour la réplique 20) dans le bon dossier.
- **Vos propres voix** : enregistrez-vous au téléphone, convertissez en MP3, et nommez de la
  même façon. Les bruitages se trouvent dans les banques de sons libres.

**Sans aucun MP3, le jeu marche quand même** : le pupitre affiche les sous-titres et
laisse le temps de les lire. Vous pouvez donc commencer à jouer avant d'avoir les voix.

**Bon à savoir pour le DFPlayer** : copiez les dossiers sur une carte fraîchement
formatée, un dossier après l'autre, et ne laissez rien d'autre à la racine (pas de
dossier « System Volume Information » ajouté par Windows si possible, ni de fichiers
cachés « ._ » ajoutés par macOS : sur Mac, éjectez puis lancez `dot_clean` sur la carte).

**Le volume, en chiffres** : environ 900 fichiers (800 répliques, 100 bruitages), 1 h 30
d'audio, une centaine de Mo : une carte de 8 Go suffit largement. Le DFPlayer joue un
fichier par son dossier (01 à 99) et son numéro (001 à 255) ; aucun dossier ne dépasse 255
fichiers (`outils/verifier_scripts.py` le contrôle). Au démarrage, il fait l'inventaire de sa
carte : le pupitre lui laisse jusqu'à 8 secondes, puis écrit sur le moniteur série
« DFPlayer : N fichiers sur la carte ». Si ce nombre ne correspond pas à ce que vous avez
copié, un fichier manque ou un fichier caché s'est glissé sur la carte.
