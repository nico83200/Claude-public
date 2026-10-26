#include "capteur_couleur.h"

#include <Arduino.h>
#include <Wire.h>

#include <cmath>

#include "broches.h"

namespace {

constexpr uint8_t ADRESSE = 0x29;
constexpr uint8_t COMMANDE = 0x80, AUTO_INCREMENT = 0x20;
constexpr uint8_t ENABLE = 0x00, ATIME = 0x01, CONTROL = 0x0f, ID = 0x12, CDATA = 0x14;
constexpr uint32_t PERIODE = 100;  // ms entre deux mesures
constexpr int CONFIRMATIONS = 3;   // mesures identiques de suite avant d'annoncer

bool ecrire(uint8_t registre, uint8_t valeur) {
  Wire.beginTransmission(ADRESSE);
  Wire.write(COMMANDE | registre);
  Wire.write(valeur);
  return Wire.endTransmission() == 0;
}

}  // namespace

void CapteurCouleur::demarrer() {
  Wire.beginTransmission(ADRESSE);
  Wire.write(COMMANDE | ID);
  if (Wire.endTransmission() != 0 || Wire.requestFrom(ADRESSE, static_cast<uint8_t>(1)) != 1) {
    Serial.println("Capteur de couleur : absent");
    return;
  }
  uint8_t id = Wire.read();
  present_ = id == 0x44 || id == 0x4d || id == 0x10;  // TCS34725, TCS34727, TCS34721
  if (!present_) return;
  ecrire(ATIME, 0xeb);    // intégration de 50 ms
  ecrire(CONTROL, 0x01);  // gain × 4
  ecrire(ENABLE, 0x01);   // sous tension…
  delay(3);
  ecrire(ENABLE, 0x03);   // … et mesure en continu
  Serial.printf("Capteur de couleur : présent (0x%02X)\n", id);
}

bool CapteurCouleur::lire(uint16_t& c, uint16_t& r, uint16_t& v, uint16_t& b) {
  Wire.beginTransmission(ADRESSE);
  Wire.write(COMMANDE | AUTO_INCREMENT | CDATA);
  if (Wire.endTransmission() != 0 || Wire.requestFrom(ADRESSE, static_cast<uint8_t>(8)) != 8) return false;
  uint16_t m[4];
  for (uint16_t& x : m) {
    x = Wire.read();
    x |= Wire.read() << 8;
  }
  c = m[0];
  r = m[1];
  v = m[2];
  b = m[3];
  return true;
}

// Nomme la couleur d'après sa teinte ; les couleurs ternes deviennent blanc ou noir.
std::string CapteurCouleur::nommer(uint16_t c, uint16_t r, uint16_t v, uint16_t b) const {
  if (c < aVide_ * broches::CAPTEUR_PLUS_SOMBRE) return "noir";  // un objet sombre masque la lumière
  float R = r * broches::CAPTEUR_BALANCE[0], V = v * broches::CAPTEUR_BALANCE[1], B = b * broches::CAPTEUR_BALANCE[2];
  float max = std::max(R, std::max(V, B)), min = std::min(R, std::min(V, B));
  if (max <= 0 || (max - min) / max < 0.18f) return "blanc";
  float teinte;
  if (max == R) teinte = 60 * std::fmod((V - B) / (max - min), 6.0f);
  else if (max == V) teinte = 60 * ((B - R) / (max - min) + 2);
  else teinte = 60 * ((R - V) / (max - min) + 4);
  if (teinte < 0) teinte += 360;
  if (teinte < 15 || teinte >= 340) return "rouge";
  if (teinte < 40) return "orange";
  if (teinte < 70) return "jaune";
  if (teinte < 165) return "vert";
  if (teinte < 200) return "cyan";
  if (teinte < 255) return "bleu";
  if (teinte < 300) return "violet";
  return "rose";
}

void CapteurCouleur::maj(const Rappel& rappel) {
  uint32_t t = millis();
  if (!present_ || t < prochain_) return;
  prochain_ = t + PERIODE;
  uint16_t c, r, v, b;
  if (!lire(c, r, v, b)) return;
  if (aVide_ <= 0) aVide_ = c;  // la première mesure : à vide, par hypothèse
  bool pose = c > aVide_ * broches::CAPTEUR_PLUS_CLAIR || c < aVide_ * broches::CAPTEUR_PLUS_SOMBRE;
  std::string nom = pose ? nommer(c, r, v, b) : "";
  if (!pose) aVide_ = aVide_ * 0.98f + c * 0.02f;  // suit la lumière de la pièce
  if (nom != lu_) {
    lu_ = nom;
    confirmations_ = 0;
    if (pose) Serial.printf("Capteur : C %u R %u V %u B %u (à vide %.0f) → %s\n", c, r, v, b, aVide_, nom.c_str());
  }
  if (++confirmations_ >= CONFIRMATIONS && lu_ != stable_) {
    stable_ = lu_;
    rappel(stable_);
  }
}
