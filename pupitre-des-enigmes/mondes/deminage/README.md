# Monde : Brigade de déminage

Un jeu de **communication** coopératif, de 2 à 8 joueurs : un **démineur** au pupitre voit la
bombe mais n'a pas le droit de lire le manuel ; les **experts** ont le manuel mais ne regardent
pas l'écran. 12 minutes par bombe, et le pupitre tire au sort une bombe parmi **12** : un
numéro de série, 5 modules sur 8, des réglages. On change de démineur à chaque bombe.

| Élément | Choix |
|---|---|
| **Mission** | 6 · Désamorçage (🟠 rapide et tendu, 12 min par bombe, sans rôles) · [déroulé et solutions](mission-6-deroule.md) |
| **Carte Monde** | M6 « Brigade de déminage » ; carte Aide A6 « Le QG » |
| **Les modules** | Fils (voyants + clavier), Détonateur (boutons), Clavier (code + horloge), Surchauffe (forge), Viseur (œil), Nuancier (capteur de couleur), Radio (casque), Stabilisateur (gros bouton) ; puis la clé |
| **Voix** | [`audio/voix.json`](audio/voix.json) (le QG et la bombe, dossier SD `60/`) |
| **Bruitages** | [`audio/sons.json`](audio/sons.json) (dossier SD `61/`) |
| **Script du pupitre** | [`pupitre/mission-6.json`](pupitre/mission-6.json) |
| **Documents à imprimer** | [`impression/`](impression/) : fiche de mission (démineur, pastilles), **manuel de désamorçage** (experts), cartes |

## Tout vient d'un générateur

Les règles du manuel sont écrites une seule fois, en Python, dans [`generer.py`](generer.py) :
le script tire les 12 bombes (tirage figé), en déduit les solutions, et produit d'un même jet le
script du pupitre, les répliques, le manuel, le déroulé avec les solutions et des parties de test
pour le simulateur. Pour changer une règle ou le nombre de bombes :

```
python3 mondes/deminage/generer.py
```

Le nombre de bombes est limité par la mémoire de l'ESP32, qui charge tout le script : 12 bombes
donnent un script du même ordre que celui de la mission 4.

## Les cartes du monde (10)

| Type | Cartes |
|---|---|
| Monde (1) | Brigade de déminage |
| Modules (8) | Fils, Détonateur, Clavier, Surchauffe, Viseur, Nuancier, Radio, Stabilisateur |
| Aide (1) | Le QG |
