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

### Étapes

1. **Créer les voix** dans ElevenLabs (*Voices → Voice Design*), à partir du champ
   `prompt_voice_design`. On peut aussi choisir une voix française de la *Voice
   Library*.
2. **Copier chaque *Voice ID*** dans le champ `voice_id` du `voix.json` concerné.
3. **Tester quelques répliques :**
   ```bash
   export ELEVENLABS_API_KEY=sk_...
   cd outils
   python3 generer_audio.py ../mondes/haras/audio/mission-1.json --seulement 9,75,120,135
   ```
4. **Tout générer :**
   ```bash
   python3 generer_audio.py ../console/audio/*.json ../mondes/*/audio/*.json
   python3 morse.py    # la balise morse de la mission 3 (sd/31/020.wav)
   python3 frappe.py   # les coups de l'Orque, mission 4 (sd/41/020.wav)
   ```
5. **Copier le dossier `sd/` sur les deux cartes micro-SD**, formatées en FAT32.

Au total (pupitre et trois mondes), il y a environ **45 000 caractères** de voix :
comptez l'offre *Creator* d'ElevenLabs pour un mois, ou *Starter* sur deux mois.

**Conseils :**
- Les heures et les montants sont écrits en toutes lettres pour une bonne
  prononciation.
- Pour régénérer une réplique modifiée : `--seulement 120 --forcer`.
- Pour harmoniser les volumes, on peut passer tous les fichiers dans `ffmpeg`
  avec le filtre `loudnorm`.

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
| `mondes/haras/mission-1-deroule.md` | `pdf/mission-1-deroule-maitre-du-jeu.pdf` |
