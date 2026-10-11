# Affiche A3 (écuries, magasins spécialisés)

Copier `public/brand/jackcie-logo.png` dans ce dossier, puis :

    pip install segno
    python3 build.py <ce dossier> https://jackcie.nlapps.fr/application-mobile nlapps-logo.png

Produit `affiche-jackcie-A3.pdf` (vectoriel, 297×420 mm, polices TrueType intégrées, prêt pour l'imprimeur)
et `affiche-jackcie-apercu.png`. Le QR code (correction d'erreur élevée) pointe vers la page d'installation.

- `fonts/` : Inter (licence SIL OFL) convertie en TrueType — le moteur PDF de Chromium intègre les polices
  OpenType/CFF en Type 3, refusé par certains imprimeurs.
- Les éléments décoratifs doivent rester dans `.deco` (overflow masqué) : un débordement de la page
  ferait réduire toute l'affiche à l'impression.
