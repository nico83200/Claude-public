# T-shirt Jack&Cie

    python3 scripts/tshirt/build.py [source]

QR code (correction d'erreur élevée) vers `https://jackcie.fr/?src=tshirt` : source à déclarer dans Administration › Codes › Sources.

Produit dans `dist/tshirt/` :
- `tshirt-devant.pdf` / `tshirt-devant-300ppp.png` : 28 × 21 cm, centré sur la poitrine (environ 8 cm sous l'encolure) ;
- `tshirt-dos.pdf` / `tshirt-dos-300ppp.png` : 30 × 40 cm, haut du visuel à environ 10 cm sous l'encolure ;
- `tshirt-simulation.png` : simulation sur un t-shirt vert bouteille (taille L).

Fichiers à taille réelle, fond transparent. Couleurs : crème `#f6efe2`, or `#cdb38c`, vert du QR code `#0d3122`
(sur un carré crème : un QR code clair sur fond foncé n'est pas lu par tous les téléphones).
Le logo est passé en version claire automatiquement (le vert devient crème).
