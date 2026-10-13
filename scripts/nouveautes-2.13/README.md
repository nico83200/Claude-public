# Vidéo « Nouveautés 2.13 »

Vidéo 9:16 (environ 2 min 40) montrant les vrais écrans de l'application, des nouveautés publiées depuis la vidéo 2.11
(2.12 à 2.13.1). Voix off à enregistrer à part (texte : `video/voix-off-nouveautes-2.13.txt`).

1. Base de **démonstration** locale uniquement (jamais en production) : fiche d'urgence d'Uni activée, profils sans
   démarrage masqué. Lancer `python3 scripts/nouveautes-2.13/mock_claude.py &` (réponses scriptées de Claude : question
   sur le dossier, plan de séance, ordonnance, synthèse vétérinaire), puis l'application avec
   `ANTHROPIC_API_KEY=demo ANTHROPIC_BASE_URL=http://127.0.0.1:8098 ELEVENLABS_API_KEY=demo` sur http://127.0.0.1:8011.
2. `node scripts/nouveautes-2.13/capture.mjs /tmp/nv213` — 30 captures téléphone (390×844, ×3).
3. `python3 scripts/nouveautes-2.13/scenes.py` — scènes et minutage estimé (2,7 mots/s).
   Avec la voix enregistrée : `python3 scripts/nouveautes-2.13/caler-voix.py voix.mp3` (recalage sur les pauses).
4. `node scripts/nouveautes-2.13/render.mjs /tmp/nv213 /tmp/nv213out` — images 1080×1920 à 30 i/s.
5. `ffmpeg -framerate 30 -i /tmp/nv213out/frames/%04d.png [-i voix.mp3 -itsoffset 0.6] -c:v libx264 -preset slow -crf 22 -pix_fmt yuv420p -movflags +faststart nouveautes-2.13.mp4`
6. `python3 scripts/nouveautes-2.13/textes.py scripts/nouveautes-2.13/video` — texte de la voix off et sous-titres WebVTT.

Les fonctions réservées au super-administrateur (e-mails, surveillance) et les correctifs techniques n'y figurent pas.
