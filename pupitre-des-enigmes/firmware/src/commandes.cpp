#include "commandes.h"

#include <Arduino.h>
#include <Wire.h>

#include "broches.h"

namespace {

constexpr uint32_t REBOND = 25;
constexpr uint32_t BALAYAGE_CLAVIER = 10;  // ms entre deux lectures du clavier

volatile int32_t position = 0;
volatile uint8_t etatMolette = 0;

// Décodage en quadrature : chaque front valide fait avancer ou reculer d'un pas.
void IRAM_ATTR surMolette() {
  static const int8_t table[16] = {0, -1, 1, 0, 1, 0, 0, -1, -1, 0, 0, 1, 0, 1, -1, 0};
  uint8_t ab = (digitalRead(broches::MOLETTE_CLK) << 1) | digitalRead(broches::MOLETTE_DT);
  etatMolette = ((etatMolette << 2) | ab) & 0x0f;
  position += table[etatMolette];
}

// PCF8574 : écrire 1 sur une broche en fait une entrée (tirée vers le haut) ; écrire 0 la met à la masse.
bool ecrire(uint8_t adresse, uint8_t octet) {
  Wire.beginTransmission(adresse);
  Wire.write(octet);
  return Wire.endTransmission() == 0;
}

int lire(uint8_t adresse) {
  if (Wire.requestFrom(adresse, static_cast<uint8_t>(1)) != 1) return -1;
  return Wire.read();
}

// Un PCF8574 répond à son adresse, un PCF8574A à la même + 0x18.
uint8_t trouver(uint8_t adresse) {
  for (uint8_t a : {adresse, static_cast<uint8_t>(adresse + 0x18)})
    if (ecrire(a, 0xff)) return a;
  return 0;
}

}  // namespace

void Commandes::demarrer() {
  pinMode(broches::GROS_BOUTON, INPUT);  // GPIO 39 : tirage externe
  pinMode(broches::CLE, INPUT_PULLUP);
  Wire.begin(broches::I2C_SDA, broches::I2C_SCL, 400000);
  adresseBoutons_ = trouver(broches::ADRESSE_BOUTONS);
  adresseClavier_ = trouver(broches::ADRESSE_CLAVIER);
  Serial.printf("Boutons de couleur (I2C) : %s · clavier 4x4 (I2C) : %s\n",
                adresseBoutons_ ? "présents" : "ABSENTS", adresseClavier_ ? "présent" : "absent");
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

// Balaye le clavier : une ligne à la masse à la fois, et l'on regarde quelle colonne la suit.
char Commandes::lireClavier() {
  for (int ligne = 0; ligne < 4; ligne++) {
    if (!ecrire(adresseClavier_, static_cast<uint8_t>(~(1u << ligne)))) return 0;
    int v = lire(adresseClavier_);
    if (v < 0) return 0;
    for (int col = 0; col < 4; col++)
      if (!(v & (1 << (4 + col)))) {
        ecrire(adresseClavier_, 0xff);
        return broches::TOUCHES[ligne][col];
      }
  }
  ecrire(adresseClavier_, 0xff);
  return 0;
}

void Commandes::maj(const Rappels& r) {
  uint32_t t = millis();
  uint8_t masque = 0;
  int pcf = adresseBoutons_ ? lire(adresseBoutons_) : -1;
  for (int i = 0; i < 5; i++) {
    bool enfonce = i == 4 ? digitalRead(broches::GROS_BOUTON) == LOW
                          : pcf >= 0 && !(pcf & (1 << broches::BOUTONS_PCF[i]));
    if (filtrer(boutons_[i], enfonce, t)) r.bouton(i);
    if (boutons_[i].stable && i < 4) masque |= 1 << i;
  }
  if (masque != masque_) {
    masque_ = masque;
    r.enfonces(masque);
  }
  if (pcf >= 0) {  // le tiroir : le contact est à la masse quand il est au fond
    filtrer(tiroir_, !(pcf & (1 << broches::TIROIR_PCF)), t);
    if (!tiroirLu_ || tiroir_.stable != tiroirAnnonce_) {
      if (tiroirLu_ || t > 200) {  // au démarrage, le temps que l'anti-rebond s'établisse
        tiroirLu_ = true;
        tiroirAnnonce_ = tiroir_.stable;
        r.tiroir(tiroirAnnonce_);
      }
    }
  }
  if (filtrer(sw_, digitalRead(broches::MOLETTE_SW) == LOW, t)) r.appui();
  filtrer(cle_, digitalRead(broches::CLE) == LOW, t);
  if (!cleLue_ || cle_.stable != cleAnnoncee_) {  // la position de la clé, et chacun de ses changements
    cleLue_ = true;
    cleAnnoncee_ = cle_.stable;
    r.cle(cleAnnoncee_);
  }

  if (adresseClavier_ && t - dernierBalayage_ >= BALAYAGE_CLAVIER) {
    dernierBalayage_ = t;
    char k = lireClavier();
    if (k != toucheLue_) {
      toucheLue_ = k;
      toucheDepuis_ = t;
    }
    if (toucheStable_ != toucheLue_ && t - toucheDepuis_ >= REBOND) {
      toucheStable_ = toucheLue_;
      if (toucheStable_) {
        Serial.printf("Clavier : %c\n", toucheStable_);
        derniereTouche_ = toucheStable_;
        r.touche(toucheStable_);
      }
    }
  }

  noInterrupts();
  int32_t p = position;
  interrupts();
  int32_t crans = (p - dernierePosition_) / reglages::TRANSITIONS_PAR_CRAN;
  if (crans != 0) {
    dernierePosition_ += crans * reglages::TRANSITIONS_PAR_CRAN;
    r.molette(static_cast<int>(crans));
  }
}
