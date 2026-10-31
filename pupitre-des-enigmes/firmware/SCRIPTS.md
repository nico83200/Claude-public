# Le langage des scripts

Une mission est un fichier JSON (`mondes/<monde>/pupitre/mission-N.json`) que le
moteur du pupitre exécute. Ce document en décrit toutes les clés. Les quatre
missions livrées servent d'exemples complets (la mission 4 se sert du clavier, du
panneau de voyants, de la lumière sous la boîte et de l'interrupteur à clé) :
[Haras](../mondes/haras/pupitre/mission-1.json) ·
[Monte-Carlo](../mondes/casse/pupitre/mission-2.json) ·
[Kepler-9](../mondes/kepler/pupitre/mission-3.json) ·
[Narval](../mondes/narval/pupitre/mission-4.json) (un escape game sans rôles ni accusation).
La mission 6, [Désamorçage](../mondes/deminage/pupitre/mission-6.json), est produite par un
[générateur](../mondes/deminage/generer.py) : elle tire une bombe au sort avec l'action `hasard`.

Le principe : **quand** une carte est posée, le moteur cherche la première
**règle** qui correspond et dont la **condition** est vraie, puis exécute ses
**actions** (dire une réplique, ouvrir une enveloppe, lancer une épreuve…).

## 1. Le monde : `monde.json`

```json
{
  "id": "haras",
  "nom": "Le Haras des Trois Fers",
  "carte": "M1",                 // la carte Monde
  "aide": "A1", "aide_nom": "Papy Marcel",
  "theme": { "fond": "#1f3b2d", "texte": "#f3ead8", "accent": "#c9a54a", "alerte": "#c0392b", "doux": "#9fb59f" },
  "accueil": { "dossier": 10, "dire": 13, "jingle_dossier": 11, "jingle": 12 },
  "missions": [ { "numero": 1, "titre": "La Disparition d'Éclipse", "script": "mission-1.json", "repliques": "repliques-1.json" } ]
}
```

Les fichiers `repliques-N.json` (sous-titres) et `cartes.json` (puces NFC) sont
produits par `outils/preparer_sd.py` et par l'enregistrement des cartes.
(Les commentaires `//` ci-dessus sont là pour l'explication : JSON n'en accepte pas.)

## 2. L'en-tête d'une mission

| Clé | Rôle | Exemple |
|---|---|---|
| `titre` | Nom de la mission | `"Signal Perdu"` |
| `dossier_voix`, `dossier_sons` | Dossiers des DFPlayer | `30`, `31` |
| `chrono` | `mode` `"horloge"` (heure qui avance depuis `depart`) ou `"compte"` (compte à rebours, avec un `prefixe`) ; `duree` en minutes | `{"mode": "compte", "prefixe": "O₂ ", "duree": 75}` |
| `jauge` | Ce que montre l'anneau de LED : `"chrono"`, `"oxygene"` (se vide), `"eau"` (se remplit), `"soupcon"` ou `"lumiere"` (la lumière de la dynamo) | `"soupcon"` |
| `anneau` | Un compteur de la mission prend l'anneau tant que `si` est vraie : `compteur`, `sur` (la valeur qui allume les 12 LED), `couleur`. Le Manoir Harrington s'en sert pour allumer un segment vert par gemme posée | `{"si": "gemmes>0", "compteur": "gemmes", "sur": 12, "couleur": "vert"}` |
| `objets_combinables` | `true` : QUI et QUOI sont les deux mains des joueurs (pupitre à deux lecteurs : la fente et le plateau) ; deux objets ensemble font un événement « combinaison » (§ 3) | `true` |
| `materiel` | Les éléments du pupitre dont la mission se sert : `fente`, `plateau`, `clavier`, `voyants`, `couleur`, `cle`, `boutons`, `molette`, `gros_bouton`, `casque`, `anneau`, `gestes` (l'œil : gestes et lampe), `souffle` (la forge), `aimant` (l'enclume), `afficheur` (l'horloge à 4 chiffres), `tiroir` (le tiroir secret). Sa façade cache les autres ; l'écran ne les cite pas. Absente : tout. | `["fente", "plateau", "clavier"]` |
| `zones` | Les noms des trois zones à l'écran (`noms`), et les répliques quand une carte arrive sur la mauvaise zone d'un pupitre à trois lecteurs (`dire_lieu`, `dire_objet`, `dire_perso`) | `{"noms": ["MAIN GAUCHE", "COMPARTIMENT", "MAIN DROITE"]}` |
| `tiroir_lumiere` | La couleur de la LED qui éclaire le tiroir secret quand il sort (blanc chaud par défaut) | `"ambre"` |
| `dynamo` | La lumière à entretenir au gros bouton (`bouton`) : elle se vide en `duree` secondes, chaque appui rend `gain` %, à zéro c'est le noir complet et les cartes ne donnent que la réplique `noir` | `{"bouton": "GROS", "duree": 80, "gain": 15, "noir": 17}` |
| `objectifs` | Le tableau de bord (appui sur la molette) et la ligne du bas : `nom`, `visible` et `fait` (conditions) | `[{"nom": "Rétablir le courant", "visible": "J_porte", "fait": "J_courant"}]` |
| `panneau` | Le panneau de 10 voyants (§ 11) | |
| `socle` | La lumière sous la boîte (§ 11) | |
| `soupcon` | Jauge de soupçon : `max` (défaite), `annulation` (un rôle peut annuler une hausse), `alerte` (réplique à un seuil) | voir Monte-Carlo |
| `cartes` | Toutes les cartes de la mission : `type` (`lieu`, `perso`, `objet`, `role`) et `nom` | `{"L1": {"type": "lieu", "nom": "L'Écurie"}}` |
| `voix` | Nom affiché sous chaque sous-titre, par voix | `{"helene": "Hélène Vasseur"}` |
| `roles` | Réplique d'arrivée et nombre d'utilisations du pouvoir. Vide (`{}`) : pas de cartes Rôle, l'introduction démarre aussitôt | `{"R3": {"dire": 5, "pouvoir": 2}}` |
| `pouvoirs` | Compteurs de pouvoir supplémentaires | `{"R3_papiers": 1}` |
| `pouvoir_epuise` | Réplique quand un pouvoir n'a plus d'utilisation | `106` |
| `roles_demande` | Réplique qui demande de poser les rôles | `2` |
| `intro` | Actions avant le départ du chrono | `[{"dire": 9}]` |
| `debut` | Actions au départ du chrono | `[{"dire": 10}, {"ambiance": 5}]` |
| `reprise` | Actions à la reprise d'une partie sauvegardée | `[{"ambiance": 5}]` |
| `progression` | Étapes affichées en bas de l'écran (✓ quand la condition est vraie) | `[{"nom": "Bureau", "si": "J3"}]` |
| `regles` | Les règles (§ 3) | |
| `defaut` | Actions si aucune règle ne correspond, par type d'événement | `{"lieu": [{"dire": 11}]}` |
| `imprevus` | Événements minutés (§ 7) | |
| `relances` | Quand faire clignoter « AIDE ? » à l'écran | `[{"minute": 25, "si": "!J3"}]` |
| `aide` | La carte Aide et ses indices (§ 8) | |
| `accusation` | L'accusation finale (§ 9). Absente : le gros bouton ne sert qu'aux épreuves `appui` | |
| `fins` | Victoire, rangs et défaites (§ 10) | |

## 3. Les règles

```json
{ "note": "Le code du bureau", "quand": {"lieu": "L3"}, "si": "!J3", "alors": [ … ] }
```

- `note` : un commentaire, ignoré par le moteur.
- `quand` : l'événement attendu (ci-dessous).
- `si` (facultatif) : une condition (§ 4).
- `alors` : la liste des actions (§ 5).

Les règles sont lues **dans l'ordre** : la première qui correspond gagne. On
écrit donc les cas particuliers avant les cas généraux.

| Événement | Se produit quand… | `quand` |
|---|---|---|
| Lieu | une carte Lieu arrive sur OÙ | `{"lieu": "L1"}` |
| Personnage | une carte Personnage est posée sur QUI, QUOI vide | `{"qui": "P2"}` |
| Objet | une carte Objet est posée sur QUOI, QUI vide | `{"quoi": "O1"}` |
| Paire | un personnage et un objet sont posés ensemble | `{"qui": "P1", "quoi": "O3"}` |
| Rôle | une carte Rôle est posée ; les autres zones donnent ses cibles | `{"role": "R5", "qui": "P4"}` |
| Objet dans un lieu | un objet est posé alors qu'un lieu est sur OÙ | `{"lieu": "L2", "quoi": "O5"}` |
| Combinaison | deux objets sur QUI et QUOI (avec `objets_combinables`), dans n'importe quel ordre | `{"combiner": ["O1", "O3"]}` |
| Couleur | un objet est posé sur le capteur de couleur (`"-"` : il est retiré) | `{"couleur": "rouge"}` |
| Clé | l'interrupteur à clé est tourné, ou revient au repos | `{"cle": "tournee"}` · `{"cle": "repos"}` |
| Geste | la main passe au-dessus de l'œil (hors épreuve) : `haut`, `bas`, `gauche`, `droite` | `{"geste": "gauche"}` |
| Capteur | un capteur tout-ou-rien s'active (hors épreuve) : `souffle`, `aimant`, `eclaire` | `{"capteur": "aimant"}` |
| Tiroir | le tiroir secret sort de son logement (`ouvert`) ou y est repoussé jusqu'au fond (`ferme`) | `{"tiroir": "ouvert"}` |
| Événement | une action `evenement` le déclenche | `{"evenement": "croisement2"}` |

Une **combinaison** qu'aucune règle ne prévoit est traitée comme le dernier objet
posé, seul, dans le lieu courant.

Pour un **rôle**, la cible peut être posée avant ou après la carte Rôle : poser
un objet sur QUOI alors qu'une carte Rôle attend sur QUI déclenche aussi
l'événement « rôle ».

Valeurs possibles : un identifiant (`"P1"`), une liste (`["L8", "L9"]`), `"*"`
(n'importe quelle carte) ou `"-"` (zone vide).

**Le pupitre à deux lecteurs** (une fente et un lecteur à plat) parle aux scripts
avec les mêmes trois zones : chaque carte rejoint la zone de son type (lieu → OÙ,
personnage → QUI, objet → QUOI ; avec `objets_combinables`, l'objet de la fente
va sur QUI et celui du plateau sur QUOI), une carte Rôle prend la zone que l'autre
lecteur laisse libre, et **le lieu reste sur OÙ** quand on reprend sa carte,
jusqu'au lieu suivant. Les scripts écrits pour trois lecteurs marchent tels quels.
Retirer la carte d'un lieu abandonne toujours une épreuve `quitter`.

## 4. Les conditions

Une condition est une expression : `"J2 && !J4"`, `"codes_faux>=3"`,
`"restant>=15 && erreurs==0"`, `"(qui_P1 && poche_P1) || pouvoir_R5==0"`.

Opérateurs : `&&` `||` `!` `==` `!=` `<` `<=` `>` `>=` `+` `-` `*` et les
parenthèses. Un nom vaut :

| Nom | Valeur |
|---|---|
| un drapeau (`J3`, `poker`…) | 1 s'il est levé, sinon 0 |
| un compteur (`codes_faux`, `soupcon`…) | sa valeur |
| `restant`, `ecoule` | minutes restantes, minutes écoulées |
| `erreurs`, `indices`, `indices_payants` | accusations fausses, indices donnés, indices payants |
| `pouvoir_R3` | utilisations restantes du pouvoir |
| `role_R3` | 1 si le rôle est dans la partie |
| `qui_P1`, `ou_L2`, `quoi_O3` | 1 si cette carte est sur cette zone |
| `lumiere` | la lumière de la dynamo, de 0 à 100 |
| `couleur_rouge` | 1 si un objet rouge est sur le capteur de couleur |
| `cle` | 1 si l'interrupteur à clé est tourné |
| `tiroir_ouvert` | 1 tant que le tiroir secret n'est pas poussé au fond |
| `tiroir_present` | 1 si le pupitre a un tiroir secret (pour prévoir une autre cachette sinon) |
| `souffle`, `aimant`, `eclaire` | 1 tant qu'on souffle sur la forge, qu'un aimant est sur l'enclume, qu'une lampe éclaire l'œil |

Une condition vide est vraie. Le moteur écrit dans le journal série toute
condition mal écrite.

## 5. Les actions

| Action | Effet |
|---|---|
| `{"dire": 20}` | Joue la réplique 20 et affiche son sous-titre. Une réplique marquée « casque » passe par le casque. |
| `{"dire": [135, 136]}` | Plusieurs répliques à la suite |
| `{"dire": 75, "unique": true, "delai": 20, "rate": [ … ]}` | Message au casque : prévenir qu'il ne passe qu'une fois, laisser 20 s pour prendre le casque, sinon jouer `rate` |
| `{"dire": 5, "pupitre": true}` | Réplique de la voix du pupitre (dossier 91) |
| `{"dire": 87, "casque": true}` · `"sans_texte": true` | Forcer le casque · ne pas afficher de sous-titre |
| `{"son": 3}` | Bruitage du monde (`"pupitre": true` : dossier 90 ; `"dossier": 31` : un autre dossier ; `"fond": true` : sans attendre ; `"casque": true`) |
| `{"ambiance": 5}` | Fond sonore en boucle quand personne ne parle (`0` l'arrête) |
| `{"titre": "…", "texte": "…"}` | Afficher un texte à l'écran |
| `{"enveloppe": "E3"}` | Bandeau « Ouvrez l'enveloppe E3 » (et drapeau `enveloppe_E3`) |
| `{"drapeau": "J3"}` · `{"retirer": "J3"}` | Lever ou baisser un drapeau (ou une liste) |
| `{"ajouter": {"soupcon": 2}}` · `{"fixer": {"x": 0}}` | Modifier des compteurs |
| `{"temps": -2}` | Ajouter ou retirer des minutes au chrono |
| `{"pouvoir": "R5"}` | Consommer une utilisation d'un pouvoir |
| `{"epreuve": { … }}` | Lancer une épreuve (§ 6) |
| `{"evenement": "croisement2"}` | Déclencher les règles `{"evenement": …}` |
| `{"si": "J3", "alors": [ … ], "sinon": [ … ]}` | Condition |
| `{"hasard": [[ … ], [ … ]]}` | Une des listes d'actions, au hasard |
| `{"attendre": 10000}` | Pause en millisecondes |
| `{"annulable": {"role": "R6", "pouvoir": "R6_annulation", "secondes": 10, "alors": [ … ]}}` | Pendant 10 s, poser la carte R6 exécute `alors` (et consomme le pouvoir) |
| `{"effet": "alerte", "duree": 5000}` | Animation de l'anneau : `alerte`, `succes`, `echec` |
| `{"dynamo": {"duree": 160}}` · `{"dynamo": {"charge": 0}}` | La lumière se vide moins vite · noir complet |
| `{"voyant": {"n": 3, "couleur": "orange", "effet": "clignote", "duree": 5000}}` | Imposer un voyant (§ 11) ; `"n": "tous"` ; sans `couleur`, il revient au `panneau` |
| `{"tiroir": "ouvrir"}` | Libérer le tiroir secret : le ressort le pousse dehors. Il ne se reverrouille qu'à la main : à la fin de la partie, l'écran demande de le pousser au fond puis de valider |
| `{"socle": {"couleur": "rouge", "effet": "pulse", "duree": 8000}}` | Imposer la lumière sous la boîte (§ 11) |
| `{"victoire": true}` · `{"defaite": "temps"}` | Terminer la partie (la défaite joue `fins.<cause>`) |

## 6. Les épreuves

Toutes les épreuves acceptent :

| Clé | Rôle |
|---|---|
| `type` | Le type (tableau ci-dessous) |
| `titre`, `texte` | L'écran |
| `intro` | Une action jouée au lancement (souvent `{"dire": n}`) |
| `reussite`, `echec` | Les actions à la fin |
| `secondes` | Limite de temps (codes, choix, `ensemble`, `molette_zero`, `ordre`, `voyants`, `cle`…) |
| `quitter` | `true` : retirer la carte Lieu abandonne l'épreuve, sans pénalité |
| `aides` | Les rôles qui aident : `{"R1": {"type": "barrer", "dire": 48}}` |

| Type | Les joueurs… | Clés propres |
|---|---|---|
| `code` | règlent des chiffres à la molette (appui : champ suivant, rouge : effacer) ou les tapent au clavier (le code est essayé au dernier chiffre, ou à la touche # avec `"valider": "#"`) | `solution` ; `valider` ; `champs` (largeur de chaque champ, ex. `[2, 2, 2]` pour un coffre 17-34-22) ; `ecoute` (stéthoscope : `dossier`, `tic`, `clac`, `audibles`) ; `boucle_casque` (`dossier`, `n`) |
| `lettres` | règlent des lettres A–Z (au clavier : 2 = ABC… comme sur un téléphone) | `solution` |
| `choix` | appuient sur un bouton de couleur, ou passent la main au-dessus de l'œil | `options` (4 textes), `bonne` (0 rouge … 3 vert), `neutre` + `si_neutre`, ou `actions` (une liste par bouton) ; `gestes` : le geste qui choisit chaque option (`["haut", "droite", "gauche", "bas"]`), avec sa flèche à l'écran |
| `ensemble` | tiennent les 4 boutons enfoncés | `tenir` (secondes) |
| `rythme` | reproduisent un rythme au gros bouton, ou sur un bouton de couleur | `motif` (instants en ms), `repetitions`, `son`, `tolerance` (ms), `bouton` (`"JAUNE"`…) |
| `sequence` | rejouent une suite de couleurs (20 s) | `longueur` |
| `immobile` | ne touchent à rien | `secondes` |
| `molette_zero` | ramènent la molette à zéro | `depart` |
| `rondes` | appuient au gros bouton quand l'anneau est vert | `pas`, `pris` (actions si on appuie au rouge) |
| `energie` | répartissent des unités entre des modules (vert : valider) | `modules`, `total`, `max`, `regles` (`expr` avec `m0`, `m1`… et `somme`, `texte`), `erreur` |
| `traitre` | se passent le casque pour le tirage au sort du traître | `minimum`, `traitre`, `loyal`, `suivant` (répliques) |
| `appui` | appuient sur le gros bouton (l'anneau clignote) | `secondes` |
| `ordre` | appuient sur les boutons de couleur dans un ordre à déduire (une erreur : échec) | `ordre` (`["BLEU", "ROUGE", "VERT", "JAUNE"]`) |
| `voyants` | basculent des interrupteurs (clavier 1 à 9 et 0, ou molette + appui) jusqu'au motif voulu, sur le panneau et à l'écran | `depart`, `cible` (`"1111111111"`), `liens` (`"voisins"`, ou `{"1": [1, 2]}`), `couleur`, `validation` (`"auto"`, ou `"#"` : vérifier au bouton vert) |
| `cle` | tournent l'interrupteur à clé | `secondes` |
| `capteur` | soufflent sur la forge, posent un aimant sur l'enclume ou éclairent l'œil, pendant `tenir` secondes | `capteur` (`"souffle"`, `"aimant"`, `"eclaire"`), `tenir` (1 par défaut), `consigne` (la ligne du bas : où et comment), `pendant` (la ligne du bas pendant le geste) |

Une épreuve `code` peut s'afficher sur l'horloge pendant la saisie : `"afficheur": true`
(« 37-- »), ou `"afficheur": "heure"` pour un code de 4 chiffres lu comme une heure
(« 03:12 »). Le reste du temps, l'horloge montre le chrono de l'en-tête (« 08:14 »,
« 59:57 »).

**Un geste doit être expliqué** : où (l'élément de la façade, nommé et situé), comment
(« la main à plat, à 5 cm au-dessus, d'un bord à l'autre », « bouche ouverte, comme
pour faire de la buée ») et combien de temps. La réplique d'introduction le dit, la
`consigne` le rappelle à l'écran, et un raté ne coûte rien de plus que de recommencer.

Les aides des rôles pendant une épreuve :

| `type` | Effet |
|---|---|
| `barrer` | Barre une mauvaise réponse d'un `choix` |
| `diagnostic` | Affiche la règle d'énergie non respectée |
| `ecoute` | Joue un son en boucle au casque (`dossier`, `n`) ; illimité |
| `stethoscope` | Active les « tic » et « clac » du `code` ; illimité |
| `reussir` | Fait réussir l'épreuve (le monte-plats de l'Acrobate) |

## 7. Les imprévus

```json
{ "minute": 8, "si": "!J5", "actions": [ {"son": 9}, {"dire": 80}, {"epreuve": { … }} ] }
{ "restant": 5, "actions": [ {"dire": 96} ] }
```

Un imprévu se déclenche une seule fois, à une minute de jeu (`minute`) ou à un
temps restant (`restant`). Il attend que le pupitre soit libre (aucune épreuve,
aucune réplique en cours). Si sa condition `si` est fausse à ce moment-là, il est
abandonné.

## 8. L'aide

```json
"aide": { "titre": "PAPY MARCEL", "gratuits": 3, "cout": [{"temps": -2}],
          "appel": 220, "gratuit": 221, "payant": 222, "fin": 238,
          "blocages": [ {"si": "!J1", "niveaux": [223, 224, 225]}, {"si": "1", "niveaux": [235, 236, 237]} ] }
```

Quand la carte Aide est posée, le pupitre cherche le **premier blocage** dont la
condition est vraie et donne son indice suivant (de plus en plus précis). Les
`gratuits` premiers indices sont offerts, les suivants appliquent `cout`.

## 9. L'accusation

Le gros bouton lance l'accusation avec les trois cartes posées.

```json
"accusation": { "titre": "ACCUSATION", "qui": "P1", "ou": "L9", "quoi": "O3", "erreurs_max": 3,
  "penalite": [{"dire": 58}, {"temps": -5}],
  "speciaux": [{"quoi": "O1", "dire": 62, "gratuit": true}],
  "poker": "poker",
  "voix": {"demande": 50, "manque": 51, "suspense": 54, "resultats": [55, 56, 57], "poker": 59, "juste": 63, "troisieme": 69} }
```

- `resultats` : répliques pour 0, 1 et 2 éléments justes.
- `speciaux` : une réplique pour une combinaison précise ; `gratuit` : l'erreur ne
  compte pas.
- `poker` : si ce drapeau est levé, une erreur ne coûte rien (et le drapeau tombe).

## 10. Les fins

```json
"fins": {
  "victoire": [{"son": 8}, {"dire": 64}],
  "rangs": [ {"si": "restant>=15 && erreurs==0", "dire": 66, "titre": "Flot bleu"}, {"si": "1", "dire": 68, "titre": "Flot jaune"} ],
  "temps": [{"dire": 72}], "erreurs": [{"dire": 70}], "soupcon": [{"dire": 75}],
  "solution": [{"dire": 71}]
}
```

Après une défaite (`temps`, `erreurs` ou `soupcon`), `solution` est jouée. Les
parties gagnées sont ajoutées à `/mondes/<monde>/scores.json`.

## 11. Le panneau de voyants et la lumière sous la boîte

Le **panneau** a 10 voyants (2 rangées de 5), que la façade de la mission montre
ou cache. Chaque voyant suit une liste d'états : le premier dont la condition est
vraie s'affiche (réévalué plusieurs fois par seconde). Sans état vrai, il est éteint.

```json
"panneau": { "voyants": [
  { "nom": "Courant", "etats": [ {"si": "J_courant", "couleur": "vert"},
                                 {"si": "J_raccord", "couleur": "orange", "effet": "clignote"},
                                 {"si": "1", "couleur": "rouge"} ] },
  { "nom": "Lumière", "etats": [ {"si": "lumiere>=40", "couleur": "ambre", "intensite": "lumiere"} ] }
] }
```

- `couleur` : `rouge`, `vert`, `bleu`, `jaune`, `orange`, `ambre`, `blanc`,
  `violet`, `cyan`, `rose`, `eteint`, ou `"#rrggbb"`.
- `effet` : `fixe`, `clignote`, `rapide`, `pulse` (respire), `faible`.
- `intensite` : une expression de 0 à 100 (ex. `"lumiere"`).
- `nom` : pour la façade et le panneau Wi-Fi.

La **lumière sous la boîte** (`socle`) fait l'ambiance d'elle-même : la couleur
d'accent du monde, qui respire doucement, et les effets de l'anneau. Une mission
peut la piloter avec la même liste d'états :

```json
"socle": { "etats": [ {"si": "lumiere<15", "couleur": "rouge", "effet": "pulse"},
                      {"si": "1", "couleur": "ambre", "intensite": "lumiere"} ] }
```

Les actions `voyant` et `socle` (§ 5) imposent une couleur un moment, par-dessus
ces états ; l'épreuve `voyants` (§ 6) prend le panneau le temps de l'épreuve.

## 12. Ce que le pupitre dit tout seul

La voix du pupitre (dossier 91, `console/audio/repliques.json`) et ses bruitages
(dossier 90) sont utilisés par le moteur sans que le script ait à les citer :
mauvaise zone (7 à 9), carte inconnue (10), carte d'un autre monde (4), casque
(5 et 6), pause et reprise (11 et 12)… Les bruitages : bip (2), erreur (3),
carillon du casque (6), roulement de l'accusation (7).

## 13. Vérifier

```
python3 outils/verifier_scripts.py   # répliques, bruitages, cartes, types d'épreuves
sh firmware/test/lancer.sh           # rejoue les parties de test dans le simulateur
```
