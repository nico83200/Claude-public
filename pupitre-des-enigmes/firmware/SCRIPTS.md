# Le langage des scripts

Une mission est un fichier JSON (`mondes/<monde>/pupitre/mission-N.json`) que le
moteur du pupitre exécute. Ce document en décrit toutes les clés. Les quatre
missions livrées servent d'exemples complets :
[Haras](../mondes/haras/pupitre/mission-1.json) ·
[Monte-Carlo](../mondes/casse/pupitre/mission-2.json) ·
[Kepler-9](../mondes/kepler/pupitre/mission-3.json) ·
[Narval](../mondes/narval/pupitre/mission-4.json) (un escape game sans rôles ni accusation).

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
| `jauge` | Ce que montre l'anneau de LED : `"chrono"`, `"oxygene"` (se vide), `"eau"` (se remplit) ou `"soupcon"` | `"soupcon"` |
| `objets_combinables` | `true` : les objets se posent aussi sur QUI ; deux objets ensemble font un événement « combinaison » (§ 3) | `true` |
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
| Lieu | une carte Lieu est posée sur OÙ | `{"lieu": "L1"}` |
| Personnage | une carte Personnage est posée sur QUI, QUOI vide | `{"qui": "P2"}` |
| Objet | une carte Objet est posée sur QUOI, QUI vide | `{"quoi": "O1"}` |
| Paire | un personnage et un objet sont posés ensemble | `{"qui": "P1", "quoi": "O3"}` |
| Rôle | une carte Rôle est posée ; les autres zones donnent ses cibles | `{"role": "R5", "qui": "P4"}` |
| Objet dans un lieu | un objet est posé alors qu'un lieu est sur OÙ | `{"lieu": "L2", "quoi": "O5"}` |
| Combinaison | deux objets sur QUI et QUOI (avec `objets_combinables`), dans n'importe quel ordre | `{"combiner": ["O1", "O3"]}` |
| Événement | une action `evenement` le déclenche | `{"evenement": "croisement2"}` |

Une **combinaison** qu'aucune règle ne prévoit est traitée comme le dernier objet
posé, seul, dans le lieu courant.

Pour un **rôle**, la cible peut être posée avant ou après la carte Rôle : poser
un objet sur QUOI alors qu'une carte Rôle attend sur QUI déclenche aussi
l'événement « rôle ».

Valeurs possibles : un identifiant (`"P1"`), une liste (`["L8", "L9"]`), `"*"`
(n'importe quelle carte) ou `"-"` (zone vide).

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
| `qui_P1`, `ou_L2`, `quoi_O3` | 1 si cette carte est posée sur cette zone |

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
| `{"victoire": true}` · `{"defaite": "temps"}` | Terminer la partie (la défaite joue `fins.<cause>`) |

## 6. Les épreuves

Toutes les épreuves acceptent :

| Clé | Rôle |
|---|---|
| `type` | Le type (tableau ci-dessous) |
| `titre`, `texte` | L'écran |
| `intro` | Une action jouée au lancement (souvent `{"dire": n}`) |
| `reussite`, `echec` | Les actions à la fin |
| `secondes` | Limite de temps (codes, choix, `ensemble`, `molette_zero`…) |
| `quitter` | `true` : retirer la carte Lieu abandonne l'épreuve, sans pénalité |
| `aides` | Les rôles qui aident : `{"R1": {"type": "barrer", "dire": 48}}` |

| Type | Les joueurs… | Clés propres |
|---|---|---|
| `code` | règlent des chiffres à la molette (appui : champ suivant, rouge : effacer) | `solution` ; `champs` (largeur de chaque champ, ex. `[2, 2, 2]` pour un coffre 17-34-22) ; `ecoute` (stéthoscope : `dossier`, `tic`, `clac`, `audibles`) ; `boucle_casque` (`dossier`, `n`) |
| `lettres` | règlent des lettres A–Z | `solution` |
| `choix` | appuient sur un bouton de couleur | `options` (4 textes), `bonne` (0 rouge … 3 vert), `neutre` + `si_neutre`, ou `actions` (une liste par bouton) |
| `ensemble` | tiennent les 4 boutons enfoncés | `tenir` (secondes) |
| `rythme` | reproduisent un rythme au gros bouton | `motif` (instants en ms), `repetitions`, `son`, `tolerance` (ms) |
| `sequence` | rejouent une suite de couleurs (20 s) | `longueur` |
| `immobile` | ne touchent à rien | `secondes` |
| `molette_zero` | ramènent la molette à zéro | `depart` |
| `rondes` | appuient au gros bouton quand l'anneau est vert | `pas`, `pris` (actions si on appuie au rouge) |
| `energie` | répartissent des unités entre des modules (vert : valider) | `modules`, `total`, `max`, `regles` (`expr` avec `m0`, `m1`… et `somme`, `texte`), `erreur` |
| `traitre` | se passent le casque pour le tirage au sort du traître | `minimum`, `traitre`, `loyal`, `suivant` (répliques) |
| `appui` | appuient sur le gros bouton (l'anneau clignote) | `secondes` |

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

## 11. Ce que le pupitre dit tout seul

La voix du pupitre (dossier 91, `console/audio/repliques.json`) et ses bruitages
(dossier 90) sont utilisés par le moteur sans que le script ait à les citer :
mauvaise zone (7 à 9), carte inconnue (10), carte d'un autre monde (4), casque
(5 et 6), pause et reprise (11 et 12)… Les bruitages : bip (2), erreur (3),
carillon du casque (6), roulement de l'accusation (7).

## 12. Vérifier

```
python3 outils/verifier_scripts.py   # répliques, bruitages, cartes, types d'épreuves
sh firmware/test/lancer.sh           # rejoue les parties de test dans le simulateur
```
