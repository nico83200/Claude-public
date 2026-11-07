# 🎄 Village de Noël – Arduino Nano

Sketch : [`village_noel.ino`](village_noel.ino) (bibliothèque **FastLED** requise).

## Ce que fait l'installation

| Élément | Commande | Effet |
|---|---|---|
| 8 lampadaires (3,3 V) | MOSFET sur **D3** (PWM) | Crépitement à l'allumage puis montée progressive, comme de vrais lampadaires |
| Buvette (LED 5 V) | **D5** (PWM) | Lumière chaude qui vacille comme une lanterne |
| 3 cabanes WS2812B | **D6** | Cabane 1 : bougie / blanc chaud · Cabane 2 : guirlande multicolore · Cabane 3 : rouge ↔ vert. Elles s'allument une par une au crépuscule |
| Sapin 9 V | Relais 1 sur **D7** | S'allume à la nuit tombée |
| Feux des 3 voitures 9 V | Relais 2 sur **D8** | « Passent » de temps en temps la nuit (allumés 20–40 s, éteints 8–20 s) |
| Bouton | **D2** → GND | Change de mode |
| Photorésistance (option) | **A0** | Le village s'allume tout seul quand la pièce s'assombrit |

**Modes** (1 à 4 flashs bleus sur la 1ʳᵉ LED pour confirmer) :
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
                                             └─► (+) lampadaires (via résistance)

TOUS LES GND (alim, convertisseur, Nano, relais, WS2812B, MOSFET, sapin, voitures) RELIÉS ENSEMBLE
```

- **Réglez le convertisseur à 5,0 V au multimètre AVANT** de brancher quoi que ce soit dessus.
- Le Nano est alimenté par **VIN en 9 V** : on peut garder l'USB branché pour le téléverser/déboguer sans risque.
- Alim 9 V : prévoir **2 A** ; convertisseur 5 V de type LM2596 (3 A) ou équivalent.

## Câblage détaillé

### Lampadaires (3,3 V) – MOSFET recommandé
Le Nano ne peut pas fournir le courant de 8 lampadaires (max ~20 mA par broche), et un relais ne permet pas de faire de fondu. Ajoutez un **MOSFET canal N « logic level »** (IRLZ44N, IRL540N, ou module MOSFET tout prêt – ~1 €) :

```
Rail 5 V ──► Résistance R ──► (+) lampadaires
                               (−) lampadaires ──► Drain MOSFET
D3 ──► 220 Ω ──► Grille MOSFET      Grille ──► 10 kΩ ──► GND
                               Source MOSFET ──► GND
```

Calcul de **R** : mesurez le courant des lampadaires sous 3 V (2 piles AA font très bien l'affaire), puis
`R = (5 − 3,3) / I`. Exemple : 160 mA → R ≈ 10 Ω, ½ W.

> Sans MOSFET : branchez les lampadaires en fixe sur le 5 V via R (toujours allumés), ou partagez un relais avec un autre élément.

### Buvette (LED 5 V)
- LED nue : D5 → résistance 220 Ω → LED → GND.
- LED « 5 V » avec résistance intégrée consommant **< 20 mA** : D5 → LED → GND directement.
- Si elle consomme plus : passez aussi par un petit MOSFET/transistor.

### Cabanes WS2812B
- 5 V et GND depuis le convertisseur (pas depuis le Nano), **condensateur 1000 µF** entre 5 V et GND au début du ruban.
- D6 → **résistance 330 Ω** → DIN de la 1ʳᵉ LED.
- Modifiez `LEDS_PAR_CABANE` dans le sketch selon votre nombre de LED par cabane.

### Relais (sapin & voitures)
- Module relais : VCC → 5 V, GND → GND, IN1 → D7, IN2 → D8.
- Côté puissance : le **+9 V** passe par COM → NO ; le (−) des éléments retourne au GND.
- Si les relais fonctionnent « à l'envers », passez `RELAIS_ACTIF_BAS` à `false`.

### Bouton & photorésistance (option)
- Bouton entre D2 et GND (résistance de tirage interne activée).
- LDR entre 5 V et A0, résistance 10 kΩ entre A0 et GND, puis `UTILISER_LDR = true`. Ajustez les seuils 600/300 dans `niveauNuit()` en regardant `analogRead(A0)`.

## Pour aller plus loin
- **2 MOSFET de plus** à la place des relais → sapin qui « respire » et phares des voitures qui s'allument en fondu, sans claquement.
- **Module MP3 DFPlayer Mini** (~3 €) → musique de Noël en mode FÊTE.
- **Capteur PIR** → le village se réveille quand quelqu'un s'approche.
