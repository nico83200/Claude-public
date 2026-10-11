# Affiche A3 (écuries, magasins spécialisés)

Copier `public/brand/jackcie-logo.png` dans ce dossier, puis :

    pip install segno
    python3 build.py <ce dossier> https://votre-domaine/application-mobile chemin/vers/logo-nlapps.png

Produit `affiche-jackcie-A3.pdf` (vectoriel, 297×420 mm, impression) et `affiche-jackcie-apercu.png`.
Sans adresse ni logo, des emplacements réservés apparaissent à la place du QR code et du logo NLAPPS.
Le QR code (correction d'erreur élevée, vert Jack&Cie sur fond blanc) pointe vers la page d'installation `/application-mobile`.
