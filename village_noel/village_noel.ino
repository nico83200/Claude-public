/*
 * Marché de Noël miniature - Arduino Nano
 * ---------------------------------------------------------------
 * Au démarrage, les éléments s'allument UN PAR UN, avec une pause entre
 * chacun, puis tout reste allumé (les LED continuent leurs animations).
 * Aucun bouton : il suffit de brancher l'alimentation.
 *
 * Matériel :
 *   - 8 LED 3 V des lampadaires         -> relais 2 sur D9 (5 V + 1 résistance 150 Ω par LED)
 *   - 4 cabanes WS2812B (1 LED chacune) -> D6 (une animation différente par cabane)
 *   - Buvette (LED 5 V)                 -> D5 (PWM : lumière de lanterne qui vacille)
 *   - 4 voitures 12 V                   -> relais 1 sur D8
 *   - Sapin 12 V                        -> relais 3 sur D7
 *   - Alimentation 12 V : Nano sur VIN, convertisseur 12 V -> 5 V pour le reste
 *
 * Bibliothèque nécessaire : FastLED (Gestionnaire de bibliothèques de l'IDE Arduino)
 */

#include <FastLED.h>

// ------------------------- Brochage -------------------------
const uint8_t PIN_BUVETTE            = 5;   // PWM
const uint8_t PIN_WS2812             = 6;
const uint8_t PIN_RELAIS_SAPIN       = 7;   // relais 3
const uint8_t PIN_RELAIS_VOITURES    = 8;   // relais 1
const uint8_t PIN_RELAIS_LAMPADAIRES = 9;   // relais 2

// ------------------------- Réglages -------------------------
const bool     RELAIS_ACTIF_BAS = true;     // la plupart des modules relais s'activent avec LOW

const uint32_t PAUSE_DEPART     = 2000;     // ms avant le premier élément
const uint32_t PAUSE_ELEMENTS   = 3000;     // ms entre deux éléments  <-- à régler

const uint8_t  NB_CABANES       = 4;
const uint8_t  LEDS_PAR_CABANE  = 1;        // nombre de WS2812B par cabane
const uint8_t  NB_LEDS          = NB_CABANES * LEDS_PAR_CABANE;
const uint8_t  LUMINOSITE_WS    = 140;      // 0..255
const uint16_t COURANT_MAX_WS   = 500;      // mA, limite de sécurité pour le convertisseur 5 V

// Ordre d'allumage : modifiez simplement l'ordre des lignes de ce tableau.
enum Element : uint8_t {
  LAMPADAIRES, CABANE_1, CABANE_2, CABANE_3, CABANE_4, BUVETTE, VOITURES, SAPIN
};
const Element ORDRE[] = {
  LAMPADAIRES,
  CABANE_1,
  CABANE_2,
  CABANE_3,
  CABANE_4,
  BUVETTE,
  VOITURES,
  SAPIN,
};
const uint8_t NB_ETAPES = sizeof(ORDRE) / sizeof(ORDRE[0]);

// ------------------------- Variables -------------------------
CRGB    leds[NB_LEDS];
uint8_t lumCabane[NB_CABANES] = { 0 };   // luminosité courante (fondu à l'allumage)
bool    allume[8]             = { false };

// ------------------------- Relais -------------------------
void commanderRelais(uint8_t pin, bool on) {
  digitalWrite(pin, (on != RELAIS_ACTIF_BAS) ? HIGH : LOW);
}

// ------------------------- Séquence de démarrage -------------------------
void majSequence(uint32_t now) {
  static uint8_t etape = 0;
  if (etape >= NB_ETAPES) return;
  if (now >= PAUSE_DEPART + (uint32_t)etape * PAUSE_ELEMENTS) {
    allume[ORDRE[etape]] = true;
    Serial.print(F("Allumage etape "));
    Serial.println(etape + 1);
    etape++;
  }
}

// ------------------------- Buvette -------------------------
// Lumière chaude qui vacille comme une lanterne (monte en douceur à l'allumage).
void majBuvette() {
  static uint8_t niveau = 0;
  if (allume[BUVETTE]) {
    uint8_t cible = random8(170, 255);
    niveau = (uint8_t)(((uint16_t)niveau * 7 + cible) / 8);
  }
  analogWrite(PIN_BUVETTE, niveau);
}

// ------------------------- Cabanes WS2812B -------------------------
void animBougie(CRGB* p, uint32_t now) {            // blanc chaud qui ondule
  for (uint8_t i = 0; i < LEDS_PAR_CABANE; i++) {
    uint8_t v = 150 + scale8(inoise8(i * 120, now / 3), 105);
    p[i] = CRGB(255, 130, 35);
    p[i].nscale8_video(v);
  }
}

void animGuirlande(CRGB* p, uint32_t now) {         // couleurs qui défilent
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

void animDore(CRGB* p, uint32_t now) {              // doré qui respire doucement
  for (uint8_t i = 0; i < LEDS_PAR_CABANE; i++) {
    p[i] = CRGB(255, 170, 30);
    p[i].nscale8_video(120 + scale8(sin8(now / 16 + i * 50), 135));
  }
}

void majCabanes(uint32_t now) {
  for (uint8_t c = 0; c < NB_CABANES; c++) {
    bool on = allume[CABANE_1 + (c % 4)];
    if (on) lumCabane[c] = qadd8(lumCabane[c], 4);   // fondu d'environ 1,3 s

    CRGB* p = &leds[c * LEDS_PAR_CABANE];
    switch (c % 4) {
      case 0: animBougie(p, now);    break;
      case 1: animGuirlande(p, now); break;
      case 2: animRougeVert(p, now); break;
      case 3: animDore(p, now);      break;
    }
    for (uint8_t i = 0; i < LEDS_PAR_CABANE; i++) p[i].nscale8_video(lumCabane[c]);
  }
  FastLED.show();
}

// ------------------------- Programme principal -------------------------
void setup() {
  // Relais au repos AVANT de passer les broches en sortie (évite un claquement au démarrage)
  commanderRelais(PIN_RELAIS_SAPIN, false);
  commanderRelais(PIN_RELAIS_VOITURES, false);
  commanderRelais(PIN_RELAIS_LAMPADAIRES, false);
  pinMode(PIN_RELAIS_SAPIN, OUTPUT);
  pinMode(PIN_RELAIS_VOITURES, OUTPUT);
  pinMode(PIN_RELAIS_LAMPADAIRES, OUTPUT);
  pinMode(PIN_BUVETTE, OUTPUT);
  analogWrite(PIN_BUVETTE, 0);

  Serial.begin(115200);
  randomSeed(analogRead(A7));

  FastLED.addLeds<WS2812B, PIN_WS2812, GRB>(leds, NB_LEDS);
  FastLED.setBrightness(LUMINOSITE_WS);
  FastLED.setMaxPowerInVoltsAndMilliamps(5, COURANT_MAX_WS);
  FastLED.clear(true);

  Serial.println(F("Marche de Noel : demarrage"));
}

void loop() {
  uint32_t now = millis();

  static uint32_t derniereImage = 0;
  if (now - derniereImage < 20) return;  // ~50 images / seconde
  derniereImage = now;

  majSequence(now);
  commanderRelais(PIN_RELAIS_LAMPADAIRES, allume[LAMPADAIRES]);
  commanderRelais(PIN_RELAIS_VOITURES,    allume[VOITURES]);
  commanderRelais(PIN_RELAIS_SAPIN,       allume[SAPIN]);
  majBuvette();
  majCabanes(now);
}
