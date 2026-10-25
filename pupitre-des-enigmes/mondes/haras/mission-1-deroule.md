# Mission 1 : le déroulé minute par minute

> **Le Pupitre des Énigmes** · Monde : Le Haras des Trois Fers · complément du
> [scénario](mission-1-disparition-eclipse.md). Ce document décrit **une partie
> type** : ce que font les joueurs, les cartes posées sur les trois zones, ce
> qu'affiche l'écran, ce qu'on entend (haut-parleur ou 🎧 casque) et les étapes
> franchies.
>
> **Repères sonores** : un numéro seul (🔊 120) désigne une réplique du Haras,
> dans le dossier SD `10`. Les autres sons sont notés avec leur dossier :
> `11/…` pour les bruitages du Haras, `90/…` pour les bruitages du pupitre,
> `91/…` pour la voix du pupitre.

---

## 1. Les règles de pose

```
      ┌─────────┐        ┌─────────┐        ┌─────────┐
      │ 👤 QUI  │        │ 📍 OÙ   │        │ 🔍 QUOI │
      └────•────┘        └────•────┘        └────•────┘        🎧 casque
```

| Je pose… | …sur | Effet |
|---|---|---|
| la carte **Monde** | n'importe quel lecteur, au démarrage | Le monde se charge |
| une carte **Lieu** | OÙ | L'équipe **s'y rend** : description, enveloppe éventuelle, ambiance sonore tant que la carte est posée |
| une carte **Personnage** | QUI | **Interrogatoire** : sa déclaration |
| un **Personnage** et un **Objet** | QUI + QUOI | Sa **réaction** à l'objet |
| un **Objet** seul | QUOI | Une courte description (ou la question, pour le carnet) |
| une carte **Rôle** | un lecteur libre | Son **pouvoir** s'applique aux cartes posées à côté |
| la carte **Papy Marcel** | n'importe quel lecteur | Un **indice** |
| 3 cartes, puis le **gros bouton** | QUI + OÙ + QUOI | **Accusation** |

- **Une carte mal placée** déclenche le buzzer (90/003) et le message
  91/007, 008 ou 009 : *« Cette carte se pose sur la zone… »*
- **Retirer puis reposer** la même carte donne un rappel court.

**🎧 Le casque.** Quand une réplique est destinée au casque :

1. Un carillon retentit (90/006), et la voix du pupitre annonce : *« Casque ! Un
   seul joueur le met, puis appuie sur la molette. »* (91/005)
2. L'écran affiche l'**écran L**, sans sous-titres.
3. Un appui sur la molette lance le message dans le casque.
4. À la fin : *« Message terminé. »* (91/006)

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
 │  à 3 boutons)   │ │                  │ │  verte, E8)       │
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
| **J1** Écurie | Carte Écurie posée sur OÙ | Le Sentier (avant J1 : 🔊 017) |
| **J2** Carnet | Carnet sur QUOI, puis bouton **bleu** | Les réactions « carnet décodé » |
| **J3** Bureau | Code `3715` | L'enveloppe E3, la question « caméra » de la gendarmerie |
| **J4** Sentier | 3 bonnes balises | La Vieille Grange |
| **J5** Grange | Carte Vieille Grange sur OÙ | L'enveloppe E4 |
| **J6** Accusation | Hélène + Vieille Grange + Contrat d'assurance | La victoire |

**Optionnels** : Sellerie (E2), Manège (E5), Carrière (E6), Pré du Bas (E7),
Forge, l'appel anonyme 🎧, les confidences 🎧, les alibis 🎧 et les expertises.

### Filets de sécurité
Si un jalon n'est pas franchi à temps, le portrait de Papy Marcel **clignote** à
l'écran. C'est une simple proposition d'aide.

| À… | …si ce jalon manque | Papy Marcel propose |
|---|---|---|
| 8:06 | J1 Écurie | L'indice de départ |
| 8:25 | J3 Bureau | L'indice du code |
| 8:30 | J2 Carnet | L'indice du carnet |
| 8:45 | J4 Sentier | L'indice du sentier |
| 8:52 | J6 pas tentée | 🔊 018 *« Il est temps de vous décider. »* |

---

## 3. Les écrans

Écran de 480 × 320 en paysage, au thème du Haras : fond vert sapin, textes
crème, titres en Playfair Display.

### Écran A : Accueil du pupitre (commun à tous les mondes)
```
┌────────────────────────────────────────────────┐
│                                                │
│            LE PUPITRE DES ÉNIGMES              │
│                    ✦ ? ✦                       │
│                                                │
│      Posez une carte Monde sur un lecteur      │
│                                                │
│     ( QUI )        ( OÙ )        ( QUOI )      │
│                                                │
└────────────────────────────────────────────────┘
```

### Écran B : Monde chargé
```
┌────────────────────────────────────────────────┐
│                     ∩ ∩ ∩                      │
│           LE HARAS DES TROIS FERS              │
│                                                │
│    Installez la façade du haras sur le         │
│    pupitre, puis appuyez sur la molette.       │
│                                                │
│    Mission 1 · La Disparition d'Éclipse  ◀ ▶   │
│    Tableau d'honneur · Reprendre une partie    │
└────────────────────────────────────────────────┘
```

### Écran C : Les rôles
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

### Écran D : Écran principal (en attente)
```
┌────────────────────────────────────────────────┐
│ 8:14          Indices gratuits : 3     ✗ 0/3   │
├────────────────────────────────────────────────┤
│  Dernier message :                             │
│  « Sultan et Comète se font face. Ces          │
│    deux-là sont inséparables. »   — Lucas      │
│                                                │
│  Écurie ✓   Carnet ·   Bureau ·   Sentier ·    │
├────────────────────────────────────────────────┤
│ QUI : —        OÙ : Écurie        QUOI : —     │
└────────────────────────────────────────────────┘
```

### Écran E : Lieu
```
┌────────────────────────────────────────────────┐
│ 8:02                                 L'ÉCURIE  │
├────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────┐  │
│  │      [illustration : box 7 ouvert]       │  │
│  └──────────────────────────────────────────┘  │
│  Le box sept est grand ouvert. La serrure      │
│  n'a pas été forcée…                 (défile)  │
│  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓    │
│  ┃     ✉  OUVREZ L'ENVELOPPE  E1          ┃    │
│  ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛    │
└────────────────────────────────────────────────┘
```

### Écran F : Interrogatoire
```
┌────────────────────────────────────────────────┐
│ 8:09                       INTERROGATOIRE      │
├──────────────┬─────────────────────────────────┤
│  [portrait]  │  LUCAS BERNIER                  │
│              │  Palefrenier, 24 ans            │
│              │  « J'ai fait ma ronde à 22 h,   │
│              │    tout était fermé… »          │
├──────────────┴─────────────────────────────────┤
│ QUI : Lucas       OÙ : Écurie      QUOI : —    │
└────────────────────────────────────────────────┘
```

### Écran G : Confrontation (personnage + objet)
```
┌────────────────────────────────────────────────┐
│ 8:44                             CONFRONTATION │
├──────────────┬─────────────────────────────────┤
│  [portrait   │  Vous montrez à LUCAS :         │
│   Lucas]     │     [vignette : bottes]         │
│              │  « Ce sont les bottes de        │
│              │    Madame. Mais elle ne va      │
│              │    jamais dans la pinède ! »    │
└──────────────┴─────────────────────────────────┘
```

### Écran H : Code
```
┌────────────────────────────────────────────────┐
│ 8:15                     PORTE DU BUREAU 🔒    │
├────────────────────────────────────────────────┤
│            ┌───┐ ┌───┐ ┌───┐ ┌───┐             │
│            │ 3 │ │ 7 │ │ 0 │ │ _ │             │
│            └───┘ └───┘ └─▲─┘ └───┘             │
│     Tourner : changer · Appuyer : valider      │
│     Bouton rouge : effacer       Essais : 1    │
└────────────────────────────────────────────────┘
```

### Écran I : Question du carnet
```
┌────────────────────────────────────────────────┐
│ 8:22                    CARNET DE SANTÉ        │
├────────────────────────────────────────────────┤
│  Vous avez déchiffré le carnet ? Que dit-il ?  │
│   (●) ROUGE  : une simple fatigue              │
│   (●) BLEU   : une tendinite, 6 mois d'arrêt   │
│   (●) JAUNE  : un vaccin à faire               │
│   (●) VERT   : pas encore                      │
└────────────────────────────────────────────────┘
```

### Écran J : Croisement du sentier
```
┌────────────────────────────────────────────────┐
│ 8:32     SENTIER DES BOIS · CROISEMENT 1 / 3   │
├────────────────────────────────────────────────┤
│        [illustration : chemin qui se divise]   │
│   ( ROUGE )   ( BLEUE )   ( JAUNE )   ( VERTE )│
│      Carnet du sentier : PAGE 1                │
│      Quelle piste suivez-vous ?                │
└────────────────────────────────────────────────┘
```
Le pouvoir du Maréchal barre une balise : `( BLEUE )` devient `(  ╳  )`.

### Écran K : Imprévu
```
┌────────────────────────────────────────────────┐
│ !!!!!!!!!!!!!!!!!!  IMPRÉVU  !!!!!!!!!!!!!!!!!!│
├────────────────────────────────────────────────┤
│      RAFALE S'EST ÉCHAPPÉ DANS LA COUR !       │
│      Les 4 BOUTONS en même temps, 3 s !        │
│        ██████████████░░░░░░  7 s               │
└────────────────────────────────────────────────┘
```

### Écran L : Casque 🎧
```
┌────────────────────────────────────────────────┐
│ 8:24                                   🎧      │
├────────────────────────────────────────────────┤
│               MESSAGE PRIVÉ                    │
│                                                │
│     Un seul joueur met le casque, puis         │
│     appuie sur la molette.                     │
│                                                │
│     ⚠ Ce message ne passera qu'une fois.      │
│     ▶ ▶ ▶  (barre de lecture, sans texte)      │
└────────────────────────────────────────────────┘
```
La mention « une seule fois » n'apparaît que pour l'appel anonyme. Les
confidences et les alibis restent consultables en reposant les cartes, mais
toujours au casque.

### Écran M : Papy Marcel
```
┌────────────────────────────────────────────────┐
│ 8:26                   UN COUP DE MAIN ?       │
├──────────────┬─────────────────────────────────┤
│  [portrait   │  « Lucas vous a donné la règle  │
│   Papy       │    du code. Et l'ordre de       │
│   Marcel]    │    passage, qui c'est qui       │
│              │    l'a ? »                      │
│              │  Indice 1/3 · gratuit (reste 2) │
└──────────────┴─────────────────────────────────┘
```

### Écran N : Accusation
```
┌────────────────────────────────────────────────┐
│ 8:51                      ⚖  ACCUSATION        │
├────────────────────────────────────────────────┤
│     QUI              OÙ              POURQUOI  │
│  ┌─────────┐    ┌───────────┐    ┌──────────┐  │
│  │ Hélène  │    │  Vieille  │    │ Contrat  │  │
│  │ Vasseur │    │  Grange   │    │d'assuranc│  │
│  └─────────┘    └───────────┘    └──────────┘  │
│   VERT : confirmer          ROUGE : annuler    │
└────────────────────────────────────────────────┘
```

### Écran O : Résultat
```
┌────────────────────────────────────────────────┐
│               ★  AFFAIRE RÉSOLUE  ★            │
├────────────────────────────────────────────────┤
│   Temps restant ............. 7 min 05 s       │
│   Indices payants ........... 1                │
│   Erreurs d'accusation ...... 0                │
│              ✿  FLOT ROUGE  ✿                  │
│   Tableau d'honneur du Haras : 3ᵉ place        │
└────────────────────────────────────────────────┘
```

---

## 4. Le déroulé d'une partie type

Les heures sont celles du **chrono du jeu**, de 8:00 à 9:00, pour un rythme moyen.

### Avant la partie (5 min)
| Action | Détail |
|---|---|
| Mise en place | Fiche de mission, cartes Lieu et Personnage et carte Papy Marcel au centre. Enveloppes E1 à E8 empilées, cartes Objet à l'intérieur. Casque branché. |
| Distribution | Les 6 rôles et leurs documents secrets, **face cachée** |
| Allumage | **Écran A** · 90/001 jingle du pupitre · 91/001, 91/002 |

### Lancement (avant le chrono)
| Moment | Joueurs | Cartes | Écran | Son | LED |
|---|---|---|---|---|---|
| — | Posent la carte **Monde Haras** | Monde | **B** | 11/012 jingle · 🔊 013 | Vert prairie |
| — | Installent la façade en bois, appuient sur la molette | — | B : mission 1 | 🔊 001 | |
| — | Chacun pose sa carte Rôle puis la reprend | Rôles | **C** | 🔊 002 à 008 · 90/002 | Flash vert sur la zone |
| — | 6/6 : un joueur lit la lettre de Camille, puis le narrateur | — | Texte défilant | 🔊 009 | Bleu nuit |
| **8:00** | Le chrono démarre | — | **D** | 🔊 010 · 11/005 ambiance | **12 LED vertes** |

### 8:00 – 8:10 : la scène du crime (J1)
| Heure | Joueurs | QUI | OÙ | QUOI | Écran | Son | Étape |
|---|---|---|---|---|---|---|---|
| 8:01 | Lisent leurs documents secrets en silence | — | — | — | D | ambiance | |
| 8:02 | Posent l'Écurie | — | **Écurie** | — | **E** + « E1 » | 🔊 020 | ✅ **J1** |
| 8:03 | Ouvrent E1 : carnet chiffré, photo du box, carte O1 | — | Écurie | — | E | 90/009 | |
| 8:04 | Le Maréchal reconnaît le fer ovale d'Éclipse | | | | | | |
| 8:05 | La Vétérinaire lit sa note et demande au Maréchal : **6 clous** | | | | | | |
| 8:06 | Interrogent Camille | **Camille** | Écurie | — | **F** | 🔊 150 | Indice « Sultan côté sud » |
| 8:08 | **IMPRÉVU Rafale** : les 4 boutons en même temps | | | | **K** | 90/005 · 🔊 080 | LED rouges tournantes |
| 8:08 | Réussi (sinon −2 min) | | | | D | 🔊 081 | |
| 8:09 | Interrogent Lucas | **Lucas** | Écurie | — | F | 🔊 135, 136 | **Règle du code**, « Sultan et Comète » |

### 8:10 – 8:20 : le code du bureau (J3)
| Heure | Joueurs | QUI | OÙ | QUOI | Écran | Son | Étape |
|---|---|---|---|---|---|---|---|
| 8:10 | Interrogent Hélène | **Hélène** | Écurie | — | F | 🔊 120 | |
| 8:11 | Posent le Bureau : verrouillé | — | **Bureau** | — | **H** | 🔊 024 · 11/014 | |
| 8:12 | Le Journaliste raconte : *« Rafale et Éclipse se font face »* | | | | | | |
| 8:13 | Le Parieur lit l'ordre de passage : Rafale, Éclipse, Comète, Sultan | | | | | | |
| 8:14 | Grille sur le plan : Rafale au 3, Sultan au 5, Comète au 1 | | | | | | |
| 8:15 | Essai `3751` | — | Bureau | — | H : incorrect | 🔊 025 · 90/003 | |
| 8:16 | *L'imprévu Comète devrait tomber, mais il attend la fin de la saisie* | | | | H | — | |
| 8:17 | **`3715`** | — | Bureau | — | E Bureau + « E3 » | 11/003 · 🔊 027 | ✅ **J3** |
| 8:17 | **IMPRÉVU Comète** : rythme du galop, 4 fois au gros bouton | | | | K | 🔊 083 · 11/006 ×3 au tempo | |
| 8:18 | Réussi | | | | D | 🔊 084 | |
| 8:19 | Ouvrent E3 : contrat, clé n° 7, ticket « COUPURE 03:12 → 04:05 ». Le Gendarme : *« Seuls Hélène et Lucas ont le code. »* | | | | | | |

### 8:20 – 8:30 : le carnet, l'appel anonyme, les confidences (J2)
| Heure | Joueurs | QUI | OÙ | QUOI | Écran | Son | Étape |
|---|---|---|---|---|---|---|---|
| 8:21 | Les déchiffreurs finissent : *« TENDINITE… SIX MOIS… PAS MÊME L'ASSURANCE »* | | | | | | |
| 8:22 | Posent le carnet et appuient sur le bouton **bleu** | — | — | **Carnet** | **I** | 🔊 112, 113 · 90/004 | ✅ **J2** |
| 8:23 | Montrent le carnet à Hélène | **Hélène** | — | **Carnet** | **G** | 🔊 122 | Elle ment |
| 8:24 | **IMPRÉVU Appel anonyme 🎧** : un joueur met le casque et appuie sur la molette | | | | **L** | 11/007 · 🔊 074 · 90/006 | |
| 8:24 | Il écoute **seul**, une seule fois | | | | L (lecture) | 🎧 🔊 075 | |
| 8:25 | Il raconte : *« une lampe dans la pinède vers 3 h 30, un cheval clair, une petite silhouette avec un seau »* | | | | D | 91/006 | 🧠 Indice |
| 8:26 | Montrent le carnet à Delorme | **Delorme** | — | **Carnet** | G | 🔊 198 | « 50 000 € au mieux » |
| 8:27 | Sellerie | — | **Sellerie** | — | E + « E2 » | 🔊 022 | Bottes « H.V. », seau et couverture manquants |
| 8:28 | Le Journaliste fait insister Lucas | **Lucas** | — | **Rôle Journaliste** | L | 🔊 118 · 🎧 🔊 137 | « La porte arrière a grincé » |
| 8:29 | Le Comptable expertise le contrat | **Rôle Comptable** | — | **Contrat** | Encadré « Expertise » | 🔊 108 | 450 000 € contre 50 000 € |
| 8:30 | **IMPRÉVU Hélène entre** : documents retournés | | | | K | 11/010 · 🔊 086 | LED orange |
| 8:30 | Hélène interroge un rôle tiré au sort, qui lui répond à voix haute | | | | Portrait d'Hélène | 🔊 087 à 092 | |
| 8:30 | Elle repart : *« Ne perdez pas votre temps dans la pinède. »* | | | | D | 🔊 093 | 🧠 Indice glissé |

### 8:31 – 8:40 : le sentier des bois (J4 et J5)
| Heure | Joueurs | QUI | OÙ | QUOI | Écran | Son | Étape |
|---|---|---|---|---|---|---|---|
| 8:31 | *« La pinède… elle veut nous en éloigner ! »* | — | **Sentier** | — | E + « E8 » | 🔊 036 · 11/013 | |
| 8:32 | Croisement 1 : **jaune** | | | | **J** 1/3 | 🔊 037, 041 | LED vert sapin |
| 8:33 | Croisement 2 : hésitent et choisissent **jaune** : raté | | | | D | 🔊 040 · 90/003 | −2 min |
| 8:35 | Le Maréchal utilise son pouvoir | **Rôle Maréchal** | Sentier | — | J avec ╳ | 🔊 048 | |
| 8:36 | **Bleue** : fer ovale **et** semelle à chevrons | | | | J 3/3 | 🔊 041, 039 | |
| 8:37 | *« Ce sont les semelles des bottes ! »* **Verte** | | | | Grange au loin | 🔊 042 · 11/002 | LED blanches pulsées |
| 8:38 | | | | | « Lieu découvert » | 🔊 043 | ✅ **J4** |
| 8:39 | Posent la Vieille Grange | — | **Grange** | — | E + « E4 » | 🔊 045 · 11/001 | ✅ **J5** |

### 8:40 – 8:50 : recouper les preuves
| Heure | Joueurs | QUI | OÙ | QUOI | Écran | Son | Étape |
|---|---|---|---|---|---|---|---|
| 8:40 | **IMPRÉVU Gendarmerie** : *« À quelle heure la caméra a-t-elle été coupée ? »* | | | | H (heure) | 11/007 · 🔊 094, 095 | |
| 8:41 | **0312** à la molette | | | | D | 🔊 098 | **+2 min** |
| 8:42 | Le Gendarme vérifie Lucas 🎧 | **Lucas** | — | **Rôle Gendarme** | L | 🎧 🔊 101 | Au téléphone : innocenté |
| 8:43 | Le Gendarme vérifie Hélène 🎧 | **Hélène** | — | **Rôle Gendarme** | L | 🎧 🔊 100 | « Aucun justificatif » |
| 8:44 | Montrent les bottes à Lucas | **Lucas** | — | **Bottes** | G | 🔊 138 | « Elle ne va jamais dans la pinède » |
| 8:45 | Montrent les bottes à Hélène | **Hélène** | — | **Bottes** | G | 🔊 124 | Mensonge : il pleuvait la nuit |
| 8:46 | Carrière (fausse piste) | — | **Carrière** | — | E + « E6 » | 🔊 031 | |
| 8:47 | Montrent le contrat à Camille | **Camille** | — | **Contrat** | G | 🔊 153 | « Juste après le diagnostic ! » |
| 8:48 | Débat final : on écarte Victoire, Antoine et Delorme | | | | | | |

### 8:50 – 8:55 : l'accusation (J6)
| Heure | Joueurs | QUI | OÙ | QUOI | Écran | Son | Étape |
|---|---|---|---|---|---|---|---|
| 8:50 | Le Parieur arme le Coup de poker | **Rôle Parieur** | — | — | « Coup de poker armé » | 🔊 115 | |
| 8:51 | Posent les 3 cartes et débattent | **Hélène** | **Grange** | **Contrat** | **N** | — | |
| 8:52 | **Gros bouton**, puis **vert** | | | | N | 🔊 050, 054 · 90/007 | LED de zone clignotantes |
| 8:53 | Les zones passent au vert une par une : QUI ✓ · OÙ ✓ · POURQUOI ✓ | | | | « C'est juste ! » | 🔊 063 · 11/008 | ✅ **J6** |

**Si l'accusation est fausse :** l'écran affiche « 2 éléments sur 3 sont justes »
(🔊 057, 058 : −5 min). Avec le Coup de poker : 🔊 059, la LED de la zone fausse
passe au rouge, et il n'y a pas de pénalité.

### Épilogue (chrono arrêté)
| Écran | Son |
|---|---|
| Hélène en larmes, Éclipse dans son box | 🔊 064 |
| Portrait d'Hélène | 🔊 129 (les aveux) |
| Camille et Éclipse, six mois plus tard | 🔊 065 |
| **Écran O** : score et rang | 🔊 066 / 067 / 068 |
| Tableau d'honneur du Haras, saisie du nom d'équipe | 91/015, 91/016 · 11/008 |

### Si le temps s'écoule
| Heure | Écran | Son | LED |
|---|---|---|---|
| 8:55 | « DERNIÈRE LIGNE DROITE » | 🔊 078 · 11/009 | Rouges clignotantes |
| 8:59 | Compte à rebours en grand | 🔊 079 · 90/008 chaque seconde | Clignotement rapide |
| 9:00 | « L'expert est arrivé » | 🔊 072, 070 · 11/011 | Extinction une à une |
| — | La solution | 🔊 071 | Blanc |

---

## 5. Les LED en résumé

| Situation | Anneau (12 LED) | LED de zone (×3) |
|---|---|---|
| Accueil du pupitre | Arc-en-ciel lent | Blanc qui respire |
| Partie en cours | **Chrono** : une LED s'éteint toutes les 5 min, du vert au rouge | Blanc faible si une carte est posée |
| Carte acceptée / refusée | Flash vert / rouge | Flash vert / rouge sur la zone |
| Message au casque | Violet qui pulse | Éteintes |
| Imprévu | Rotation rouge | Rouges |
| Sentier | Vert sapin | — |
| Accusation | Clignotement blanc | Vert une par une (juste), rouge (poker) |
| Victoire / échec | Arc-en-ciel / extinction progressive | Vertes / éteintes |
