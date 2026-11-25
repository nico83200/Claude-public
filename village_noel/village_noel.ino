/*
 * Marché de Noël miniature - Arduino Nano
 * ---------------------------------------------------------------
 * Au démarrage, les éléments s'allument UN PAR UN, avec une pause entre
 * chacun, puis tout reste allumé. Aucun bouton, pas de mode jour.
 *
 * Les 4 LED WS2812B sont des bougies :
 *   - 3 cabanes : flamme de bougie dont la couleur change TRÈS lentement
 *     (fondus de plusieurs secondes, jamais de changement brutal).
 *     De temps en temps, le marché « ferme » : les cabanes s'éteignent
 *     doucement l'une après l'autre, restent éteintes un moment, puis
 *     se rallument l'une après l'autre.
 *   - 1 église : bougie blanc chaud qui s'éteint et se rallume au hasard.
 *
 * Matériel :
 *   - 8 LED 3 V des lampadaires          -> relais 2 sur D9 (5 V + 1 résistance 150 Ω par LED)
 *   - 3 cabanes + 1 église (WS2812B)     -> D6 (via 330 Ω), dans cet ordre sur la chaîne
 *   - Buvette (LED jaune 5 mm)           -> D5 via 220 Ω (lumière de lanterne qui vacille)
 *   - 4 voitures 12 V                    -> relais 1 sur D8
 *   - Sapin 12 V                         -> relais 3 sur D7
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

// ------------------------- Réglages généraux -------------------------
const bool     RELAIS_ACTIF_BAS = true;     // la plupart des modules relais s'activent avec LOW
const uint8_t  LUMINOSITE_WS    = 160;      // 0..255
const uint16_t COURANT_MAX_WS   = 500;      // mA, limite de sécurité pour le convertisseur 5 V

// Séquence de démarrage
const uint32_t PAUSE_DEPART     = 2000;     // ms avant le premier élément
const uint32_t PAUSE_ELEMENTS   = 3000;     // ms entre deux éléments

// Couleur des cabanes : durée d'un fondu et temps passé sur chaque couleur
const uint16_t DUREE_FONDU_COULEUR = 8000;  // ms pour passer d'une couleur à l'autre
const uint16_t TENUE_COULEUR_MIN   = 10000; // ms
const uint16_t TENUE_COULEUR_MAX   = 25000; // ms

// Fin du marché (les 3 cabanes)
const uint32_t DUREE_MARCHE_OUVERT = 10UL * 60UL * 1000UL;  // 10 min ouvert
const uint32_t DUREE_MARCHE_FERME  = 60UL * 1000UL;         // 1 min fermé
const uint32_t DECALAGE_CABANES    = 4000;                  // ms entre deux cabanes qui ferment / ouvrent

// Église : la bougie s'éteint et se rallume au hasard
const uint32_t EGLISE_ALLUMEE_MIN = 20000;  // ms
const uint32_t EGLISE_ALLUMEE_MAX = 90000;
const uint32_t EGLISE_ETEINTE_MIN = 4000;
const uint32_t EGLISE_ETEINTE_MAX = 20000;

// Ordre d'allumage au démarrage : modifiez simplement l'ordre des lignes.
enum Element : uint8_t {
  LAMPADAIRES, CABANE_1, CABANE_2, CABANE_3, EGLISE, BUVETTE, VOITURES, SAPIN, NB_ELEMENTS
};
const Element ORDRE[] = {
  LAMPADAIRES,
  CABANE_1,
  CABANE_2,
  CABANE_3,
  EGLISE,
  BUVETTE,
  VOITURES,
  SAPIN,
};
const uint8_t NB_ETAPES = sizeof(ORDRE) / sizeof(ORDRE[0]);

// ------------------------- LED -------------------------
const uint8_t NB_CABANES = 3;
const uint8_t LED_EGLISE = 3;              // position de l'église sur la chaîne (0 = 1re LED)
const uint8_t NB_LEDS    = 4;

// Couleurs possibles des cabanes (des tons « bougie » et des couleurs de Noël)
const CRGB PALETTE_CABANES[] = {
  CRGB(255, 120,  30),   // flamme orange
  CRGB(255, 170,  50),   // doré
  CRGB(255,  45,  10),   // rouge
  CRGB( 60, 255,  40),   // vert sapin
  CRGB(255,  60, 120),   // rose
  CRGB(150,  50, 255),   // violet
  CRGB( 60, 110, 255),   // bleu nuit
  CRGB(255, 200, 130),   // blanc chaud
};
const uint8_t NB_COULEURS = sizeof(PALETTE_CABANES) / sizeof(PALETTE_CABANES[0]);

// ------------------------- Variables -------------------------
CRGB     leds[NB_LEDS];
bool     allume[NB_ELEMENTS] = { false };  // éléments déjà allumés par la séquence
uint32_t finSequence = 0;                  // 0 tant que la séquence n'est pas terminée

struct Cabane {
  uint8_t  depart, cible;      // index dans la palette
  uint32_t debutFondu;         // début du fondu en cours
  uint32_t prochainChangement; // quand choisir une nouvelle couleur
  uint8_t  lum;                // luminosité 0..255 (ouverture / fermeture)
};
Cabane cabanes[NB_CABANES];

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
    if (etape == NB_ETAPES) finSequence = now;
  }
}

// ------------------------- Flamme de bougie -------------------------
// Renvoie une luminosité 0..255 qui vacille comme une flamme.
// Chaque LED a sa propre « graine » pour ne pas vaciller en même temps.
uint8_t flamme(uint8_t graine, uint32_t now) {
  uint8_t lent   = inoise8(graine * 97,  now / 6);      // ondulation douce
  uint8_t rapide = inoise8(graine * 151 + 5000, now);   // petits frémissements
  uint8_t v = 140 + scale8(lent, 80) + scale8(rapide, 35);
  return v;                                             // 140..255
}

CRGB couleurBougie(CRGB base, uint8_t graine, uint32_t now) {
  CRGB c = base;
  c.nscale8_video(flamme(graine, now));
  return c;
}

// ------------------------- Cabanes -------------------------
uint8_t couleurAuHasard(uint8_t sauf) {
  uint8_t i;
  do { i = random8(NB_COULEURS); } while (i == sauf);
  return i;
}

void initCabanes() {
  for (uint8_t c = 0; c < NB_CABANES; c++) {
    cabanes[c].depart = cabanes[c].cible = c;          // chaque cabane démarre sur une couleur différente
    cabanes[c].debutFondu = 0;
    cabanes[c].prochainChangement = random16(TENUE_COULEUR_MIN, TENUE_COULEUR_MAX);
    cabanes[c].lum = 0;
  }
}

// La cabane doit-elle être allumée ? (séquence de démarrage puis cycle ouvert / fermé)
bool cabaneOuverte(uint8_t c, uint32_t now) {
  if (finSequence == 0) return allume[CABANE_1 + c];
  // Le cycle démarre comme si toutes les cabanes venaient d'ouvrir
  const uint32_t cycle = DUREE_MARCHE_OUVERT + DUREE_MARCHE_FERME;
  uint32_t t = (now - finSequence + (NB_CABANES - 1) * DECALAGE_CABANES) % cycle;
  return t >= c * DECALAGE_CABANES && t < DUREE_MARCHE_OUVERT + c * DECALAGE_CABANES;
}

void majCabane(uint8_t c, uint32_t now) {
  Cabane& k = cabanes[c];

  // Ouverture / fermeture en fondu (~2,5 s)
  k.lum = cabaneOuverte(c, now) ? qadd8(k.lum, 2) : qsub8(k.lum, 2);

  // Nouvelle couleur cible de temps en temps
  if ((int32_t)(now - k.prochainChangement) >= 0) {
    k.depart = k.cible;
    k.cible = couleurAuHasard(k.cible);
    k.debutFondu = now;
    k.prochainChangement = now + DUREE_FONDU_COULEUR + random16(TENUE_COULEUR_MIN, TENUE_COULEUR_MAX);
  }

  // Fondu progressif entre l'ancienne et la nouvelle couleur
  uint32_t ecoule = now - k.debutFondu;
  uint8_t avancement = ecoule >= DUREE_FONDU_COULEUR ? 255 : (uint8_t)(ecoule * 255UL / DUREE_FONDU_COULEUR);
  avancement = ease8InOutCubic(avancement);              // départ et arrivée tout en douceur
  CRGB base = blend(PALETTE_CABANES[k.depart], PALETTE_CABANES[k.cible], avancement);

  leds[c] = couleurBougie(base, c, now);
  leds[c].nscale8_video(k.lum);
}

// ------------------------- Église -------------------------
void majEglise(uint32_t now) {
  static bool     flammeAllumee = true;
  static uint32_t prochain = 0;
  static uint8_t  lum = 0;

  if (!allume[EGLISE]) { leds[LED_EGLISE] = CRGB::Black; return; }

  if (prochain == 0) prochain = now + random(EGLISE_ALLUMEE_MIN, EGLISE_ALLUMEE_MAX);
  if ((int32_t)(now - prochain) >= 0) {
    flammeAllumee = !flammeAllumee;
    prochain = now + (flammeAllumee ? random(EGLISE_ALLUMEE_MIN, EGLISE_ALLUMEE_MAX)
                                    : random(EGLISE_ETEINTE_MIN, EGLISE_ETEINTE_MAX));
  }

  // S'éteint vite (comme une bougie soufflée), se rallume plus doucement
  lum = flammeAllumee ? qadd8(lum, 3) : qsub8(lum, 8);

  leds[LED_EGLISE] = couleurBougie(CRGB(255, 140, 40), 7, now);
  leds[LED_EGLISE].nscale8_video(lum);
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
  uint16_t graine = analogRead(A7) ^ (analogRead(A6) << 4);
  randomSeed(graine);
  random16_set_seed(graine);

  FastLED.addLeds<WS2812B, PIN_WS2812, GRB>(leds, NB_LEDS);
  FastLED.setBrightness(LUMINOSITE_WS);
  FastLED.setMaxPowerInVoltsAndMilliamps(5, COURANT_MAX_WS);
  FastLED.clear(true);

  initCabanes();
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

  for (uint8_t c = 0; c < NB_CABANES; c++) majCabane(c, now);
  majEglise(now);
  FastLED.show();
}
