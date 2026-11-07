/*
 * Village de Noël - Arduino Nano
 * ---------------------------------------------------------------
 * Matériel :
 *   - 8 lampadaires (3,3 V)  -> MOSFET logique sur D3 (PWM : fondu + crépitement)
 *   - Buvette (LED 5 V)      -> D5 (PWM : lumière de lanterne qui vacille)
 *   - 3 cabanes WS2812B      -> D6 (une animation différente par cabane)
 *   - Sapin 9 V              -> relais 1 sur D7
 *   - Feux des 3 voitures 9 V-> relais 2 sur D8
 *   - Bouton poussoir        -> D2 vers GND (changement de mode)
 *   - Photorésistance (opt.) -> A0 (allumage automatique à la tombée de la nuit)
 *
 * Modes (bouton) :
 *   0 AUTO  : cycle jour / crépuscule / nuit / aube simulé (ou piloté par la LDR)
 *   1 NUIT  : tout allumé en permanence, animations douces
 *   2 FÊTE  : spectacle (arc-en-ciel dans les cabanes, feux des voitures qui clignotent)
 *   3 ARRÊT : tout éteint
 *
 * Bibliothèque nécessaire : FastLED (Gestionnaire de bibliothèques de l'IDE Arduino)
 */

#include <FastLED.h>

// ------------------------- Brochage -------------------------
const uint8_t PIN_BOUTON          = 2;
const uint8_t PIN_LAMPADAIRES     = 3;   // PWM
const uint8_t PIN_BUVETTE         = 5;   // PWM
const uint8_t PIN_WS2812          = 6;
const uint8_t PIN_RELAIS_SAPIN    = 7;
const uint8_t PIN_RELAIS_VOITURES = 8;
const uint8_t PIN_LDR             = A0;

// ------------------------- Réglages -------------------------
const bool     RELAIS_ACTIF_BAS = true;   // la plupart des modules relais chinois s'activent avec LOW
const bool     UTILISER_LDR     = false;  // true = le mode AUTO suit la lumière ambiante

const uint8_t  NB_CABANES       = 3;
const uint8_t  LEDS_PAR_CABANE  = 3;      // <-- à adapter au nombre de WS2812B par cabane
const uint8_t  NB_LEDS          = NB_CABANES * LEDS_PAR_CABANE;
const uint8_t  LUMINOSITE_WS    = 140;    // 0..255
const uint16_t COURANT_MAX_WS   = 1500;   // mA, limite de sécurité pour le convertisseur 5 V

const uint8_t  LAMPADAIRES_MAX  = 255;    // luminosité maxi des lampadaires (PWM)

// Durées du cycle automatique (millisecondes)
const uint32_t DUREE_JOUR       = 2UL * 60UL * 1000UL;   // 2 min
const uint32_t DUREE_TRANSITION = 20UL * 1000UL;         // 20 s de crépuscule / d'aube
const uint32_t DUREE_NUIT       = 8UL * 60UL * 1000UL;   // 8 min

// ------------------------- Variables -------------------------
enum Mode : uint8_t { MODE_AUTO, MODE_NUIT, MODE_FETE, MODE_ARRET, NB_MODES };
const char* const NOMS_MODES[] = { "AUTO", "NUIT", "FETE", "ARRET" };

Mode     mode       = MODE_AUTO;
uint32_t debutCycle = 0;
CRGB     leds[NB_LEDS];
uint8_t  lumCabane[NB_CABANES] = { 0 };

// ------------------------- Utilitaires -------------------------
void commanderRelais(uint8_t pin, bool allume) {
  digitalWrite(pin, (allume != RELAIS_ACTIF_BAS) ? HIGH : LOW);
}

// Niveau de "nuit" : 0 = plein jour, 255 = nuit noire
uint8_t niveauNuit(uint32_t now) {
  if (mode == MODE_NUIT || mode == MODE_FETE) return 255;
  if (mode == MODE_ARRET) return 0;

  if (UTILISER_LDR) {
    // LDR entre 5 V et A0, résistance 10 kΩ entre A0 et GND : clair = valeur haute
    static uint16_t lisse = 1023;
    lisse = (lisse * 15 + analogRead(PIN_LDR)) / 16;
    return (uint8_t)constrain(map(lisse, 600, 300, 0, 255), 0, 255);  // à calibrer
  }

  const uint32_t cycle = DUREE_JOUR + DUREE_TRANSITION + DUREE_NUIT + DUREE_TRANSITION;
  uint32_t t = (now - debutCycle) % cycle;
  if (t < DUREE_JOUR) return 0;
  t -= DUREE_JOUR;
  if (t < DUREE_TRANSITION) return (uint8_t)(t * 255UL / DUREE_TRANSITION);
  t -= DUREE_TRANSITION;
  if (t < DUREE_NUIT) return 255;
  t -= DUREE_NUIT;
  return (uint8_t)(255 - t * 255UL / DUREE_TRANSITION);
}

// ------------------------- Lampadaires -------------------------
// Allumage façon vieux lampadaire : quelques crépitements puis montée progressive.
void majLampadaires(uint8_t nuit, uint32_t now) {
  static bool     allumes   = false;
  static uint32_t tAllumage = 0;
  static uint8_t  niveau    = 0;
  static bool     etincelle = false;

  if (!allumes && nuit > 70) {
    allumes   = true;
    tAllumage = now;
    niveau    = LAMPADAIRES_MAX / 3;
  } else if (allumes && nuit < 50) {
    allumes = false;
  }

  uint8_t sortie;
  if (allumes && now - tAllumage < 1800) {
    if (random8() < 60) etincelle = !etincelle;
    sortie = etincelle ? LAMPADAIRES_MAX / 3 : 0;
  } else {
    uint8_t cible = allumes ? LAMPADAIRES_MAX : 0;
    if (niveau < cible) niveau++;
    else if (niveau > cible) niveau--;
    sortie = niveau;
  }
  analogWrite(PIN_LAMPADAIRES, dim8_video(sortie));
}

// ------------------------- Buvette -------------------------
// Lumière chaude qui vacille comme une lanterne.
void majBuvette(uint8_t nuit) {
  static uint8_t niveau = 0;
  if (nuit > 40) {
    uint8_t cible = random8(170, 255);
    niveau = (uint8_t)(((uint16_t)niveau * 3 + cible) / 4);
  } else {
    niveau = qsub8(niveau, 4);
  }
  analogWrite(PIN_BUVETTE, niveau);
}

// ------------------------- Relais -------------------------
void majRelais(uint8_t nuit, uint32_t now) {
  // Sapin : s'allume en pleine nuit (avec hystérésis pour éviter les claquements)
  static bool sapin = false;
  if (mode == MODE_FETE)  sapin = true;
  else if (nuit > 180)    sapin = true;
  else if (nuit < 150)    sapin = false;
  commanderRelais(PIN_RELAIS_SAPIN, sapin);

  // Voitures : "passent" de temps en temps la nuit, clignotent en mode fête
  static bool     voitures   = false;
  static uint32_t prochain   = 0;
  if (mode == MODE_FETE) {
    if ((int32_t)(now - prochain) >= 0) {
      voitures = !voitures;
      prochain = now + 4000;            // pas trop rapide : un relais s'use et claque
    }
  } else if (nuit > 200) {
    if ((int32_t)(now - prochain) >= 0) {
      voitures = !voitures;
      prochain = now + (voitures ? random(20000, 40000) : random(8000, 20000));
    }
  } else {
    voitures = false;
    prochain = now;
  }
  commanderRelais(PIN_RELAIS_VOITURES, voitures);
}

// ------------------------- Cabanes WS2812B -------------------------
void animBougie(CRGB* p, uint32_t now) {            // blanc chaud qui ondule
  for (uint8_t i = 0; i < LEDS_PAR_CABANE; i++) {
    uint8_t v = 150 + scale8(inoise8(i * 120, now / 3), 105);
    p[i] = CRGB(255, 130, 35);
    p[i].nscale8_video(v);
  }
}

void animGuirlande(CRGB* p, uint32_t now) {         // guirlande multicolore qui défile
  static const CRGB couleurs[] = { CRGB::Red, CRGB::Green, CRGB(255, 160, 0), CRGB::Blue };
  for (uint8_t i = 0; i < LEDS_PAR_CABANE; i++) {
    p[i] = couleurs[(i + now / 500) % 4];
  }
}

void animRougeVert(CRGB* p, uint32_t now) {         // rouge et vert qui se fondent
  for (uint8_t i = 0; i < LEDS_PAR_CABANE; i++) {
    p[i] = blend(CRGB::Red, CRGB::Green, sin8(now / 12 + i * 60));
  }
}

void animFete(CRGB* p, uint8_t c, uint32_t now) {   // arc-en-ciel + paillettes
  for (uint8_t i = 0; i < LEDS_PAR_CABANE; i++) {
    p[i] = CHSV(now / 8 + (c * LEDS_PAR_CABANE + i) * 25, 255, 255);
    if (random8() < 6) p[i] = CRGB::White;
  }
}

void majCabanes(uint8_t nuit, uint32_t now) {
  for (uint8_t c = 0; c < NB_CABANES; c++) {
    // Les cabanes s'allument l'une après l'autre pendant le crépuscule
    bool allumee = (mode == MODE_FETE) || nuit > 90 + c * 50;
    lumCabane[c] = allumee ? qadd8(lumCabane[c], 3) : qsub8(lumCabane[c], 3);

    CRGB* p = &leds[c * LEDS_PAR_CABANE];
    if (mode == MODE_FETE) {
      animFete(p, c, now);
    } else {
      switch (c % 3) {
        case 0: animBougie(p, now);    break;
        case 1: animGuirlande(p, now); break;
        case 2: animRougeVert(p, now); break;
      }
    }
    for (uint8_t i = 0; i < LEDS_PAR_CABANE; i++) p[i].nscale8_video(lumCabane[c]);
  }
  FastLED.show();
}

// ------------------------- Bouton -------------------------
void signalerMode() {
  // Petit flash bleu sur la première LED : 1 flash = AUTO, 2 = NUIT, 3 = FÊTE, 4 = ARRÊT
  for (uint8_t n = 0; n <= mode; n++) {
    fill_solid(leds, NB_LEDS, CRGB::Black);
    leds[0] = CRGB::Blue;
    FastLED.show();
    delay(150);
    leds[0] = CRGB::Black;
    FastLED.show();
    delay(150);
  }
  Serial.print(F("Mode : "));
  Serial.println(NOMS_MODES[mode]);
}

void lireBouton(uint32_t now) {
  static bool     etatPrec = HIGH;
  static uint32_t tChange  = 0;
  bool lecture = digitalRead(PIN_BOUTON);
  if (lecture != etatPrec && now - tChange > 40) {   // anti-rebond
    tChange  = now;
    etatPrec = lecture;
    if (lecture == LOW) {
      mode = (Mode)((mode + 1) % NB_MODES);
      if (mode == MODE_AUTO) debutCycle = millis() - DUREE_JOUR;  // repart au crépuscule
      signalerMode();
    }
  }
}

// ------------------------- Programme principal -------------------------
void setup() {
  // Relais au repos AVANT de passer les broches en sortie (évite un claquement au démarrage)
  commanderRelais(PIN_RELAIS_SAPIN, false);
  commanderRelais(PIN_RELAIS_VOITURES, false);
  pinMode(PIN_RELAIS_SAPIN, OUTPUT);
  pinMode(PIN_RELAIS_VOITURES, OUTPUT);

  pinMode(PIN_LAMPADAIRES, OUTPUT);
  pinMode(PIN_BUVETTE, OUTPUT);
  pinMode(PIN_BOUTON, INPUT_PULLUP);

  Serial.begin(115200);
  randomSeed(analogRead(A7));

  FastLED.addLeds<WS2812B, PIN_WS2812, GRB>(leds, NB_LEDS);
  FastLED.setBrightness(LUMINOSITE_WS);
  FastLED.setMaxPowerInVoltsAndMilliamps(5, COURANT_MAX_WS);
  FastLED.clear(true);

  debutCycle = millis() - DUREE_JOUR;   // on démarre directement au crépuscule : effet garanti !
  Serial.println(F("Village de Noel pret - Mode : AUTO"));
}

void loop() {
  uint32_t now = millis();
  lireBouton(now);

  static uint32_t derniereImage = 0;
  if (now - derniereImage < 20) return;  // ~50 images / seconde
  derniereImage = now;

  uint8_t nuit = niveauNuit(now);
  majLampadaires(nuit, now);
  majBuvette(nuit);
  majRelais(nuit, now);
  majCabanes(nuit, now);
}
