# Mission 1 : le déroulé minute par minute

> Complément du [scénario](mission-1-disparition-eclipse.md). Ce document décrit
> **une partie type** : ce que font les joueurs, les cartes posées, ce qu'affiche
> l'écran, ce qu'on entend et les étapes à franchir.
> Version de la console à **3 lecteurs RFID** : QUI, OÙ et QUOI.
> Les numéros 🔊 renvoient aux répliques de [`audio/mission-1.json`](audio/mission-1.json)
> (dossier `01`) et aux bruitages de [`audio/sfx.json`](audio/sfx.json) (dossier `99`).

---

## 1. Les règles de pose (3 lecteurs)

```
      ┌─────────┐        ┌─────────┐        ┌─────────┐
      │ 👤 QUI  │        │ 📍 OÙ   │        │ 🔍 QUOI │
      └─────────┘        └─────────┘        └─────────┘
```

| Je pose… | …sur | Effet |
|---|---|---|
| une carte **Lieu** | OÙ | L'équipe **se rend** dans ce lieu : description, enveloppe éventuelle, ambiance sonore tant que la carte reste posée |
| une carte **Personnage** | QUI | **Interrogatoire** : sa déclaration |
| un **Personnage** sur QUI et un **Objet** sur QUOI | | Sa **réaction** à l'objet |
| un **Objet** seul | QUOI | Rappel de ce qu'est l'objet |
| une carte **Rôle** | un lecteur libre | Le **pouvoir** du rôle s'applique aux cartes posées sur les autres lecteurs |
| la carte **Papy Marcel** | n'importe quel lecteur | Un **indice** |
| 3 cartes, puis appui sur le **gros bouton** | QUI + OÙ + QUOI | **Accusation** |

**Une carte mal placée** (un objet sur OÙ, par exemple) déclenche le buzzer et
le message : *« Cette carte se pose sur la zone QUOI. »*

**Retirer puis reposer** la même carte donne le rappel court, pas le texte
complet.

---

## 2. Les étapes à franchir

```
                 ┌──────────────────────┐
                 │ J1  ÉCURIE fouillée  │  (E1 : carnet chiffré, fer ovale)
                 └──────────┬───────────┘
          ┌─────────────────┼──────────────────────┐
          ▼                 ▼                      ▼
 ┌─────────────────┐ ┌──────────────────┐ ┌───────────────────┐
 │ J2 CARNET lu    │ │ J3 BUREAU ouvert │ │ J4 SENTIER réussi │
 │ (clé 6, question│ │ (code 3715, E3)  │ │ (jaune, bleue,    │
 │  sur 3 boutons) │ │                  │ │  verte, E8)       │
 └────────┬────────┘ └────────┬─────────┘ └─────────┬─────────┘
          │                   │                     ▼
          │                   │           ┌───────────────────┐
          │                   │           │ J5 GRANGE trouvée │ (E4)
          │                   │           └─────────┬─────────┘
          └───────────────────┴──────────┬──────────┘
                                         ▼
                              ┌──────────────────────┐
                              │ J6 ACCUSATION juste  │
                              └──────────────────────┘
```

| Jalon | Condition | Débloque |
|---|---|---|
| **J1** Écurie | Carte Écurie posée sur OÙ | Le Sentier (avant J1 : *« Vous ne savez pas encore par où chercher. »*) |
| **J2** Carnet | Carnet sur QUOI, puis bonne réponse (bouton bleu) à la question 🔊 112 | Les réactions « carnet décodé » de tous les personnages |
| **J3** Bureau | Code `3715` | L'enveloppe E3, la question « caméra » du gendarme |
| **J4** Sentier | 3 bonnes balises | La Vieille Grange |
| **J5** Grange | Carte Vieille Grange sur OÙ | L'enveloppe E4 |
| **J6** Accusation | Hélène + Vieille Grange + Contrat d'assurance | La victoire |

**Optionnels** (indices et fausses pistes) : Sellerie (E2), Manège (E5),
Carrière (E6), Pré du Bas (E7), Forge, alibis du Gendarme, expertises du
Comptable.

### Filets de sécurité (aide automatique)
Si un jalon n'est pas franchi à temps, **l'icône de Papy Marcel clignote** sur
l'écran. C'est une proposition : l'équipe reste libre de poser sa carte ou non.

| Si à… | …ce jalon n'est pas franchi | Papy Marcel propose |
|---|---|---|
| 8:06 | J1 Écurie | « Une enquête, ça commence sur la scène du crime. » |
| 8:25 | J3 Bureau | l'indice du code |
| 8:30 | J2 Carnet | l'indice du carnet |
| 8:45 | J4 Sentier | l'indice du sentier |
| 8:52 | J6 pas tentée | 🔊 nouvelle réplique : *« Il est temps de vous décider. Posez vos trois cartes. »* |

---

## 3. Les écrans

Écran de 480 × 320 en paysage. Les illustrations sont des images 16 bits
stockées sur la carte SD de l'écran.

### Écran A : Titre
```
┌────────────────────────────────────────────────┐
│                                                │
│          LES MYSTÈRES DU HARAS                 │
│             DES TROIS FERS                     │
│                  ~ ∩ ~                         │
│   Mission 1 · La Disparition d'Éclipse         │
│                                                │
│     < tournez la molette pour choisir >        │
│        appuyez sur la molette : JOUER          │
└────────────────────────────────────────────────┘
```

### Écran B : Les rôles
```
┌────────────────────────────────────────────────┐
│  POSEZ VOS CARTES RÔLE SUR UN LECTEUR          │
├────────────────────────────────────────────────┤
│   [✓] Maréchal-ferrant   [✓] Vétérinaire       │
│   [ ] Journaliste        [✓] Expert-comptable  │
│   [ ] Parieur            [ ] Ancien gendarme   │
│                                                │
│              3 / 6 rôles présents              │
└────────────────────────────────────────────────┘
```

### Écran C : Écran principal (en attente)
```
┌────────────────────────────────────────────────┐
│ 8:14          Indices gratuits : 3     ✗ 0/3   │
├────────────────────────────────────────────────┤
│                                                │
│  Dernier message :                             │
│  « Sultan et Comète se font face. Ces          │
│    deux-là sont inséparables. »   — Lucas      │
│                                                │
│  Écurie ✓   Carnet ·   Bureau ·   Sentier ·    │
├────────────────────────────────────────────────┤
│ QUI : —        OÙ : Écurie        QUOI : —     │
└────────────────────────────────────────────────┘
```
- **En haut** : l'heure du haras (de 8:00 à 9:00), les indices gratuits restants
  et les erreurs d'accusation.
- **Au milieu** : le dernier texte lu, pour ceux qui n'ont pas suivi.
- **En bas** : les cartes posées sur chaque lecteur.

### Écran D : Lieu
```
┌────────────────────────────────────────────────┐
│ 8:04                                 L'ÉCURIE  │
├────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────┐  │
│  │      [illustration : box 7 ouvert]       │  │
│  └──────────────────────────────────────────┘  │
│  Le box sept est grand ouvert. La serrure      │
│  n'a pas été forcée…                 (défile)  │
│                                                │
│  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓    │
│  ┃     ✉  OUVREZ L'ENVELOPPE  E1          ┃    │
│  ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛    │
└────────────────────────────────────────────────┘
```
Le texte défile au rythme de la voix. Le bandeau « enveloppe » clignote.

### Écran E : Interrogatoire
```
┌────────────────────────────────────────────────┐
│ 8:09                       INTERROGATOIRE      │
├──────────────┬─────────────────────────────────┤
│              │  LUCAS BERNIER                  │
│  [portrait]  │  Palefrenier, 24 ans            │
│              │                                 │
│              │  « J'ai fait ma ronde à 22 h,   │
│              │    tout était fermé… »          │
├──────────────┴─────────────────────────────────┤
│ QUI : Lucas       OÙ : Écurie      QUOI : —    │
└────────────────────────────────────────────────┘
```

### Écran F : Réaction à un objet
```
┌────────────────────────────────────────────────┐
│ 8:33                              CONFRONTATION│
├──────────────┬─────────────────────────────────┤
│  [portrait   │  Vous montrez à LUCAS :         │
│   Lucas]     │     [vignette : bottes]         │
│              │                                 │
│              │  « Ce sont les bottes de        │
│              │    Madame. Mais elle ne va      │
│              │    jamais dans la pinède ! »    │
└──────────────┴─────────────────────────────────┘
```

### Écran G : Code du bureau
```
┌────────────────────────────────────────────────┐
│ 8:17                     PORTE DU BUREAU 🔒    │
├────────────────────────────────────────────────┤
│                                                │
│            ┌───┐ ┌───┐ ┌───┐ ┌───┐             │
│            │ 3 │ │ 7 │ │ 0 │ │ _ │             │
│            └───┘ └───┘ └─▲─┘ └───┘             │
│                                                │
│     Tourner : changer · Appuyer : valider      │
│     Bouton rouge : effacer                     │
│                                  Essais : 1    │
└────────────────────────────────────────────────┘
```

### Écran H : Question du carnet
```
┌────────────────────────────────────────────────┐
│ 8:24                    CARNET DE SANTÉ        │
├────────────────────────────────────────────────┤
│  Vous avez déchiffré le carnet ? Que dit-il ?  │
│                                                │
│   (●) ROUGE  : une simple fatigue              │
│   (●) BLEU   : une tendinite, 6 mois d'arrêt   │
│   (●) JAUNE  : un vaccin à faire               │
│                                                │
│               Bouton vert : pas encore         │
└────────────────────────────────────────────────┘
```

### Écran I : Croisement du sentier
```
┌────────────────────────────────────────────────┐
│ 8:36     SENTIER DES BOIS · CROISEMENT 1 / 3   │
├────────────────────────────────────────────────┤
│        [illustration : chemin qui se divise]   │
│                                                │
│   ( ROUGE )   ( BLEUE )   ( JAUNE )   ( VERTE )│
│                                                │
│      Carnet du sentier : PAGE 1                │
│      Quelle piste suivez-vous ?                │
└────────────────────────────────────────────────┘
```
Le pouvoir du Maréchal barre une balise : `( BLEUE )` devient `(  ╳  )`.

### Écran J : Imprévu
```
┌────────────────────────────────────────────────┐
│ !!!!!!!!!!!!!!!!!!  IMPRÉVU  !!!!!!!!!!!!!!!!!!│
├────────────────────────────────────────────────┤
│                                                │
│      RAFALE S'EST ÉCHAPPÉ DANS LA COUR !       │
│                                                │
│      Les 4 BOUTONS en même temps, 3 s !        │
│                                                │
│        ██████████████░░░░░░  7 s               │
└────────────────────────────────────────────────┘
```
Le fond clignote en rouge et l'anneau de LED tourne en rouge.

### Écran K : Papy Marcel
```
┌────────────────────────────────────────────────┐
│ 8:26                   UN COUP DE MAIN ?       │
├──────────────┬─────────────────────────────────┤
│  [portrait   │  « Lucas vous a donné la règle  │
│   Papy       │    du code. Et l'ordre de       │
│   Marcel]    │    passage, qui c'est qui       │
│              │    l'a ? »                      │
│              │  Indice 1/3 · gratuit (reste 2) │
│              │  Reposer la carte : indice 2    │
└──────────────┴─────────────────────────────────┘
```

### Écran L : Accusation
```
┌────────────────────────────────────────────────┐
│ 8:51                      ⚖  ACCUSATION        │
├────────────────────────────────────────────────┤
│                                                │
│     QUI              OÙ              POURQUOI  │
│  ┌─────────┐    ┌───────────┐    ┌──────────┐  │
│  │ Hélène  │    │  Vieille  │    │ Contrat  │  │
│  │ Vasseur │    │  Grange   │    │d'assuranc│  │
│  └─────────┘    └───────────┘    └──────────┘  │
│                                                │
│   VERT : confirmer          ROUGE : annuler    │
└────────────────────────────────────────────────┘
```

### Écran M : Résultat et score
```
┌────────────────────────────────────────────────┐
│               ★  AFFAIRE RÉSOLUE  ★            │
├────────────────────────────────────────────────┤
│   Temps restant ............. 7 min 05 s       │
│   Indices payants ........... 1                │
│   Erreurs d'accusation ...... 0                │
│                                                │
│              ✿  FLOT ROUGE  ✿                  │
│                                                │
│   Meilleur score : Équipe « Les Cracks » 14:30 │
└────────────────────────────────────────────────┘
```

---

## 4. Le déroulé d'une partie type

Les heures sont celles du **chrono du jeu**, qui va de 8:00 à 9:00. Les actions
des joueurs correspondent à un rythme **moyen** : un groupe rapide aura de
l'avance, un groupe lent sera rattrapé par les filets de sécurité.

### Avant la partie (5 min)
| Action | Détail |
|---|---|
| Mise en place | Plans et cartes Lieu et Personnage au centre, enveloppes E1 à E8 empilées, cartes Objet rangées dans leurs enveloppes |
| Distribution | Les 6 rôles (voir le scénario, chapitre 4), chacun avec son document secret **face cachée** |
| Console | Allumage : **écran A**, 🔊 99/006 ambiance d'écurie, LED blanches qui respirent |

### Introduction (avant le chrono)
| Moment | Joueurs | Cartes | Écran | Son | LED |
|---|---|---|---|---|---|
| — | Un joueur appuie sur la molette | — | A, puis **B** | 🔊 001, puis 002 | Blanc |
| — | Chacun pose sa carte Rôle sur un lecteur, puis la reprend | Rôles | **B** : les cases se cochent | 🔊 003 à 008 et 99/012 | Flash vert sur le lecteur utilisé |
| — | 6/6 rôles : la lettre de Camille s'affiche | — | Texte défilant | 🔊 009 (le pitch, environ 50 s) | Bleu nuit |
| **8:00** | Le chrono démarre | — | **C** | 🔊 010, ambiance d'écurie | **12 LED vertes** : chacune vaut 5 minutes |

### 8:00 – 8:10 : la scène du crime (J1)
| Heure | Joueurs | QUI | OÙ | QUOI | Écran | Son | Étape |
|---|---|---|---|---|---|---|---|
| 8:01 | Découvrent les plans et lisent leurs documents secrets en silence | — | — | — | C | ambiance | |
| 8:02 | Posent l'Écurie | — | **Écurie** | — | **D** + « ENVELOPPE E1 » | 🔊 020 | ✅ **J1** |
| 8:03 | Ouvrent E1 : carnet chiffré, photo du box, carte O1 | — | Écurie | — | D | — | |
| 8:04 | Le Maréchal reconnaît le fer ovale d'Éclipse dans son catalogue | | | | | | |
| 8:05 | La Vétérinaire lit sa note : *« décalez du nombre de clous… »* et demande au Maréchal : **6** | | | | | | |
| 8:06 | L'équipe se répartit : 2 joueurs déchiffrent, les autres interrogent | | | | | | |
| 8:06 | Interrogent Camille | **Camille** | Écurie | — | **E** | 🔊 150 | Indice « Sultan côté sud » |
| 8:08 | **IMPRÉVU Rafale** : les 4 boutons en même temps | | | | **J** | 🔊 99/008, 080 | LED rouges qui tournent |
| 8:08 | Réussi : *« Ouf ! »* (raté : 2 min de pénalité) | | | | C | 🔊 081 / 082 | Retour vert |
| 8:09 | Interrogent Lucas | **Lucas** | Écurie | — | E | 🔊 135, 136 | **Règle du code**, indice « Sultan et Comète » |

### 8:10 – 8:20 : le code du bureau (J3)
| Heure | Joueurs | QUI | OÙ | QUOI | Écran | Son | Étape |
|---|---|---|---|---|---|---|---|
| 8:10 | Interrogent Hélène, qui joue la victime | **Hélène** | Écurie | — | E | 🔊 120 | |
| 8:11 | Posent le Bureau : il est verrouillé | — | **Bureau** | — | **G** | 🔊 024 | |
| 8:12 | Le Journaliste lit son article : *« Rafale et Éclipse se font face »* | | | | | | |
| 8:13 | Le Parieur lit l'ordre de passage : Rafale, Éclipse, Comète, Sultan | | | | | | |
| 8:14 | Grille sur le plan de l'écurie : Rafale au 3, Sultan au 5, Comète au 1 | | | | | | |
| 8:15 | Premier essai, par exemple `3751` | — | Bureau | — | G : « Code incorrect » | 🔊 025, 99/004 | |
| 8:17 | Ils corrigent : **`3715`** | — | Bureau | — | D Bureau + « E3 » | 🔊 99/003, 027 | ✅ **J3** |
| 8:18 | Ouvrent E3 : contrat, clé n° 7, ticket caméra 03:12 → 04:05 | | | | | | |
| 8:18 | **IMPRÉVU Comète** : rythme du galop, à reproduire 4 fois sur le gros bouton | | | | J | 🔊 083, 99/007 ×3 au tempo | |
| 8:19 | Réussi | | | | C | 🔊 084 | |
| 8:20 | Le Gendarme note : *« Le code, seuls Hélène et Lucas le connaissent. »* | | | | | | |

> Si l'imprévu tombe pendant la saisie du code, **il est repoussé** jusqu'à la
> fin de la saisie. Aucun imprévu n'interrompt un code, un croisement ou une
> accusation.

### 8:20 – 8:30 : le carnet et les lieux secondaires (J2)
L'équipe se sépare en petits groupes, et la console reçoit plusieurs cartes à
la suite.

| Heure | Joueurs | QUI | OÙ | QUOI | Écran | Son | Étape |
|---|---|---|---|---|---|---|---|
| 8:21 | Les déchiffreurs finissent : *« TENDINITE… SIX MOIS… PAS MÊME L'ASSURANCE »* | | | | | | |
| 8:22 | Posent le carnet | — | — | **Carnet** | **H** | 🔊 112 | |
| 8:22 | Bouton **bleu** | | | | C | 🔊 113, 99/014 | ✅ **J2** |
| 8:23 | Montrent le carnet à Hélène | **Hélène** | — | **Carnet** | **F** | 🔊 122 | Elle ment : « une petite inflammation » |
| 8:24 | Le montrent à Delorme | **Delorme** | — | **Carnet** | F | 🔊 198 | « 50 000 € au mieux » |
| 8:25 | Sellerie | — | **Sellerie** | — | D + « E2 » | 🔊 022 | Bottes « H.V. », couverture et seau manquants |
| 8:26 | Le Comptable expertise le contrat | **Rôle Comptable** | — | **Contrat** | Encadré « Expertise » | 🔊 108 | 450 000 € contre 50 000 € |
| 8:27 | Carrière (fausse piste Victoire) | — | **Carrière** | — | D + « E6 » | 🔊 031 | |
| 8:28 | **IMPRÉVU Hélène** : documents retournés face cachée | | | | J | 🔊 99/013, 086 | LED orange |
| 8:28 | Hélène interroge un rôle tiré au sort ; un joueur répond à voix haute | | | | Portrait d'Hélène | 🔊 087 à 092 | |
| 8:29 | Hélène repart : *« Ne perdez pas votre temps dans la pinède. »* | | | | C | 🔊 093 | 🧠 Indice glissé |

### 8:30 – 8:40 : le sentier des bois (J4)
| Heure | Joueurs | QUI | OÙ | QUOI | Écran | Son | Étape |
|---|---|---|---|---|---|---|---|
| 8:30 | *« La pinède… elle veut nous en éloigner ! »* | — | **Sentier** | — | D + « E8 » | 🔊 036, 99/005 | |
| 8:31 | Ouvrent E8 : le carnet du sentier | | | | **I** (1/3) | 🔊 037 | LED vert sapin |
| 8:32 | Comparent les traces au fer ovale (6 clous) : **jaune** | | | | I (2/3) | 🔊 041, 038 | |
| 8:34 | Hésitent entre bleue et jaune, choisissent **jaune** : raté | | | | C | 🔊 040, 99/004 | −2 min |
| 8:35 | Le Maréchal utilise son pouvoir : une balise est barrée | **Rôle Maréchal** | Sentier | — | I avec ╳ | 🔊 048 | |
| 8:36 | **Bleue** : fer ovale **et** semelle à chevrons | | | | I (3/3) | 🔊 041, 039 | |
| 8:37 | *« Ce sont les mêmes semelles que les bottes ! »* **Verte** | | | | Grange au loin | 🔊 042, 99/002 | LED blanches qui pulsent |
| 8:38 | | | | | « Lieu découvert » | 🔊 043 | ✅ **J4** |
| 8:39 | Posent la Vieille Grange | — | **Grange** | — | D + « E4 » | 🔊 045, 99/001 | ✅ **J5** |
| 8:40 | Ouvrent E4 : photo d'Éclipse saine et sauve, couverture, seau, lampe « BUREAU » | | | | | | |

### 8:40 – 8:50 : recouper les preuves
| Heure | Joueurs | QUI | OÙ | QUOI | Écran | Son | Étape |
|---|---|---|---|---|---|---|---|
| 8:40 | **IMPRÉVU Gendarmerie** : *« À quelle heure la caméra a-t-elle été coupée ? »* | | | | G (heure) | 🔊 99/009, 094, 095 | |
| 8:41 | Molette sur **0312** : bonus | | | | C | 🔊 098 | **+2 min** |
| 8:42 | Le Gendarme vérifie Lucas | **Lucas** | — | **Rôle Gendarme** | Encadré « Vérification » | 🔊 101 | Au téléphone : innocenté |
| 8:43 | Le Gendarme vérifie Hélène | **Hélène** | — | **Rôle Gendarme** | idem | 🔊 100 | « Aucun justificatif » |
| 8:44 | Montrent les bottes à Lucas | **Lucas** | — | **Bottes** | F | 🔊 138 | « Elle ne va jamais dans la pinède » |
| 8:45 | Montrent les bottes à Hélène | **Hélène** | — | **Bottes** | F | 🔊 124 | Mensonge : il pleuvait la nuit |
| 8:46 | Le Journaliste fait insister Delorme | **Delorme** | — | **Rôle Journaliste** | E | 🔊 118, 196 | « Des acheteurs discrets… à l'étranger » |
| 8:47 | Montrent le contrat à Camille | **Camille** | — | **Contrat** | F | 🔊 153 | « Juste après le diagnostic ! » |
| 8:48 | Débat final à voix haute. Les fausses pistes (Victoire, Antoine) sont écartées ou vérifiées | | | | | | |

### 8:50 – 8:55 : l'accusation (J6)
| Heure | Joueurs | QUI | OÙ | QUOI | Écran | Son | Étape |
|---|---|---|---|---|---|---|---|
| 8:50 | Le Parieur arme son Coup de poker | **Rôle Parieur** | — | — | « Coup de poker armé » | 🔊 115 | |
| 8:51 | Posent les 3 cartes et débattent | **Hélène** | **Grange** | **Contrat** | **L** | — | |
| 8:52 | **Gros bouton** | | | | L | 🔊 050 | Les 3 LED de zone en blanc |
| 8:52 | **Vert** pour confirmer | | | | « Voyons voir… » | 🔊 054, roulement | LED qui clignotent |
| 8:53 | Les zones passent au vert une par une : QUI ✓, OÙ ✓, POURQUOI ✓ | | | | « C'est juste ! » | 🔊 063, 99/010 | ✅ **J6** |

**Si l'accusation est fausse :** l'écran affiche « 2 éléments sur 3 sont justes »
(🔊 057, 058, −5 min). Avec le Coup de poker, la zone fausse s'allume en rouge
(🔊 059, 060 ou 061) et il n'y a pas de pénalité.

### Épilogue (environ 2 min, chrono arrêté)
| Écran | Son |
|---|---|
| Illustration : Hélène en larmes, Éclipse dans son box | 🔊 064 |
| Portrait d'Hélène | 🔊 129 (les aveux) |
| Illustration : Camille et Éclipse, six mois plus tard | 🔊 065 |
| **Écran M** : score et rang | 🔊 066 / 067 / 068 |
| Tableau des scores, saisie d'un nom d'équipe à la molette | 99/010 |

### Si le temps s'écoule
| Heure | Écran | Son | LED |
|---|---|---|---|
| 8:55 | Bandeau « DERNIÈRE LIGNE DROITE » | 🔊 078, 99/011 (musique de tension) | Rouges qui clignotent |
| 8:59 | Compte à rebours en grand | 🔊 079 | Clignotement rapide |
| 9:00 | « L'expert est arrivé » | 🔊 072, 070, 99/015 | Extinction une à une |
| — | La solution | 🔊 071 | Blanc |

---

## 5. Les LED en résumé

| Situation | Anneau (12 LED) | LED de zone (×3) |
|---|---|---|
| Partie en cours | **Chrono** : une LED verte s'éteint toutes les 5 min, la couleur vire à l'orange puis au rouge | Blanc faible quand une carte est posée |
| Carte acceptée | Flash vert | Flash vert sur la zone |
| Carte refusée ou mal placée | Flash rouge | Flash rouge sur la zone |
| Imprévu | Rotation rouge | Rouges |
| Sentier | Vert sapin | — |
| Accusation | Clignotement blanc | Vert une par une (juste), rouge (poker) |
| Victoire | Arc-en-ciel | Vertes |
| Échec | Extinction progressive | Éteintes |

---

## 6. Modifications induites dans les autres fichiers

À reporter dans le scénario et dans `audio/mission-1.json` :
- répliques 051 à 053 : remplacer par une seule phrase, *« Posez vos trois cartes,
  puis appuyez sur le gros bouton. »* ;
- répliques 116 et 117 : à réécrire pour les règles de pose ;
- nouvelles répliques :
  - *« Cette carte se pose sur la zone QUI / OÙ / QUOI. »* (3 variantes) ;
  - *« Vous ne savez pas encore par où chercher. »* (Sentier avant J1) ;
  - *« Il est temps de vous décider. »* (8:52) ;
  - la question de repli du gendarme : *« Quel est le numéro du box d'Éclipse ? »* ;
  - une description courte de chaque objet posé seul sur QUOI (10 répliques) ;
- câblage : 3 lecteurs RC522 (CS 13, 22, 15, RST non branchée) et 3 LED de zone
  à la suite de l'anneau.
