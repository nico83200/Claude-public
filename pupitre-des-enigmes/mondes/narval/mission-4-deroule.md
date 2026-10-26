# Mission 4 : le déroulé minute par minute

> **Le Pupitre des Énigmes** · Monde : Sous-marin Narval · complément du
> [scénario](mission-4-dernier-souffle.md). Une **partie type** de 60 minutes :
> actions, cartes posées, écran, son, lumière et objectifs.
>
> **Repères sonores** : 🔊 20 désigne une réplique du dossier SD `40` (voix du
> Narval) ; `41/…` les bruitages du Narval ; `90/…` et `91/…` ceux du pupitre.
> Les captures de l'écran sont dans `firmware/docs/ecrans/` (fichiers `m4-…`).

---

## 1. Ce qui change par rapport aux missions 1 à 3

| Situation | Effet |
|---|---|
| **Pas d'enquête** | Ni coupable ni accusation : le but est de **sortir**. |
| **Pas de rôles** | Tous matelots. Après le choix de la mission, l'introduction démarre aussitôt. |
| **Le plateau** | La façade de la mission renomme les lecteurs : **MAIN GAUCHE**, **COMPARTIMENT** (centre), **MAIN DROITE**. L'écran les affiche sous ces noms. |
| **COMPARTIMENT** | Un lieu posé au centre y reste : c'est là où se trouve l'équipe. Un lieu posé sur une main : 🔊 15. |
| **Les mains** | Elles reçoivent des **objets**. Un objet seul est **utilisé dans le compartiment** ; deux objets ensemble se **combinent** (les deux clés). Un objet posé au centre : 🔊 16. |
| **La dynamo** | Le **gros bouton**. La lumière baisse en continu (de 100 % à 0 en **80 s**, puis en **160 s** une fois le courant rétabli) ; chaque appui rend **15 %**. L'écran s'assombrit sous 40 % ; à 0, **noir complet** : l'écran affiche « Noir complet · Actionnez la dynamo ! » et toute carte posée ne donne que 🔊 17. |
| **Chrono = oxygène** | « O₂ 60:00 » dans l'en-tête, avec une barre qui se vide. Trois bonus rendent de l'air (+6, +5, +4 min). |
| **Les objectifs** | Un appui sur la molette (hors épreuve) ouvre le **tableau de bord** : objectifs découverts, temps, lumière. La ligne du bas de l'écran les montre aussi. |
| **Gestes à plusieurs** | Porte, clés, poste avant, écoutille, coque : les **4 boutons tenus ensemble**, et quelqu'un sur la dynamo. |

---

## 2. Les étapes

| Drapeau | Condition | Débloque |
|---|---|---|
| `casier` | Casier de Rasmus (O1) : **379** | E2 : multimètre, carnet de l'électricien |
| `J_porte` | Coursive (L2) : **4 boutons, 3 s** | Tous les compartiments ; E3 |
| `releve` | Multimètre (O2) au local des batteries | Les tensions à l'écran |
| `pont` | Câble (O3) au local : **banc E** (bouton bleu) | Le raccordement |
| `J_raccord` | Raccordement : **ABF** | La répartition |
| `J_courant` | Répartition : **3-2-2-1-1** | Console, radio, épurateur ; la dynamo dure 2 fois plus |
| `vannes` | Manivelle (O4) en salle des machines → vannes **2586** | La pompe |
| `J_hyd` | Pompe : rythme long-court-court × 3 sur le **bouton jaune** | Le sas a sa pression |
| `J_console` | Poste de commande (avec courant) : **ABYSSE** | Les deux clés |
| `coffre` | Coffre (O7) : **2479** | E10 : clé de la commandante (O6) |
| `J_sec` | Clés O5 + O6 en main, poste de commande au centre : **4 boutons, 2 s** | La sécurité du sas est levée |
| `J_contact` | Salle radio (avec courant), 🎧 : **736** | L'Orque viendra : « seize bars » |
| `J_verrous` | Clé à cliquet (O11) au sas, `J_hyd` et `J_sec` : **bleu, rouge, vert, jaune** | L'Orque accoste (si `J_contact`) |
| `egal` | Égalisation : **16** | L'écoutille : **4 boutons, 4 s** → victoire |

**Bonus** : `armoire` (O8, **9742**, E5) puis `chandelle` (O9, partout sauf au
local des batteries, **+6 min**) · `chaux` (O10 en salle des machines avec le
courant, **+5 min**) · `J_cmdt` (O12 au poste avant, 4 boutons 4 s, **+4 min**,
E13).

L'ordre est libre une fois la porte ouverte : l'équipe peut, par exemple, ouvrir
le coffre avant d'avoir le courant, ou lever la sécurité avant l'hydraulique.

### Les objectifs du tableau de bord

| Objectif | Apparaît | Coché |
|---|---|---|
| Ouvrir la porte étanche | dès le départ | `J_porte` |
| Rétablir le courant | porte ouverte | `J_courant` |
| Pressuriser l'hydraulique | salle des machines ou sas visités | `J_hyd` (ou verrous libérés) |
| Lever la sécurité du sas | poste de commande ou sas visités | `J_sec` |
| Contacter l'Orque | porte ouverte | `J_contact` |
| Libérer les verrous | sas visité | `J_verrous` |
| Ouvrir l'écoutille | verrous libres et contact établi | — |
| Bonus : la commandante | poste avant visité | `J_cmdt` |

### Filets de sécurité (« AIDE ? » clignote)

| Minute | Ce qui manque |
|---|---|
| 6 | le casier |
| 12 | la porte |
| 25 | le courant |
| 32 | l'hydraulique |
| 38 | la sécurité du sas |
| 44 | le contact avec l'Orque |
| 50 | les verrous |

---

## 3. Les écrans propres à cette mission

Thème : fond acier bleu nuit, titres ambre, alertes rouge vif.

| Écran | Capture | Ce qu'on y voit |
|---|---|---|
| Compartiment | `m4-01-poste` | Carte du récit, bandeau « Ouvrez l'enveloppe », objectifs en pastilles, la barre MAIN GAUCHE · COMPARTIMENT · MAIN DROITE |
| Geste à plusieurs | `m4-02-porte` | La consigne en grand, le compte à rebours circulaire |
| Relevé des bancs | `m4-03-releve` | Les huit tensions, en texte |
| Le pontage | `m4-04-pontage` | Quatre tuiles de couleur : C, E, H, ne pas ponter |
| Noir complet | `m4-05-noir`, `m4-12-noir` | Écran noir, « Actionnez la dynamo ! » |
| Répartition | `m4-06-energie` | 5 circuits, 9 unités |
| La pompe | `m4-07-pompe` | « Écoutez le rythme… », puis « À vous, sur le bouton jaune ! », le voyant jaune |
| Téléphone sous-marin | `m4-08-radio` | 3 chiffres ; les coups passent en boucle au casque |
| Les verrous | `m4-09-verrous` | Le rappel des positions, les étapes franchies (●●○○) |
| L'écoutille | `m4-10-ecoutille` | 4 boutons, 4 secondes |
| Fin | `m4-11-victoire`, `m4-13-asphyxie` | Rang, temps restant, conseils payants |

L'en-tête porte à droite **O₂ 59:57** (ambre, rouge sous 20 %) et le **témoin de
lumière** (ampoule et jauge, rouge sous 30 %). Sous 40 % de lumière, tout l'écran
s'assombrit par paliers.

---

## 4. Partie type (60 min)

### Lancement
| Action | Écran | Son |
|---|---|---|
| Carte Monde sur un lecteur | Menu du Narval | `41/001` jingle, 🔊 13 |
| Appui sur la molette (mission 4) | Le titre | 🔊 9 (pitch), 🔊 14 (le plateau, la dynamo) |
| | O₂ 60:00, la lumière commence à baisser | `41/004` alarme, 🔊 10 (système), ambiance `41/002` |

### 60:00 – 50:00 : le poste d'équipage et la porte
| Minute | Action | Résultat |
|---|---|---|
| 59 | **Poste d'équipage** au centre | 🔊 20, **E1** : casier (O1), photo, mot de Rasmus, tableau des quarts, plaque de la dynamo |
| 57 | **Casier** en main | 🔊 70, code : **3-7-9**. Erreur 🔊 71 (sans pénalité). Ouvert : `41/008`, 🔊 72, **E2** |
| 55 | **Coursive** au centre | 🔊 22 : 4 boutons, 3 s. Raté : 🔊 23. Réussi : `41/007`, 🔊 24, **E3** (clé à cliquet, plan, rapport d'avarie) |
| 53 (7ᵉ min) | ⛰️ **Le Narval glisse** | `41/006`, 🔊 120 : 15 s sans rien toucher, **dynamo comprise**. 🔊 121, ou 🔊 122 et −3 min |

> Avant la porte, tout autre compartiment répond 🔊 25.

### 50:00 – 38:00 : le courant
| Minute | Action | Résultat |
|---|---|---|
| 50 | **Infirmerie** | 🔊 27, **E4** : clé du second (O5), armoire (O8), fiche de soins |
| 49 | **Armoire** en main | 🔊 100, **9742** → `41/008`, 🔊 102, **E5** : la chandelle (O9) |
| 48 | **Chandelle** en main (hors local des batteries) | `41/022`, 🔊 104, **+6 min**. Au local : `41/004`, 🔊 105, rien |
| 47 | **Local des batteries** | 🔊 29, **E6** : manivelle (O4), tableau des bancs, manuel |
| 46 | **Multimètre** en main | 🔊 75, le relevé à l'écran |
| 45 | **Salle des machines** | 🔊 32, **E7** : câble (O3), chaux sodée (O10), pied-de-biche (O12), schéma hydraulique |
| 46 (14ᵉ min) | ⚡ **Court-circuit** | `41/011`, 🔊 123 : 5 couleurs à rejouer. 🔊 124, ou 🔊 125 et **noir complet** |
| 43 | **Câble** en main, au local | 🔊 77 : C, **E**, H, ne pas ponter. Erreur : `41/011`, 🔊 78, −1 min. Juste : 🔊 79 |
| 42 | Raccordement | 🔊 80 : **ABF**. Erreur : 🔊 81, −1 min |
| 41 | Répartition | 🔊 82 : **3-2-2-1-1**, bouton vert. Surcharge : `41/011`, 🔊 83, −2 min, **noir complet**. Juste : `41/010`, 🔊 84, ambiance `41/003` |

> Abandonner (retirer le compartiment) ne coûte rien : reposer le local des
> batteries reprend là où l'équipe s'était arrêtée.

### 38:00 – 25:00 : l'hydraulique, la console, le coffre
| Minute | Action | Résultat |
|---|---|---|
| 38 (22ᵉ min) | 📞 **La commandante** | `41/021`, 🔊 126, 🎧 **127** (une seule fois) : les deux clés, le coffre, « il manque une information » |
| 37 | **Manivelle** en main, salle des machines | 🔊 86 (la pompe tourne à vide), puis 🔊 33 : vannes **2586**. Erreur : 🔊 34, −1 min |
| 35 | Vannes ouvertes | 🔊 35, puis 🔊 87 : rythme long-court-court × 3, **bouton jaune**. Raté : 🔊 88. Réussi : `41/013`, 🔊 89 |
| 34 | **Chaux sodée** en main, salle des machines | 🔊 108, **+5 min** (sans courant : 🔊 107) |
| 33 | **Poste de commande** | 🔊 37, **E8** (carnet de la commandante) ; avec le courant, 🔊 39 : **ABYSSE**. ABYSTE : 🔊 40, −1 min |
| 31 | **Carré des officiers** | 🔊 51, **E9** : coffre (O7), carte marine, cahier du second |
| 30 (30ᵉ min) | 🛢️ **Fuite d'huile** (si l'hydraulique est faite et les verrous pas encore) | `41/005`, 🔊 128 : molette à zéro, 15 s. 🔊 129, ou 🔊 130 : l'hydraulique retombe (on réamorce la pompe ; les vannes restent ouvertes) |
| 28 | **Coffre** en main | 🔊 96, **2479** → `41/008`, 🔊 98, **E10** : clé de la commandante (O6), lettre |
| 26 | **Les deux clés** en main, poste de commande au centre | 🔊 93 : 4 boutons, 2 s. Raté : 🔊 94. Réussi : 🔊 95. Ailleurs : 🔊 92 |

### 25:00 – 10:00 : l'Orque, les verrous, la sortie
| Minute | Action | Résultat |
|---|---|---|
| 24 | **Salle radio** | 🔊 44, **E11** (grille, carnet des signaux) ; avec le courant, 🔊 46 : 🎧 les coups en boucle (`41/020`), réponse **736**. Erreur : 🔊 47, −1 min. Juste : `41/014`, 🔊 48, 🔊 49 (« seize bars ») |
| 22 (38ᵉ min) | 😮‍💨 **Le CO₂ monte** | `41/024`, 🔊 131 : rythme lent sur le **bouton vert**. 🔊 132 et +2 min, ou 🔊 133 et −1 min |
| 20 | **Poste avant** | 🔊 67 |
| 19 | **Pied-de-biche** en main | 🔊 68 : 4 boutons, 4 s. Raté : 🔊 69. Réussi : `41/007`, 🔊 115, **E13**, **+4 min** |
| 17 | **Sas** | 🔊 53, **E12** (procédure), puis l'état : 🔊 54 à 57 |
| 16 | **Clé à cliquet** en main, au sas | 🔊 58 : **bleu, rouge, vert, jaune**. Erreur : 🔊 59, −1 min. Réussi : `41/015`, 🔊 60 |
| 15 | L'Orque accoste | `41/019`, 🔊 62 : égalisation **16**. Erreur : 🔊 63, −1 min. Juste : 🔊 64 |
| 14 | L'écoutille | 🔊 65 : 4 boutons, 4 s. Raté : 🔊 66. Réussi : `41/026`, `41/017`, 🔊 150, 151 (ou 152), 153, puis le rang (🔊 154 à 156). **Victoire** |

> Une équipe plus lente rencontre encore **la coque qui craque** (46ᵉ minute :
> 4 boutons 3 s, −3 min si raté) et le système qui annonce l'oxygène restant à
> 15, 10, 5 et 1 minute (🔊 137 à 140).

### Si l'oxygène s'épuise
À 0:00 : `41/018`, 🔊 157, puis la solution 🔊 158. L'écran affiche « Mission
échouée ».

---

## 5. Les LED

| Moment | Anneau | LED des zones |
|---|---|---|
| Partie | **Blanc chaud** : la jauge de lumière, qui se vide quand la dynamo faiblit ; elle **vacille** sous 30 % | Blanc faible quand une carte est posée |
| Noir complet | Éteint | — |
| Imprévu | Tournoiement rouge (alerte) | — |
| Court-circuit | Les couleurs des disjoncteurs, une à une | — |
| Réussite / échec d'une épreuve | Vert / rouge, un instant | — |
| Victoire | Arc-en-ciel | — |
