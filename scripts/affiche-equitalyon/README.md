# Affiche A5 « Spécial EquitaLyon 2026 »

    pip install segno
    python3 scripts/affiche-equitalyon/build.py [url_inscription] [code]

Par défaut : code `EQUITA2026`, QR code vers `https://jackcie.fr/?code=EQUITA2026&src=affiche`
(le site retient le code et la source et les transmet à l'inscription dans l'application, y compris dans l'application installée et pour une inscription avec Google ou Apple).

Produit dans `dist/affiche-equitalyon/` :
- `affiche-equitalyon-A5-fonds-perdus.pdf` : 154 × 216 mm (A5 + 3 mm de fonds perdus), pour l'imprimeur ;
- `affiche-equitalyon-A5.pdf` : format fini 148 × 210 mm, pour une imprimante de bureau ;
- `affiche-equitalyon-A5-apercu.png` : aperçu à environ 300 ppp.

Reprend les polices TrueType et le logo NLapps de `scripts/affiche/` ; capture d'écran : `ecran.jpg` (accueil de l'app, issue de `site/img/app/mobile-accueil.webp`).
Le code doit exister dans Administration › Codes promo : type « Accès gratuit pour une durée »,
offre « Propriétaire » (un cheval, toutes les fonctionnalités), 30 jours.
Mentions légales en pied d'affiche : constante `LEGAL` dans `build.py` (remplacer `PRINTER` par le nom et la ville de l'imprimeur si l'impression est confiée à un professionnel).
