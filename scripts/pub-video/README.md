# Publicité vidéo Jack&Cie (30 s, 1080 × 1920)

Projet [HyperFrames](https://hyperframes.heygen.com) : la vidéo est écrite en HTML et rendue image par image.

- `index.html` : les trois scènes, les sous-titres, la voix off et les bruitages.
- `compositions/` : `papier.html` (la question et les papiers), `appli.html` (les vrais écrans), `fin.html` (prix et appel à l'action), `soustitres.html`.
- `assets/screens/` : captures réelles de l'appli sur le compte de démonstration (téléphone 390 × 844, densité 3).
- `assets/voice/voix-elevenlabs.mp3` : la voix off utilisée (ElevenLabs, fournie). Les fichiers `l1.wav` à `l9.wav` sont l'ancienne voix locale (Kokoro), gardée en secours ; le texte est dans `lines.txt`.
  « Jack&Cie » y est écrit « Djak et compagnie » pour que la voix le prononce bien.
- `assets/sfx/` : bruitages fournis avec les skills HyperFrames (voir `CREDITS.md`).
- `SPEC.md` : le découpage plan par plan.

Rendu : `npx hyperframes render --quality delivery --output renders/jackcie-pub-elevenlabs.mp4` (32,5 s)
Aperçu modifiable : `npx hyperframes preview`
