# Mission 5 : Banc d'essai, le déroulé

> **Le Pupitre des Énigmes** · Monde : L'Atelier du professeur Lumen. La mission de
> **test du pupitre** : 2 joueurs, 20 à 30 minutes, chaque organe essayé une fois.
> Le chrono compte 30 minutes. Il n'y a ni rôles, ni accusation, ni enveloppes (sauf E1,
> pour un pupitre sans tiroir).
>
> **Repères sonores** : 🔊 20 désigne une réplique du dossier SD `50` (la voix du
> professeur Lumen) ; `51/…` les bruitages de l'atelier.

---

## 1. Le principe

| Situation | Effet |
|---|---|
| **Une carte par banc** | 13 cartes Banc d'essai. Glissée dans la fente, la carte lance l'essai de son organe. Elle reste en place pendant l'essai. |
| **Le tableau de bord** | Les 10 voyants : 1 écran et clavier, 2 boutons, 3 molette, 4 gros bouton, 5 lecteurs, 6 forge, 7 enclume, 8 œil, 9 nuancier, 10 clé. Rouge faible : à essayer ; vert : validé. |
| **La lumière sous la boîte** | Ambre qui respire ; verte quand les 11 essais sont faits. |
| **Un essai raté** | Rien de perdu : on retire la carte du banc, on la repose, on recommence. |
| **Un banc déjà validé** | 🔊 12 : « Ce banc est déjà validé. » |
| **Les objectifs** | Un appui sur la molette affiche les 13 bancs, faits ou non. |
| **Un organe absent** | Le maître du jeu le valide avec **Débloquer** (panneau Wi-Fi), et le note sur la fiche de recette. |

## 2. Les bancs et leurs solutions

| Carte | Organe | Épreuve | Solution |
|---|---|---|---|
| L1 Poste de contrôle | Écran, haut-parleur, clavier, horloge | `code`, affiché sur l'horloge | **2580** (la plaque de la fiche) |
| L2 Banc des boutons | 4 boutons | `sequence` de 4, puis `ensemble` 3 s | Rejouer la suite ; les 4 boutons tenus |
| L3 Banc de la molette | Molette et appui | `molette_zero` (départ 20), puis `code` | Zéro ; puis **7** et un appui |
| L4 Banc du gros bouton | Gros bouton | `rythme` : 3 coups (0, 0,5 s, 1 s), 3 fois | Taper en mesure |
| L5 Tableau des voyants | 10 voyants | `voyants` : de 0000000000 à 1111111111 | Touches 1 à 9 et 0 |
| L6 La forge | BME280 | `capteur` souffle, 2 s | Souffler sur la grille |
| L7 L'enclume | QMC5883L | `capteur` aimant, 2 s | Un aimant sur la marque |
| L8 L'œil | APDS-9960 | 4 `choix` aux gestes, puis `capteur` éclaire 2 s | Haut, bas, gauche, droite ; une lampe |
| L9 Le nuancier | TCS34725 | Règles « couleur » | Rouge, bleu, vert, jaune ; une mauvaise pastille affiche « Le capteur voit : … » |
| L10 La serrure | Interrupteur à clé | `cle`, 20 s | Tourner la clé |
| L11 Banc des lecteurs | Fente et lecteur à plat | Combinaison O1 + O2 | Clé à molette dans la fente, burette sur le plateau |
| L12 Le casque | Casque | Réplique 🔊 60 au casque (20 s pour le prendre) | Code **3861** ; seulement quand les 11 essais sont faits |
| L13 Le coffre | Tiroir secret et sa LED | `code`, affiché sur l'horloge | **3861** : le tiroir sort (sinon, enveloppe E1) |
| O3 Certificat | Lecteur à plat | Posé après le coffre | **Victoire** |

## 3. Une partie type (25 minutes)

| Temps | Les joueurs | Le pupitre |
|---|---|---|
| 0:00 | Carte M5 dans la fente, 1 · Jouer | 🔊 9 et 14 : le professeur présente l'essai |
| 0:02 | — | Le chrono part (30:00) ; 🔊 10 ; ambiance `51/2` |
| 0:03 | Poste de contrôle : 2580 au clavier | L'horloge montre « 25-- » puis le chrono ; voyant 1 vert |
| 0:05 | Boutons : la suite, puis les 4 ensemble | Voyant 2 vert |
| 0:07 | Molette : zéro, puis 7 | Voyant 3 vert |
| 0:08 | Gros bouton : le rythme | Voyant 4 vert |
| 0:08 | **Imprévu : surtension** (minute 8) | 🔊 70, `51/6`, l'anneau en alerte, le socle rouge ; 10 s sans toucher |
| 0:10 | Voyants : les dix touches | Les 10 voyants s'allument en blanc, puis reprennent leur état |
| 0:12 | Forge, enclume | Voyants 6 et 7 verts |
| 0:15 | L'œil : 4 gestes et la lampe | Voyant 8 vert |
| 0:18 | Nuancier : les 4 pastilles | Voyant 9 vert |
| 0:19 | Serrure : la clé | Voyant 10 vert |
| 0:20 | Lecteurs : clé à molette + burette | Voyant 5 vert ; 🔊 57 : tout est vert, le socle passe au vert |
| 0:22 | Casque : un joueur écoute | 🔊 60 au casque : 3861 |
| 0:23 | Coffre : 3861 | `51/7`, le tiroir sort et s'éclaire, 🔊 64 |
| 0:24 | Certificat sur le lecteur à plat | `51/8`, 🔊 80, puis le rang ; l'écran demande de repousser le tiroir |

**Les rangs** : *Ingénieurs en chef* (10 minutes ou plus restantes, aucun conseil payant),
*Techniciens confirmés* (3 minutes ou plus), *Apprentis de l'atelier*.

**À 5 minutes de la fin** : 🔊 73. **Temps écoulé** : 🔊 84, puis la solution (🔊 85).

## 4. Le carnet du professeur (carte A5)

| Blocage | Conseils |
|---|---|
| Poste de contrôle pas fait | 🔊 93 (la plaque de la fiche), 🔊 94 (2580) |
| Des essais restent | 🔊 95 (la molette affiche la liste), 🔊 96 (la carte reste en place), 🔊 97 (le maître du jeu peut débloquer) |
| Le coffre | 🔊 98 (3861, puis le certificat) |

Les 3 premiers conseils sont gratuits, les suivants coûtent 2 minutes.
