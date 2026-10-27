# Mission 6 : Désamorçage, le déroulé

> **Le Pupitre des Énigmes** · Monde : Brigade de déminage. Un jeu coopératif de 2 à 8 joueurs, 12 minutes par bombe,
> rejouable : le pupitre tire au sort une bombe parmi 16. Document produit par `mondes/deminage/generer.py`.
>
> **Repères sonores** : 🔊 20 désigne une réplique du dossier SD `60` ; `61/…` les bruitages.

---

## 1. Le principe

| Situation | Effet |
|---|---|
| **Les rôles** | Un **démineur** au pupitre : il voit la bombe, ne lit pas le manuel. Les **experts** (1 à 7) ont le manuel et ne regardent pas l'écran. On change de démineur à chaque bombe. |
| **La bombe** | Tirée au sort parmi 16 : un numéro de série, **5 modules** sur 8, des réglages. Le numéro et les modules s'affichent à l'armement, et dans le tableau de bord (appui sur la molette). |
| **Un module** | Le démineur glisse sa carte dans la fente. Module absent : 🔊 15 ; déjà désamorcé : 🔊 12. |
| **Une erreur** | `61/4`, une minute de moins (🔊 33, 🔊 34) ; la lumière sous la boîte passe à l'orange, puis au rouge rapide. **À la 3ᵉ, la bombe explose.** |
| **Les voyants** | 1 à 5 : les fils du module Fils ; 6 à 10 : les modules désamorcés (vert). |
| **L'horloge** | Le compte à rebours, et les codes pendant leur saisie. |
| **La fin** | Les 5 modules désamorcés : 🔊 35, la clé en 15 s. Victoire : `61/7`, 🔊 60 et le rang. Temps écoulé ou 3 erreurs : `61/6`, 🔊 65. |
| **L'imprévu** | Minute 5 : capteur de vibrations, 8 s sans toucher (🔊 37). À 2 minutes de la fin : 🔊 39. |
| **Le QG** (carte A6) | 4 conseils généraux ; 2 gratuits, puis 1 minute chacun. |

## 2. Les 16 bombes et leurs solutions

| Bombe | N° de série | Impair | Voyelle | Modules et solutions |
|---|---|---|---|---|
| 1 | EF-5541 | oui | oui | **Détonateur** « EXPLOSER » → VERT · **Clavier** (IGLOO, PIRATE, ANCRE, BALLON) → 3720 · **Surchauffe** 79 °C → VERT puis souffler · **Radio** AMBRE → 185 · **Stabilisateur** (rythme 0, 300, 600 ms) |
| 2 | TD-8347 | oui | non | **Fils** (bleu, jaune, bleu, jaune, jaune) → fil 2 · **Surchauffe** 83 °C → VERT puis souffler · **Nuancier** niveau 1 → pastille bleu · **Radio** SAPHIR → 473 · **Stabilisateur** (rythme 0, 400, 600, 1000 ms) |
| 3 | AW-8784 | non | oui | **Détonateur** « ANNULER » → BLEU · **Clavier** (JOKER, NUAGE, PIRATE, IGLOO) → 0265 · **Surchauffe** 97 °C → VERT puis souffler · **Radio** SAPHIR → 472 · **Stabilisateur** (rythme 0, 500, 750, 1000 ms) |
| 4 | GG-7260 | non | non | **Fils** (bleu, rouge, bleu, blanc, vert) → fil 3 · **Clavier** (LAMPE, FUSÉE, ÉCLAIR, OURS) → 8844 · **Surchauffe** 87 °C → VERT puis souffler · **Viseur** DELTA → vers la droite, vers le bas · **Stabilisateur** (rythme 0, 250, 750 ms) |
| 5 | OR-6757 | oui | oui | **Clavier** (CACTUS, LAMPE, KAYAK, PIRATE) → 2389 · **Viseur** FOXTROT → vers la droite, vers la gauche · **Nuancier** niveau 1 → pastille rouge · **Radio** AMBRE → 185 · **Stabilisateur** (rythme 0, 400, 600, 1000 ms) |
| 6 | FW-5993 | oui | non | **Fils** (blanc, bleu, jaune, vert, bleu) → fil 2 · **Détonateur** « MAINTENIR » → BLEU · **Viseur** DELTA → vers le bas, vers la droite · **Nuancier** niveau 1 → pastille bleu · **Stabilisateur** (rythme 0, 500, 750, 1000 ms) |
| 7 | IM-0388 | non | oui | **Détonateur** « EXPLOSER » → VERT · **Surchauffe** 91 °C → VERT puis souffler · **Viseur** GOLF → vers le bas, vers le haut · **Nuancier** niveau 4 → pastille jaune · **Radio** JADE → 720 |
| 8 | FT-8718 | non | non | **Fils** (rouge, blanc, vert, rouge, blanc) → fil 5 · **Clavier** (OURS, HIBOU, DRAGON, GUITARE) → 4612 · **Nuancier** niveau 1 → pastille bleu · **Radio** RUBIS → 208 · **Stabilisateur** (rythme 0, 250, 750 ms) |
| 9 | OX-9359 | oui | oui | **Fils** (bleu, jaune, rouge, rouge, rouge) → fil 1 · **Détonateur** « EXPLOSER » → VERT · **Clavier** (ÉCLAIR, FUSÉE, PIRATE, HIBOU) → 6284 · **Surchauffe** 82 °C → VERT puis souffler · **Radio** OPALE → 316 |
| 10 | KN-4357 | oui | non | **Fils** (blanc, bleu, vert, bleu, blanc) → fil 2 · **Clavier** (FUSÉE, GUITARE, LAMPE, JOKER) → 8285 · **Surchauffe** 53 °C → ROUGE · **Radio** AMBRE → 185 · **Stabilisateur** (rythme 0, 250, 750 ms) |
| 11 | IR-5636 | non | oui | **Fils** (bleu, vert, blanc, bleu, vert) → fil 2 · **Viseur** ALPHA → vers le haut, vers la droite · **Nuancier** niveau 3 → pastille bleu · **Radio** ONYX → 947 · **Stabilisateur** (rythme 0, 400, 600, 1000 ms) |
| 12 | LH-9910 | non | non | **Fils** (rouge, jaune, jaune, rouge, jaune) → fil 5 · **Détonateur** « EXPLOSER » → ROUGE · **Viseur** CHARLIE → vers la gauche, vers le haut · **Radio** SAPHIR → 472 · **Stabilisateur** (rythme 0, 500, 750, 1000 ms) |
