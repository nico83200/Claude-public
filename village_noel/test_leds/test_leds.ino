/*
 * TEST DES WS2812B - Marché de Noël
 * Programme minimal : fait passer les 4 LED (branchées sur D6) par
 * rouge, vert, bleu, blanc, puis les allume une par une.
 * Aucune télécommande, aucun relais : uniquement les LED.
 * Ouvrez le moniteur série à 115200 bauds pour suivre le test.
 */
#include <FastLED.h>

const uint8_t PIN_WS2812 = 6;
const uint8_t NB_LEDS    = 4;
CRGB leds[NB_LEDS];

void tout(CRGB c, const __FlashStringHelper* nom) {
  fill_solid(leds, NB_LEDS, c);
  FastLED.show();
  Serial.println(nom);
  delay(1500);
}

void setup() {
  Serial.begin(115200);
  Serial.println(F("Test WS2812B sur D6"));
  FastLED.addLeds<WS2812B, PIN_WS2812, GRB>(leds, NB_LEDS);
  FastLED.setBrightness(80);
}

void loop() {
  tout(CRGB::Red,   F("Toutes ROUGES"));
  tout(CRGB::Green, F("Toutes VERTES"));
  tout(CRGB::Blue,  F("Toutes BLEUES"));
  tout(CRGB::White, F("Toutes BLANCHES"));
  tout(CRGB::Black, F("Toutes ETEINTES"));
  for (uint8_t i = 0; i < NB_LEDS; i++) {
    fill_solid(leds, NB_LEDS, CRGB::Black);
    leds[i] = CRGB::Orange;
    FastLED.show();
    Serial.print(F("LED ")); Serial.print(i + 1); Serial.println(F(" seule en orange"));
    delay(1500);
  }
}
