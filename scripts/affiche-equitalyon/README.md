# Affiche A5 « Spécial EquitaLyon 2026 »

    pip install segno
    python3 scripts/affiche-equitalyon/build.py [url_inscription] [code]

Par défaut : code `EQUITA2026`, QR code vers `https://jackcie.nlapps.fr/register?code=EQUITA2026`
(la page d'inscription retient le code, y compris pour une inscription avec Google ou Apple).

Produit dans `dist/affiche-equitalyon/` :
- `affiche-equitalyon-A5-fonds-perdus.pdf` : 154 × 216 mm (A5 + 3 mm de fonds perdus), pour l'imprimeur ;
- `affiche-equitalyon-A5.pdf` : format fini 148 × 210 mm, pour une imprimante de bureau ;
- `affiche-equitalyon-A5-apercu.png` : aperçu à environ 300 ppp.

Reprend les polices TrueType, le logo NLapps et la capture d'écran de `scripts/affiche/`.
Le code doit exister dans Administration › Codes promo : type « Accès gratuit pour une durée »,
offre « Propriétaire multi-chevaux », 30 jours.
