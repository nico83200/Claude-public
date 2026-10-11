# Vidéo « Nouveautés 2.11 »

Vidéo 9:16 (2 min 22) montrant les vrais écrans de l'application. Muette : la voix off s'enregistre à part
(texte et minutage dans `scenes.json`, champ `text`).

1. Données de démonstration locales (pension de l'Écurie des Tilleuls, ferrures d'Uni pour le rythme habituel,
   lecture de facture en attente) : à créer dans une base de démo, jamais en production.
2. `node scripts/nouveautes/capture.mjs /tmp/nv` — captures téléphone (390×844, ×3) avec le serveur local sur
   127.0.0.1:8011. Les boutons Google/Apple n'apparaissent que si les fournisseurs sont configurés.
3. `node scripts/nouveautes/render.mjs /tmp/nv /tmp/nvout` — 4 276 images 1080×1920 à 30 i/s.
4. `ffmpeg -framerate 30 -i /tmp/nvout/frames/%04d.png -c:v libx264 -preset slow -crf 22 -pix_fmt yuv420p -movflags +faststart nouveautes.mp4`

Le point « statistiques d'usage » (super-administrateur) n'y figure volontairement pas.
