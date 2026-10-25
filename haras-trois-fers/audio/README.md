# Voix et bruitages (ElevenLabs)

Toutes les répliques du jeu sont lues par des voix différentes, pré-enregistrées
avec ElevenLabs, puis jouées par le DFPlayer Mini de la console. L'écran affiche
le même texte en sous-titre.

## Les fichiers

| Fichier | Contenu |
|---|---|
| `voix.json` | Le casting : 10 voix, avec leur description et leurs réglages |
| `mission-1.json` | Les 165 répliques de la mission 1, numérotées |
| `sfx.json` | Les 15 bruitages communs aux 3 missions |
| `generer_audio.py` | Le script qui appelle l'API et range les MP3 pour la carte SD |

Le numéro de chaque réplique est **le nom du fichier sur la carte SD** :
la réplique 120 de la mission 1 devient `sd/01/120.mp3`. Le programme de la
console fera `playFolder(1, 120)`.

## Le casting

| Clé | Personnage | Répliques (M1) |
|---|---|---|
| `narrateur` | Voix de la console, conteur | 85 |
| `papy_marcel` | Les indices | 19 |
| `helene` | Hélène Vasseur | 17 |
| `gendarme` | Vérifications d'alibis, appel | 12 |
| `lucas` | Lucas Bernier | 8 |
| `camille` | Camille Roussel | 6 |
| `delorme` | Marc Delorme | 6 |
| `victoire` | Victoire de Lancey | 5 |
| `antoine` | Antoine Vasseur | 5 |
| `commentateur` | Les dernières minutes | 2 |

La mission 1 représente environ **15 000 caractères**. Prévoyez de la marge pour
refaire les prises ratées : l'offre *Starter* (30 000 caractères par mois)
suffit pour une mission, l'offre *Creator* est plus confortable pour les trois.
Les bruitages consomment des crédits à part.

## Étapes

1. **Créer les voix.** Dans ElevenLabs, ouvrez *Voices → Voice Design* et collez le
   champ `prompt_voice_design` de chaque personnage. Vous pouvez aussi choisir une
   voix française de la *Voice Library* qui correspond à la description.
   Écoutez les propositions et gardez celle qui vous plaît.
2. **Récupérer les identifiants.** Copiez le *Voice ID* de chaque voix dans le champ
   `voice_id` correspondant de `voix.json`.
3. **Tester sur quelques répliques** avant de tout générer :
   ```bash
   export ELEVENLABS_API_KEY=sk_...
   python3 generer_audio.py mission-1.json --seulement 9,120,135,220
   ```
   Si une voix ne convient pas, changez-la ou ajustez ses `reglages`
   (`stability` plus bas = plus expressif, `style` plus haut = plus théâtral),
   puis relancez avec `--forcer`.
4. **Tout générer** :
   ```bash
   python3 generer_audio.py mission-1.json
   python3 generer_audio.py --sfx
   ```
5. **Copier sur la micro-SD du DFPlayer** (formatée en FAT32) le contenu du
   dossier `sd/` : les dossiers `01/` et `99/` doivent être à la racine.

Le script ne régénère pas un fichier qui existe déjà : relancer après une
coupure ne coûte rien. Ajoutez `--essai` pour voir ce qui serait généré sans
consommer de crédits.

## Conseils

- **Modifier un texte** : changez-le dans `mission-1.json`, puis relancez avec
  `--seulement <numéro> --forcer`.
- **Prononciation** : les heures et les montants sont écrits en toutes lettres
  (« trois heures douze »), parce que la synthèse lit mal « 3 h 12 ».
- **Volume** : si certaines voix sortent plus fort que d'autres, on peut
  harmoniser tous les fichiers avec `ffmpeg` (filtre `loudnorm`).
- **Une seule piste à la fois** : le DFPlayer ne mélange pas les sons. Une voix
  interrompt l'ambiance d'écurie, que la console relance ensuite. Pour avoir
  l'ambiance **sous** les voix, il faudra un second DFPlayer, pour environ 3 €.
- **Rythme du galop** (imprévu Comète) : la console joue le son n° 7, un coup de
  sabot, au bon tempo. Elle maîtrise ainsi exactement le rythme à reproduire.
