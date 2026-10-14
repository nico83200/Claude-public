# Affiche A4 générale Jack&Cie

    pip install segno pillow
    python3 scripts/affiche-app/build.py [source]

Affiche de présentation, sans lien avec un salon ni code promo (écuries, selleries, salles d'attente).
QR code vers `https://jackcie.fr/?src=affiche-app` (source à déclarer dans Administration › Codes › Sources ;
passer une autre source en argument pour un lieu précis, par exemple `affiche-ecurie-dupont`).

Produit dans `dist/affiche-app/` :
- `affiche-jackcie-A4-fonds-perdus.pdf` : 216 × 303 mm (A4 + 3 mm de fonds perdus), pour l'imprimeur ;
- `affiche-jackcie-A4.pdf` : format fini A4, pour une imprimante de bureau (vectoriel : s'agrandit en A3 sans perte) ;
- `affiche-jackcie-A4-apercu.png`.

Captures : `site/img/app/mobile-accueil.webp` et `mobile-assistant.webp`. Mentions légales : constante `LEGAL`
(remplacer `PRINTER` par le nom et la ville de l'imprimeur si l'impression lui est confiée).
