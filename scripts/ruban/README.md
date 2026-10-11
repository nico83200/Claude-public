# Vidéo « Mesurer le poids au ruban »

Animation 9:16 (71 s) affichée dans la fenêtre « Comment mesurer au ruban » de l'onglet Poids d'un cheval.
La vidéo est muette : les sous-titres (`public/videos/mesure-ruban.vtt`) reprennent la voix off ci-dessous.

## Fabrication

```sh
node scripts/ruban/render.mjs /tmp/ruban            # 2 136 images 1080×1920 (30 i/s)
node scripts/ruban/render.mjs /tmp/ruban 4,28,46       # aperçus seulement
ffmpeg -framerate 30 -i /tmp/ruban/frames/%04d.png -vf scale=720:1280:flags=lanczos \
  -c:v libx264 -preset slow -crf 24 -pix_fmt yuv420p -movflags +faststart public/videos/mesure-ruban.mp4
```

- `stage.html` : la scène, entièrement déterminée par `render(t)` (minutage des scènes dans `S`).
- `horse.svg` : silhouette du cheval, injectée par `render.mjs`.
- `scenes.json` / `voix-off.json` : texte et minutage de la voix off (sert aux sous-titres).

Pour ajouter la voix enregistrée : `ffmpeg -i mesure-ruban.mp4 -i voix.m4a -c:v copy -c:a aac -shortest sortie.mp4`.

## Texte de la voix off

- **0:00.6** — Pas de balance à l'écurie ? Un simple mètre ruban suffit pour estimer le poids de votre cheval.
- **0:08.2** — Placez votre cheval d'aplomb, sur un sol plat, la tête dans l'axe du corps. Prenez un mètre ruban souple, celui de la couture fait très bien l'affaire.
- **0:19.6** — Mesurez d'abord le tour de poitrine : faites passer le ruban juste derrière le garrot et derrière les coudes, bien à plat, sans serrer. Lisez la mesure à la fin d'une expiration.
- **0:32.9** — Mesurez ensuite la longueur du corps, en ligne droite, de la pointe de l'épaule jusqu'à la pointe de la fesse.
- **0:41.5** — Dans Jack&Cie, ouvrez l'onglet Poids de votre cheval, choisissez « Ruban et mètre » et saisissez les deux mesures. Le poids estimé s'affiche aussitôt.
- **0:52.8** — Pour une courbe fiable, mesurez toujours de la même façon, au même moment de la journée. L'estimation peut s'écarter de quelques pour cent : c'est l'évolution qui compte.
- **1:05.5** — Et si le poids varie trop vite, Jack&Cie vous prévient.
