# Vidéo publicitaire (réseaux sociaux, 9:16, 24 s)

1. Copier `public/icons/icon-512.png` à côté de `stage.html`.
2. `node render.mjs <dossier>` : rend le décor animé (720 images 1080×1920) et le contour du téléphone (`bezel.png`).
3. `python3 compose.py <dossier> <tournage-muet.mp4>` : incruste les extraits de l'application (vidéo muette du
   tournage du tutoriel, voir `scripts/tutoriel`) dans l'écran du téléphone → `jackcie-pub-9x16.mp4` (H.264, piste audio muette).

Textes et minutage : tableau `scenes` dans `stage.html` ; extraits : liste `clips` dans `compose.py`.

## Son

4. `python3 mix.py <dossier>` : `<dossier>/jackcie-pub-9x16-muet.mp4` (sortie de l'étape 3, renommée) + `audio/voix.mp3`
   (voix off ElevenLabs) + `audio/musique1.mp3` (musique retenue : montée à 8 s, fin à 20 s sur le logo).
   Chaque phrase est placée au début de sa scène, la musique baisse automatiquement sous la voix
   (compression déclenchée par la voix), sonie finale ≈ -14/-16 LUFS, crête ≤ -1 dBFS.
