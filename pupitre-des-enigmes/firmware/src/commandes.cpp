#include "commandes.h"

#include <Arduino.h>

#include "broches.h"

namespace {

constexpr uint32_t REBOND = 25;

volatile int32_t position = 0;
volatile uint8_t etatMolette = 0;

// Décodage en quadrature : chaque front valide fait avancer ou reculer d'un pas.
void IRAM_ATTR surMolette() {
  static const int8_t table[16] = {0, -1, 1, 0, 1, 0, 0, -1, -1, 0, 0, 1, 0, 1, -1, 0};
  uint8_t ab = (digitalRead(broches::MOLETTE_CLK) << 1) | digitalRead(broches::MOLETTE_DT);
  etatMolette = ((etatMolette << 2) | ab) & 0x0f;
  position += table[etatMolette];
}

}  // namespace

void Commandes::demarrer() {
  for (int i = 0; i < 5; i++) pinMode(broches::BOUTONS[i], i == 4 ? INPUT : INPUT_PULLUP);  // GPIO 39 : tirage externe
  pinMode(broches::MOLETTE_SW, INPUT_PULLUP);
  pinMode(broches::MOLETTE_CLK, INPUT);  // tirages présents sur la carte KY-040
  pinMode(broches::MOLETTE_DT, INPUT);
  attachInterrupt(digitalPinToInterrupt(broches::MOLETTE_CLK), surMolette, CHANGE);
  attachInterrupt(digitalPinToInterrupt(broches::MOLETTE_DT), surMolette, CHANGE);
}

bool Commandes::filtrer(Anti& a, bool brut, uint32_t t) {
  if (brut != a.lu) {
    a.lu = brut;
    a.depuis = t;
  }
  if (a.stable != a.lu && t - a.depuis >= REBOND) {
    a.stable = a.lu;
    return a.stable;  // vrai seulement au moment de l'appui
  }
  return false;
}

void Commandes::maj(const Rappels& r) {
  uint32_t t = millis();
  uint8_t masque = 0;
  for (int i = 0; i < 5; i++) {
    bool enfonce = digitalRead(broches::BOUTONS[i]) == LOW;
    if (filtrer(boutons_[i], enfonce, t)) r.bouton(i);
    if (boutons_[i].stable && i < 4) masque |= 1 << i;
  }
  if (masque != masque_) {
    masque_ = masque;
    r.enfonces(masque);
  }
  if (filtrer(sw_, digitalRead(broches::MOLETTE_SW) == LOW, t)) r.appui();

  noInterrupts();
  int32_t p = position;
  interrupts();
  int32_t crans = (p - dernierePosition_) / reglages::TRANSITIONS_PAR_CRAN;
  if (crans != 0) {
    dernierePosition_ += crans * reglages::TRANSITIONS_PAR_CRAN;
    r.molette(static_cast<int>(crans));
  }
}
