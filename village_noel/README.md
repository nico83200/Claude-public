# 🎄 Village de Noël – Arduino Nano

Sketch : [`village_noel.ino`](village_noel.ino) (bibliothèque **FastLED** requise).

## Ce que fait l'installation

| Élément | Commande | Effet |
|---|---|---|
| 8 lampadaires (3,3 V) | Relais 3 sur **D3** | S'allument en premier à la tombée de la nuit |
| Buvette (LED 5 V) | **D5** (PWM) | Lumière chaude qui vacille comme une lanterne |
| 3 cabanes WS2812B | **D6** | Cabane 1 : bougie / blanc chaud · Cabane 2 : guirlande multicolore · Cabane 3 : rouge ↔ vert. Elles s'allument une par une au crépuscule |
| Sapin 9 V | Relais 1 sur **D7** | S'allume à la nuit tombée |
| Feux des 3 voitures 9 V | Relais 2 sur **D8** | « Passent » de temps en temps la nuit (allumés 20–40 s, éteints 8–20 s) |
| 2 interrupteurs ON/OFF | **D2** et **D4** → GND | Choisissent le mode |
| Photorésistance (option) | **A0** | Le village s'allume tout seul quand la pièce s'assombrit |

**Modes** avec 2 interrupteurs ON/OFF (1 à 4 flashs bleus sur la 1ʳᵉ LED pour confirmer) :

| Inter D2 | Inter D4 | Mode |
|---|---|---|
| OFF | OFF | AUTO |
| ON | OFF | NUIT |
| OFF | ON | FÊTE |
| ON | ON | ARRÊT |

Avec un seul interrupteur : mettre `NB_INTERRUPTEURS 1`, chaque basculement passe au mode suivant (`TYPE_INTERRUPTEUR = false` pour un bouton poussoir).

1. **AUTO** – cycle jour (2 min) → crépuscule (20 s) → nuit (8 min) → aube. Démarre au crépuscule.
2. **NUIT** – tout allumé en permanence.
3. **FÊTE** – arc-en-ciel + paillettes dans les cabanes, feux des voitures qui clignotent.
4. **ARRÊT** – tout éteint.

## Alimentation

```
Alim 9 V (+) ──┬──────────────► VIN du Nano
               ├──────────────► COM relais 1 ──NO──► (+) sapin
               ├──────────────► COM relais 2 ──NO──► (+) feux voitures
               └──► Convertisseur 9V→5V ──► Rail 5 V
                                             ├─► VCC modules relais
                                             ├─► 5V WS2812B (+ condensateur 1000 µF)
                                             ├─► (+) buvette
                                             └─► COM relais 3 ──NO──► R ──► (+) lampadaires

TOUS LES GND (alim, convertisseur, Nano, relais, WS2812B, lampadaires, sapin, voitures) RELIÉS ENSEMBLE
```

- **Réglez le convertisseur à 5,0 V au multimètre AVANT** de brancher quoi que ce soit dessus.
- Le Nano est alimenté par **VIN en 9 V** : on peut garder l'USB branché pour le téléverser/déboguer sans risque.
- Alim 9 V : prévoir **2 A** ; convertisseur 5 V de type LM2596 (3 A) ou équivalent.

## Câblage détaillé

### Lampadaires (3,3 V) – sur relais 3
```
Rail 5 V ──► COM relais 3
             NO  relais 3 ──► Résistance R ──► (+) lampadaires
                                               (−) lampadaires ──► GND
```

Le relais sert d'interrupteur ; la résistance **R** fait passer le 5 V à ~3,3 V.
Calcul de **R** : mesurez le courant des lampadaires sous 3 V (2 piles AA font très bien l'affaire), puis
`R = (5 − 3,3) / I`. Exemple : 160 mA → R ≈ 10 Ω, ½ W.

> Sans multimètre pour mesurer le courant : **2 diodes 1N4007 en série** (bague grise côté lampadaires) à la place de R font chuter d'environ 1,4 V → ~3,6 V.

### Buvette (LED 5 V)
- LED nue : D5 → résistance 220 Ω → LED → GND.
- LED « 5 V » avec résistance intégrée consommant **< 20 mA** : D5 → LED → GND directement.
- Si elle consomme plus : passez aussi par un petit MOSFET/transistor.

### Cabanes WS2812B
- 5 V et GND depuis le convertisseur (pas depuis le Nano), **condensateur 1000 µF** entre 5 V et GND au début du ruban.
- D6 → **résistance 330 Ω** → DIN de la 1ʳᵉ LED.
- Modifiez `LEDS_PAR_CABANE` dans le sketch selon votre nombre de LED par cabane.

### Relais (sapin, voitures, lampadaires)
- Modules relais : VCC → 5 V, GND → GND, IN1 → D7 (sapin), IN2 → D8 (voitures), IN du 3ᵉ relais → D3 (lampadaires).
- Côté puissance : le **+9 V** passe par COM → NO ; le (−) des éléments retourne au GND.
- Si les relais fonctionnent « à l'envers », passez `RELAIS_ACTIF_BAS` à `false`.

### Interrupteurs & photorésistance (option)
- Chaque interrupteur ON/OFF a 2 pattes utiles : l'une sur D2 (ou D4), l'autre sur GND. Pas de résistance nécessaire (tirage interne activé). S'il a 3 pattes : celle du milieu sur GND, une des deux autres sur la broche.
- LDR entre 5 V et A0, résistance 10 kΩ entre A0 et GND, puis `UTILISER_LDR = true`. Ajustez les seuils 600/300 dans `niveauNuit()` en regardant `analogRead(A0)`.

## Pour aller plus loin
- **MOSFET** (IRLZ44N, ~1 €) à la place des relais → lampadaires qui crépitent puis s'allument en fondu, sapin qui « respire », sans claquement.
- **Module MP3 DFPlayer Mini** (~3 €) → musique de Noël en mode FÊTE.
- **Capteur PIR** → le village se réveille quand quelqu'un s'approche.
