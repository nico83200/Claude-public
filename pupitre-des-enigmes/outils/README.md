# Outils

## 1. Les voix et les bruitages (ElevenLabs)

Toutes les répliques sont pré-enregistrées avec ElevenLabs, puis jouées par les
deux DFPlayer du pupitre : celui du haut-parleur et celui du casque.

### Les fichiers sources

| Fichier | Contenu | Dossier SD |
|---|---|---|
| `console/audio/voix.json` | La voix du Pupitre | — |
| `console/audio/repliques.json` | Les 17 phrases communes à tous les mondes | `91/` |
| `console/audio/sons.json` | Les 9 bruitages du pupitre | `90/` |
| `mondes/haras/audio/voix.json` | Le casting du Haras (11 voix) | — |
| `mondes/haras/audio/mission-1.json` | Les 178 répliques de la mission 1, dont 13 au casque | `10/` |
| `mondes/haras/audio/sons.json` | Les 14 bruitages du Haras | `11/` |
| `mondes/casse/audio/…` | Monte-Carlo : 10 voix, 172 répliques, 17 bruitages | `20/` · `21/` |
| `mondes/kepler/audio/…` | Kepler-9 : 10 voix, 153 répliques, 17 bruitages | `30/` · `31/` |

Le numéro de chaque réplique est le **nom du fichier** : la réplique 120 du Haras
devient `sd/10/120.mp3`. Une réplique marquée `"sortie": "casque"` est jouée par
le DFPlayer du casque, et toutes les autres par le haut-parleur.

### L'atelier des voix : une seule commande

```bash
cd outils
python3 generer_audio.py --essai        # ce qui reste à faire, et combien de caractères ElevenLabs
python3 generer_audio.py --brouillon    # des voix provisoires, gratuites, avec la voix de l'ordinateur
export ELEVENLABS_API_KEY=sk_...
python3 generer_audio.py                # les vraies voix et les bruitages (demande confirmation)
```

Ce que fait la commande, dans l'ordre :

1. **Elle met d'accord le classeur et le jeu.** Le classeur `suivi/suivi-audio.xlsx` est
   l'endroit le plus simple pour retoucher un texte : modifiez la colonne « Script », et la
   commande reprend le texte dans le jeu (les sous-titres de l'écran suivent). Un texte retouché
   dans le jeu repart dans le classeur. Une réplique nouvelle est ajoutée au classeur. Un
   **Voice ID** collé dans l'onglet « Narrateurs » passe dans les `voix.json`. Si un texte a
   changé des deux côtés, le jeu l'emporte et la commande le signale. Le dernier texte sur
   lequel les deux étaient d'accord est gardé dans `suivi/synchro-classeur.json`.
2. **Elle cherche ce qui est à faire** : un fichier absent, ou dont le texte (ou la voix) a
   changé depuis qu'il a été fabriqué (`sd/empreintes.json` garde la trace de chaque fichier).
   Un MP3 posé à la main (fait sur le site d'ElevenLabs, ou votre propre voix) est gardé tant
   que son texte ne change pas.
3. **Elle fabrique** : ElevenLabs pour les voix et les bruitages, une seule fois par texte
   identique dit par la même voix. Avec `--brouillon`, la voix de l'ordinateur (`say` sur Mac,
   `espeak-ng` sous Linux, la synthèse de Windows) : de quoi jouer tout de suite, avec de vrais
   sons. Les brouillons sont remplacés automatiquement au premier passage avec ElevenLabs.
4. **Elle harmonise les volumes** (ffmpeg, s'il est installé : `brew install ffmpeg` sur Mac) :
   toutes les voix au même niveau, les bruitages un peu en retrait.
5. **Elle met à jour le classeur** : « Fait » et la date pour chaque audio fabriqué ;
   « À refaire » si un audio fait ou validé a vu son texte changer depuis. Un brouillon est noté
   dans « Remarques » et reste « À faire ».

Il reste à **copier le dossier `sd/`** (sans `empreintes.json`) sur les deux cartes micro-SD des
lecteurs audio, formatées en FAT32. Si des textes ont changé, recopiez aussi la carte de l'écran
(`preparer_sd.py`, ou l'onglet **Missions** du panneau Wi-Fi) pour que les sous-titres suivent.

**Créer les voix** dans ElevenLabs (*Voices → Voice Design*), à partir du champ
`prompt_voice_design` des `voix.json` (ou de la colonne « Prompt » de l'onglet Narrateurs). On
peut aussi choisir une voix française de la *Voice Library*. Copiez ensuite chaque *Voice ID*
dans l'onglet « Narrateurs » du classeur : la commande fait le reste.

**Options utiles :**

| Option | Effet |
|---|---|
| `--essai` | Tout montrer, ne rien fabriquer ni modifier |
| `--brouillon` | Voix provisoires de l'ordinateur (pas de bruitages) |
| `--seulement 120-129,135` | Seulement ces numéros (avec un fichier en argument : `../mondes/narval/audio/mission-4.json`) |
| `--forcer` | Refaire même ce qui est à jour |
| `--oui` | Ne pas demander de confirmation |
| `--sans-classeur` | Ne pas toucher au classeur |
| `--sans-normaliser` | Garder les volumes tels quels |
| `--simulation` | Des fichiers muets, pour essayer la chaîne sans compte |

Deux bruitages sont fabriqués par des scripts du dépôt, au rythme exact :
`python3 morse.py` (la balise morse, `sd/31/020.wav`) et `python3 frappe.py` (les coups de
l'Orque, `sd/41/020.wav`).

Au total, il y a environ **1 160 répliques et 137 bruitages** : comptez l'offre *Creator*
d'ElevenLabs pour un mois, ou *Starter* sur deux mois. `--essai` donne le nombre exact de
caractères restant à fabriquer.

**Conseils :**
- Les heures et les montants sont écrits en toutes lettres pour une bonne prononciation.
- Le classeur peut vivre sur le Google Drive : téléchargez-le à la place de
  `suivi/suivi-audio.xlsx` avant de lancer la commande, et renvoyez-le ensuite (ou passez
  `--classeur chemin/vers/le-classeur.xlsx`).

## 2. Les documents à imprimer (PDF)

```bash
pip install markdown
cd outils/impression
python3 construire.py
```

Le script produit dans `pdf/` le **mode d'emploi du pupitre** et, pour chaque
mission, la **fiche des joueurs** et le **déroulé du maître du jeu**. Le rendu
utilise Chromium via Playwright (`rendre.js`).

| Source | PDF |
|---|---|
| `outils/impression/mode-emploi.html` | `pdf/mode-emploi.pdf` |
| `mondes/haras/impression/fiche-mission-1.html` | `pdf/mission-1-fiche-joueurs.pdf` |
| `mondes/haras/impression/plateau.html` | `pdf/mission-1-plateau.pdf` |
| `mondes/haras/mission-1-deroule.md` | `pdf/mission-1-deroule-maitre-du-jeu.pdf` |
