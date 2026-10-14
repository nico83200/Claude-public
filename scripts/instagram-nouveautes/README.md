# Publications Instagram « Nouveautés » (3:4, 1080 × 1440)

1. Base de démonstration locale, application sur http://127.0.0.1:8012 avec le faux Claude
   (`scripts/nouveautes-2.13/mock_claude.py`, :8098) et une fausse voix ElevenLabs sur :8099.
2. `EMERGENCY_TOKEN=… node scripts/instagram-nouveautes/capture.mjs` → `cap/*.png`
3. `node scripts/instagram-nouveautes/render.mjs` → `publications/jackcie-*.png` (un visuel par entrée de `posts.json`)

Légendes et hashtags : `publications/textes-publications.md`.
