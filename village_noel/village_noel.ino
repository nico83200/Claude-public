/*
 * Marché de Noël miniature - Arduino Nano
 * ---------------------------------------------------------------
 * Au démarrage, les éléments s'allument UN PAR UN, avec une pause entre
 * chacun, puis tout reste allumé. Pas de mode jour.
 *
 * Télécommande infrarouge (kit Elegoo, récepteur KY-022 sur D2) :
 *   ⏻        : tout éteindre / tout rallumer (rallumage un par un)
 *   1 à 8    : allumer / éteindre un élément
 *              1 lampadaires · 2 cabane 1 · 3 cabane 2 · 4 cabane 3
 *              5 église · 6 buvette · 7 voitures · 8 sapin
 *   9        : fermer / rouvrir le marché tout de suite (les 3 cabanes)
 *   0        : rejouer la séquence d'allumage
 *   ⏯ (play) : MODE FÊTE marche / arrêt (tout s'allume, couleurs arc-en-ciel
 *              qui défilent, paillettes, buvette qui pulse, phares qui clignotent)
 *   VOL+/VOL- : luminosité des cabanes, de l'église et de la buvette
 *
 * Les 4 LED WS2812B sont des bougies :
 *   - 3 cabanes : flamme de bougie en bougie, doré ou blanc chaud. La couleur
 *     change au plus toutes les 2 minutes, par un fondu de 10 s.
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
 *   - Récepteur infrarouge KY-022        -> S sur D2, + sur 5V du Nano, - sur GND
 *   - Alimentation 12 V : Nano sur VIN, convertisseur 12 V -> 5 V pour le reste
 *
 * Bibliothèques nécessaires (Gestionnaire de bibliothèques de l'IDE Arduino) :
 *   FastLED  et  IRremote (version 4.x, par Armin Joachimsmeyer)
 */

#define DECODE_NEC            // la télécommande Elegoo utilise le protocole NEC
#include <IRremote.hpp>
#include <FastLED.h>

// ------------------------- Brochage -------------------------
const uint8_t PIN_IR                 = 2;   // récepteur infrarouge
const uint8_t PIN_BUVETTE            = 5;   // PWM
const uint8_t PIN_WS2812             = 6;
const uint8_t PIN_RELAIS_SAPIN       = 7;   // relais 3
const uint8_t PIN_RELAIS_VOITURES    = 8;   // relais 1
const uint8_t PIN_RELAIS_LAMPADAIRES = 9;   // relais 2

// ------------------------- Réglages généraux -------------------------
const bool     RELAIS_ACTIF_BAS = true;     // la plupart des modules relais s'activent avec LOW
const uint8_t  LUMINOSITE_WS    = 160;      // luminosité de départ 0..255 (réglable avec VOL+/VOL-)
const uint8_t  PAS_LUMINOSITE   = 20;
const uint8_t  LUMINOSITE_MIN   = 20;
const uint16_t COURANT_MAX_WS   = 500;      // mA, limite de sécurité pour le convertisseur 5 V

// Séquence de démarrage
const uint32_t PAUSE_DEPART     = 2000;     // ms avant le premier élément (à la mise sous tension)
const uint32_t PAUSE_DEPART_TELECOMMANDE = 500;   // ms avant le premier élément (touche ⏻ ou 0)
const uint32_t PAUSE_ELEMENTS   = 3000;     // ms entre deux éléments

// Couleur des cabanes : durée d'un fondu et temps passé sur chaque couleur
const uint32_t DUREE_FONDU_COULEUR = 10000UL;        // ms pour passer d'une couleur à l'autre
const uint32_t TENUE_COULEUR_MIN   = 2UL * 60UL * 1000UL;  // au moins 2 min sur chaque couleur
const uint32_t TENUE_COULEUR_MAX   = 4UL * 60UL * 1000UL;  // au plus 4 min

// Fin du marché (les 3 cabanes)
const uint32_t DUREE_MARCHE_OUVERT = 10UL * 60UL * 1000UL;  // 10 min ouvert
const uint32_t DUREE_MARCHE_FERME  = 60UL * 1000UL;         // 1 min fermé
const uint32_t DECALAGE_CABANES    = 4000;                  // ms entre deux cabanes qui ferment / ouvrent

// Mode fête (touche ⏯)
const uint32_t DUREE_FETE_MAX     = 5UL * 60UL * 1000UL;  // retour automatique au mode normal après 5 min
const uint32_t CLIGNO_VOITURES    = 2000;                 // ms : phares allumés / éteints (pas trop vite pour les relais)
const uint8_t  VITESSE_ARC_EN_CIEL = 12;                  // plus petit = défile plus vite

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

// Codes des touches de la télécommande Elegoo (protocole NEC, adresse 0x00).
// Si une touche ne réagit pas, ouvrez le moniteur série (115200 bauds) :
// le code de chaque touche reçue y est affiché.
const uint8_t TOUCHE_POWER = 0x45;
const uint8_t TOUCHE_VOLP  = 0x46;
const uint8_t TOUCHE_VOLM  = 0x15;
const uint8_t TOUCHE_0     = 0x16;
const uint8_t TOUCHE_PLAY  = 0x40;
const uint8_t TOUCHES_1_A_9[9] = { 0x0C, 0x18, 0x5E, 0x08, 0x1C, 0x5A, 0x42, 0x52, 0x4A };

// ------------------------- LED -------------------------
const uint8_t NB_CABANES = 3;
const uint8_t LED_EGLISE = 3;              // position de l'église sur la chaîne (0 = 1re LED)
const uint8_t NB_LEDS    = 4;

// Couleurs possibles des cabanes : bougie, doré, blanc
const CRGB PALETTE_CABANES[] = {
  CRGB(255, 120,  30),   // bougie (flamme orange)
  CRGB(255, 170,  50),   // doré
  CRGB(255, 210, 150),   // blanc chaud
};
const uint8_t NB_COULEURS = sizeof(PALETTE_CABANES) / sizeof(PALETTE_CABANES[0]);

// ------------------------- Variables -------------------------
CRGB     leds[NB_LEDS];
bool     allume[NB_ELEMENTS] = { false };  // état de chaque élément (séquence ou télécommande)
uint8_t  etape = 0;                        // étape en cours de la séquence d'allumage
uint32_t debutSequence = 0;
uint32_t pauseDepart = PAUSE_DEPART;
uint8_t  luminosite = LUMINOSITE_WS;

bool     fete = false;                     // mode fête en cours ?
uint8_t  mixFete = 0;                      // 0 = normal, 255 = fête (fondu entre les deux)
uint32_t debutFete = 0;
bool     avantFete[NB_ELEMENTS];           // état des éléments avant la fête, restauré ensuite

bool     marcheOuvert = true;              // fin du marché : fermeture / réouverture des cabanes
uint32_t basculeMarche = 0;                // moment de la dernière ouverture ou fermeture

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
  if (etape >= NB_ETAPES) return;
  if (now - debutSequence >= pauseDepart + (uint32_t)etape * PAUSE_ELEMENTS) {
    allume[ORDRE[etape]] = true;
    Serial.print(F("Allumage etape "));
    Serial.println(etape + 1);
    etape++;
  }
}

void relancerSequence(uint32_t now) {
  for (uint8_t i = 0; i < NB_ELEMENTS; i++) allume[i] = false;
  etape = 0;
  debutSequence = now;
  pauseDepart = PAUSE_DEPART_TELECOMMANDE;
  marcheOuvert = true;
  basculeMarche = now;
}

void toutEteindre() {
  etape = NB_ETAPES;                        // arrête une séquence en cours
  for (uint8_t i = 0; i < NB_ELEMENTS; i++) allume[i] = false;
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
    cabanes[c].depart = cabanes[c].cible = c % NB_COULEURS;  // chaque cabane démarre sur une couleur différente
    cabanes[c].debutFondu = 0;
    cabanes[c].prochainChangement = random(TENUE_COULEUR_MIN, TENUE_COULEUR_MAX);
    cabanes[c].lum = 0;
  }
}

// Ouverture / fermeture automatique du marché
void majMarche(uint32_t now) {
  uint32_t duree = marcheOuvert ? DUREE_MARCHE_OUVERT : DUREE_MARCHE_FERME;
  if (now - basculeMarche >= duree) {
    marcheOuvert = !marcheOuvert;
    basculeMarche = now;
  }
}

// La cabane doit-elle être allumée ? Les cabanes ferment et rouvrent l'une après l'autre.
bool cabaneOuverte(uint8_t c, uint32_t now) {
  if (!allume[CABANE_1 + c]) return false;
  bool apresDecalage = (now - basculeMarche) >= (uint32_t)c * DECALAGE_CABANES;
  return marcheOuvert ? apresDecalage : !apresDecalage;
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
    k.prochainChangement = now + DUREE_FONDU_COULEUR + random(TENUE_COULEUR_MIN, TENUE_COULEUR_MAX);
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

  if (prochain == 0) prochain = now + random(EGLISE_ALLUMEE_MIN, EGLISE_ALLUMEE_MAX);
  if ((int32_t)(now - prochain) >= 0) {
    flammeAllumee = !flammeAllumee;
    prochain = now + (flammeAllumee ? random(EGLISE_ALLUMEE_MIN, EGLISE_ALLUMEE_MAX)
                                    : random(EGLISE_ETEINTE_MIN, EGLISE_ETEINTE_MAX));
  }

  // S'éteint vite (comme une bougie soufflée), se rallume plus doucement
  lum = (flammeAllumee && allume[EGLISE]) ? qadd8(lum, 3) : qsub8(lum, 8);

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
  } else {
    niveau = qsub8(niveau, 6);              // s'éteint en douceur
  }
  uint8_t sortie = niveau;
  if (mixFete && allume[BUVETTE]) {
    uint8_t pulse = beatsin8(100, 30, 255);   // pulsation rapide pendant la fête
    sortie = lerp8by8(niveau, pulse, mixFete);
  }
  analogWrite(PIN_BUVETTE, scale8(sortie, qadd8(luminosite, 255 - LUMINOSITE_WS)));
}

// ------------------------- Mode fête -------------------------
void demarrerFete(uint32_t now) {
  for (uint8_t i = 0; i < NB_ELEMENTS; i++) { avantFete[i] = allume[i]; allume[i] = true; }
  etape = NB_ETAPES;                        // arrête une séquence en cours
  fete = true;
  debutFete = now;
  Serial.println(F("Mode fete !"));
}

void arreterFete() {
  if (!fete) return;
  for (uint8_t i = 0; i < NB_ELEMENTS; i++) allume[i] = avantFete[i];
  fete = false;
  Serial.println(F("Fin de la fete"));
}

// Couleurs arc-en-ciel qui défilent en douceur, avec quelques paillettes,
// mélangées aux bougies selon mixFete (fondu d'entrée et de sortie).
void appliquerFete(uint32_t now) {
  if (mixFete == 0) return;
  for (uint8_t i = 0; i < NB_LEDS; i++) {
    Element e = (i == LED_EGLISE) ? EGLISE : (Element)(CABANE_1 + i);
    if (!allume[e]) continue;
    CRGB c = CHSV(now / VITESSE_ARC_EN_CIEL + i * 64, 230, 255);
    if (random8() < 5) c = CRGB::White;     // paillette
    leds[i] = blend(leds[i], c, mixFete);
  }
}

// ------------------------- Télécommande -------------------------
void basculer(Element e) {
  etape = NB_ETAPES;                        // la télécommande prend la main sur la séquence
  allume[e] = !allume[e];
}

void lireTelecommande(uint32_t now) {
  if (!IrReceiver.decode()) return;
  IRData& d = IrReceiver.decodedIRData;
  bool repetition = d.flags & IRDATA_FLAGS_IS_REPEAT;

  if (d.protocol == NEC) {
    uint8_t code = d.command;
    Serial.print(F("Touche recue : 0x"));
    Serial.println(code, HEX);

    if (code == TOUCHE_VOLP) {                        // VOL+ / VOL- : maintien possible
      luminosite = qadd8(luminosite, PAS_LUMINOSITE);
    } else if (code == TOUCHE_VOLM) {
      luminosite = max((int)LUMINOSITE_MIN, (int)luminosite - PAS_LUMINOSITE);
    } else if (!repetition) {                          // les autres touches : un appui = une action
      if (code == TOUCHE_PLAY) {
        if (fete) arreterFete(); else demarrerFete(now);
      } else if (code == TOUCHE_POWER) {
        arreterFete();
        bool unAllume = false;
        for (uint8_t i = 0; i < NB_ELEMENTS; i++) unAllume |= allume[i];
        if (unAllume) toutEteindre(); else relancerSequence(now);
      } else if (code == TOUCHE_0) {
        arreterFete();
        relancerSequence(now);
      } else if (code == TOUCHES_1_A_9[8]) {           // 9 : fermer / rouvrir le marché
        marcheOuvert = !marcheOuvert;
        basculeMarche = now;
      } else {
        for (uint8_t i = 0; i < 8; i++) {              // 1 à 8 : un élément chacun
          if (code == TOUCHES_1_A_9[i]) basculer((Element)i);
        }
      }
    }
  }
  IrReceiver.resume();
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

  IrReceiver.begin(PIN_IR, DISABLE_LED_FEEDBACK);
  pinMode(PIN_IR, INPUT_PULLUP);           // broche au repos (HIGH) même si le récepteur est débranché

  initCabanes();
  Serial.println(F("Marche de Noel : demarrage"));
}

void loop() {
  uint32_t now = millis();
  lireTelecommande(now);

  static uint32_t derniereImage = 0;
  if (now - derniereImage < 20) return;  // ~50 images / seconde
  derniereImage = now;

  majSequence(now);
  majMarche(now);
  if (fete && now - debutFete >= DUREE_FETE_MAX) arreterFete();
  mixFete = fete ? qadd8(mixFete, 4) : qsub8(mixFete, 4);   // fondu d'environ 1,3 s

  bool phares = allume[VOITURES];
  if (fete) phares = phares && ((now - debutFete) / CLIGNO_VOITURES) % 2 == 0;
  commanderRelais(PIN_RELAIS_LAMPADAIRES, allume[LAMPADAIRES]);
  commanderRelais(PIN_RELAIS_VOITURES,    phares);
  commanderRelais(PIN_RELAIS_SAPIN,       allume[SAPIN]);
  majBuvette();

  for (uint8_t c = 0; c < NB_CABANES; c++) majCabane(c, now);
  majEglise(now);
  appliquerFete(now);
  FastLED.setBrightness(luminosite);
  // Envoyer les couleurs aux LED bloque les interruptions un court instant :
  // on évite de le faire pendant la réception d'une touche, MAIS on ne bloque
  // jamais les LED plus de 100 ms (récepteur absent, débranché ou parasité).
  static uint32_t dernierEnvoi = 0;
  if (IrReceiver.isIdle() || now - dernierEnvoi >= 100) {
    FastLED.show();
    dernierEnvoi = now;
  }
}
