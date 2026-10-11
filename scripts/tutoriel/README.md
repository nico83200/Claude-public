# Tournage du tutoriel mobile

Outils utilisés pour produire `public/videos/tutoriel-mobile.mp4` à partir de l'application réelle
(base de **développement** avec les données de démonstration — ne jamais lancer `fixtures.php` en production).

1. `php scripts/tutoriel/fixtures.php 0` — prépare les données (pension récurrente, cours du matin…) ; `1` à la fin pour rétablir le super-administrateur de démo.
2. `python3 scripts/tutoriel/mock_claude.py &` — faux serveur de l'API Claude : rejoue la réponse de l'assistant
   pour la scène « Cours collectif demain à 16 h avec Jimmy sur Uni » (le traitement dans Jack&Cie reste réel).
3. Application lancée avec `ANTHROPIC_API_KEY=demo ANTHROPIC_BASE_URL=http://127.0.0.1:8098 ELEVENLABS_API_KEY=demo`
   sur http://127.0.0.1:8011 (`php -S`).
4. `node scripts/tutoriel/record.mjs <dossier> <profil>` — joue les scènes de `scenes.json` sur un téléphone émulé
   (390×844, ×2) ; chaque scène dure le temps de lire sa voix off (2,5 mots/s).
5. `python3 scripts/tutoriel/assemble.py <dossier>` puis `python3 scripts/tutoriel/subs.py <dossier> scripts/tutoriel/scenes.json`
   — MP4 H.264 30 i/s, image d'aperçu, sous-titres WebVTT et texte minuté de la voix off.

Pour modifier le tutoriel : changer le texte ou les actions d'une scène, relancer, puis copier
`tutoriel-mobile.mp4/.vtt/.jpg` dans `public/videos/` et mettre à jour `docs/TUTORIEL-VOIX-OFF.md`.

## Voix off

6. Avec un enregistrement de la voix off : `python3 scripts/tutoriel/caler-voix.py <dossier>` recale chaque scène
   sur la durée réelle de son paragraphe (accélération ou ralenti léger des images), intègre le son (AAC dans le MP4,
   Opus dans le WebM à produire ensuite) et cale les sous-titres sur les phrases détectées.

## Version ordinateur

`ordinateur/record.mjs <dossier> <profil> ordinateur/durees.json` tourne les mêmes scènes en 1280×720 (×1,5 → 1920×1080),
avec curseur visible ; chaque scène dure exactement son paragraphe de voix off (`durees.json`, issu du calage mobile).
`ordinateur/assemble.py <dossier> voix.mp3 ordinateur/durees.json` ajuste chaque scène à la durée cible et intègre la voix.
Les sous-titres sont ceux du mobile (même voix, mêmes temps) : `tutoriel-ordinateur.vtt` = copie de `tutoriel-mobile.vtt`.
