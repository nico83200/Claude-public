#include "capteurs_i2c.h"

#include <Arduino.h>
#include <Wire.h>

#include <cmath>
#include <cstdlib>

#include "broches.h"

namespace {

bool ecrire(uint8_t adresse, uint8_t registre, uint8_t valeur) {
  Wire.beginTransmission(adresse);
  Wire.write(registre);
  Wire.write(valeur);
  return Wire.endTransmission() == 0;
}

// Lit n octets à partir d'un registre ; faux si le module ne répond pas.
bool lire(uint8_t adresse, uint8_t registre, uint8_t* octets, uint8_t n) {
  Wire.beginTransmission(adresse);
  Wire.write(registre);
  if (Wire.endTransmission(false) != 0 || Wire.requestFrom(adresse, n) != n) return false;
  for (uint8_t i = 0; i < n; i++) octets[i] = Wire.read();
  return true;
}

int lireOctet(uint8_t adresse, uint8_t registre) {
  uint8_t o;
  return lire(adresse, registre, &o, 1) ? o : -1;
}

}  // namespace

// ======================================================================
// L'œil : APDS-9960 (gestes et lumière)
// ======================================================================

namespace apds {
constexpr uint8_t ADRESSE = 0x39;
constexpr uint8_t ENABLE = 0x80, ATIME = 0x81, WTIME = 0x83, CONFIG1 = 0x8d, PPULSE = 0x8e, CONTROL = 0x8f,
                  CONFIG2 = 0x90, ID = 0x92, CDATAL = 0x94, GPENTH = 0xa0, GEXTH = 0xa1, GCONF1 = 0xa2, GCONF2 = 0xa3,
                  GPULSE = 0xa6, GCONF3 = 0xaa, GCONF4 = 0xab, GFLVL = 0xae, GSTATUS = 0xaf, GFIFO = 0xfc;
constexpr int NET = 10;          // une mesure du geste est « nette » au-dessus de ce niveau
constexpr int SENSIBILITE = 40;  // écart minimal entre le début et la fin du geste
}  // namespace apds

void Oeil::demarrer() {
  using namespace apds;
  int id = lireOctet(ADRESSE, ID);
  present_ = id == 0xab || id == 0x9c || id == 0xa8;  // APDS-9960 et ses variantes
  if (!present_) {
    Serial.println("Œil (APDS-9960) : absent");
    return;
  }
  ecrire(ADRESSE, ENABLE, 0x00);
  ecrire(ADRESSE, ATIME, 0xdb);    // lumière : 103 ms d'intégration
  ecrire(ADRESSE, WTIME, 0xff);
  ecrire(ADRESSE, PPULSE, 0x87);
  ecrire(ADRESSE, CONFIG1, 0x60);
  ecrire(ADRESSE, CONTROL, 0x09);  // LED 100 mA, proximité × 4, lumière × 4
  ecrire(ADRESSE, CONFIG2, 0x31);  // LED renforcée (300 %)
  ecrire(ADRESSE, GPENTH, 40);     // le geste commence quand la main approche…
  ecrire(ADRESSE, GEXTH, 30);      // … et finit quand elle s'éloigne
  ecrire(ADRESSE, GCONF1, 0x40);
  ecrire(ADRESSE, GCONF2, 0x41);   // gain × 4, LED 100 mA, 2,8 ms entre deux mesures
  ecrire(ADRESSE, GPULSE, 0xc9);
  ecrire(ADRESSE, GCONF3, 0x00);   // les quatre photodiodes
  ecrire(ADRESSE, GCONF4, 0x01);
  ecrire(ADRESSE, ENABLE, 0x4f);   // sous tension : lumière, proximité, attente, gestes
  Serial.printf("Œil (APDS-9960) : présent (0x%02X)\n", id);
}

// Le sens du geste : comment le rapport haut/bas et gauche/droite a changé entre le moment où
// la main est arrivée au-dessus du capteur et celui où elle l'a quitté.
bool Oeil::decoder(const char*& sens) {
  auto rapport = [](int a, int b) { return a + b > 0 ? (a - b) * 100 / (a + b) : 0; };
  int dUD = rapport(dernier_[0], dernier_[1]) - rapport(premier_[0], premier_[1]);
  int dLR = rapport(dernier_[2], dernier_[3]) - rapport(premier_[2], premier_[3]);
  if (std::abs(dUD) < apds::SENSIBILITE && std::abs(dLR) < apds::SENSIBILITE) return false;
  int s;  // 0 haut, 1 droite, 2 bas, 3 gauche
  if (std::abs(dUD) > std::abs(dLR)) s = dUD < 0 ? 0 : 2;
  else s = dLR < 0 ? 3 : 1;
  static const char* const SENS[4] = {"haut", "droite", "bas", "gauche"};
  sens = SENS[(s + broches::OEIL_ROTATION / 90) % 4];
  return true;
}

void Oeil::maj(const RappelGeste& geste, const RappelCapteur& capteur) {
  using namespace apds;
  uint32_t t = millis();
  if (!present_ || t < prochain_) return;
  prochain_ = t + 15;

  int statut = lireOctet(ADRESSE, GSTATUS);
  if (statut > 0 && (statut & 0x01)) {  // des mesures du geste attendent dans la file
    int n = lireOctet(ADRESSE, GFLVL);
    uint8_t m[4 * 32];
    if (n > 0 && n <= 32 && lire(ADRESSE, GFIFO, m, static_cast<uint8_t>(4 * n))) {
      for (int i = 0; i < n; i++) {
        const uint8_t* udlr = m + 4 * i;
        if (udlr[0] <= NET || udlr[1] <= NET || udlr[2] <= NET || udlr[3] <= NET) continue;
        for (int k = 0; k < 4; k++) {
          if (!enCours_) premier_[k] = udlr[k];
          dernier_[k] = udlr[k];
        }
        enCours_ = true;
      }
    }
  } else if (enCours_) {  // la main est partie : le geste est complet
    enCours_ = false;
    const char* sens;
    if (decoder(sens)) {
      Serial.printf("Œil : geste %s\n", sens);
      geste(sens);
    }
  }

  // La lumière : une lampe braquée sur l'œil, bien plus fort que la pièce.
  if (t < prochaineLumiere_ || enCours_) return;
  prochaineLumiere_ = t + 200;
  uint8_t c[2];
  if (!lire(ADRESSE, CDATAL, c, 2)) return;
  float clair = c[0] | (c[1] << 8);
  if (ambiance_ <= 0) ambiance_ = std::max(clair, 1.0f);
  float seuil = std::max(ambiance_ * broches::OEIL_LAMPE, ambiance_ + broches::OEIL_LAMPE_MIN);
  bool eclaire = eclaire_ ? clair > seuil * 0.6f : clair > seuil;
  if (!eclaire) ambiance_ = ambiance_ * 0.95f + clair * 0.05f;
  if (eclaire != eclaire_) {
    eclaire_ = eclaire;
    Serial.printf("Œil : %s (lumière %.0f, pièce %.0f)\n", eclaire ? "éclairé" : "plus éclairé", clair, ambiance_);
    capteur("eclaire", eclaire);
  }
}

// ======================================================================
// L'enclume : QMC5883L (un aimant posé dessus)
// ======================================================================

namespace qmc {
constexpr uint8_t ADRESSE = 0x0d;
constexpr uint8_t DONNEES = 0x00, STATUT = 0x06, CONTROLE = 0x09, PERIODE = 0x0b;
}  // namespace qmc

void Enclume::demarrer() {
  Wire.beginTransmission(qmc::ADRESSE);
  present_ = Wire.endTransmission() == 0;
  if (!present_) {
    Serial.println("Enclume (QMC5883L) : absente");
    return;
  }
  ecrire(qmc::ADRESSE, qmc::PERIODE, 0x01);
  ecrire(qmc::ADRESSE, qmc::CONTROLE, 0x1d);  // en continu, 50 mesures/s, ± 8 gauss
  Serial.println("Enclume (QMC5883L) : présente");
}

bool Enclume::lire(float& champ) {
  uint8_t o[7];
  if (!::lire(qmc::ADRESSE, qmc::DONNEES, o, 7)) return false;
  if (o[6] & 0x02) {  // saturé : un aimant tout près
    champ = 1e6f;
    return true;
  }
  float x = static_cast<int16_t>(o[0] | (o[1] << 8)), y = static_cast<int16_t>(o[2] | (o[3] << 8)),
        z = static_cast<int16_t>(o[4] | (o[5] << 8));
  champ = std::sqrt(x * x + y * y + z * z);
  return true;
}

void Enclume::maj(const RappelCapteur& capteur) {
  uint32_t t = millis();
  if (!present_ || t < prochain_) return;
  prochain_ = t + 100;
  float champ;
  if (!lire(champ)) return;
  if (repos_ <= 0) repos_ = champ;  // au démarrage, rien n'est posé
  bool aimant = std::fabs(champ - repos_) > broches::ENCLUME_SEUIL;
  if (!aimant) repos_ = repos_ * 0.98f + champ * 0.02f;
  confirmations_ = aimant != aimant_ ? confirmations_ + 1 : 0;
  if (confirmations_ >= 3) {
    aimant_ = aimant;
    confirmations_ = 0;
    Serial.printf("Enclume : %s (champ %.0f, repos %.0f)\n", aimant ? "aimant posé" : "aimant retiré", champ, repos_);
    capteur("aimant", aimant);
  }
}

// ======================================================================
// La forge : BME280 (un souffle : l'humidité, ou à défaut la température, qui monte)
// ======================================================================

namespace bme {
constexpr uint8_t ADRESSES[2] = {0x76, 0x77};
constexpr uint8_t ID = 0xd0, CTRL_HUM = 0xf2, CTRL_MEAS = 0xf4, CONFIG = 0xf5, DONNEES = 0xfa;
uint8_t adresse = 0x76;
}  // namespace bme

void Forge::demarrer() {
  int id = -1;
  for (uint8_t a : bme::ADRESSES) {
    id = lireOctet(a, bme::ID);
    if (id == 0x60 || id == 0x58) {
      bme::adresse = a;
      break;
    }
  }
  present_ = id == 0x60 || id == 0x58;
  if (!present_) {
    Serial.println("Forge (BME280) : absente");
    return;
  }
  humidite_ = id == 0x60;  // 0x58 : un BMP280, sans capteur d'humidité
  uint8_t c[7];
  if (::lire(bme::adresse, 0x88, c, 6)) {
    t1_ = c[0] | (c[1] << 8);
    t2_ = static_cast<int16_t>(c[2] | (c[3] << 8));
    t3_ = static_cast<int16_t>(c[4] | (c[5] << 8));
  }
  if (humidite_) {
    h1_ = static_cast<uint8_t>(lireOctet(bme::adresse, 0xa1));
    if (::lire(bme::adresse, 0xe1, c, 7)) {
      h2_ = static_cast<int16_t>(c[0] | (c[1] << 8));
      h3_ = c[2];
      h4_ = static_cast<int16_t>((static_cast<int8_t>(c[3]) << 4) | (c[4] & 0x0f));
      h5_ = static_cast<int16_t>((static_cast<int8_t>(c[5]) << 4) | (c[4] >> 4));
      h6_ = static_cast<int8_t>(c[6]);
    }
    ecrire(bme::adresse, bme::CTRL_HUM, 0x01);
  }
  ecrire(bme::adresse, bme::CTRL_MEAS, 0x27);  // température, pression, humidité × 1, en continu
  ecrire(bme::adresse, bme::CONFIG, 0x00);
  Serial.printf("Forge (%s) : présente (0x%02X)\n", humidite_ ? "BME280" : "BMP280, sans humidité", bme::adresse);
}

// Les formules de compensation de la fiche technique Bosch (en entiers).
bool Forge::lire(float& temperature, float& humidite) {
  uint8_t o[5];
  if (!::lire(bme::adresse, bme::DONNEES, o, 5)) return false;
  int32_t adcT = (o[0] << 12) | (o[1] << 4) | (o[2] >> 4);
  int32_t v1 = ((((adcT >> 3) - (static_cast<int32_t>(t1_) << 1))) * t2_) >> 11;
  int32_t v2 = (((((adcT >> 4) - t1_) * ((adcT >> 4) - t1_)) >> 12) * t3_) >> 14;
  int32_t tFine = v1 + v2;
  temperature = ((tFine * 5 + 128) >> 8) / 100.0f;
  humidite = 0;
  if (!humidite_) return true;
  int32_t adcH = (o[3] << 8) | o[4];
  int32_t x = tFine - 76800;
  x = (((((adcH << 14) - (static_cast<int32_t>(h4_) << 20) - (h5_ * x)) + 16384) >> 15) *
       (((((((x * h6_) >> 10) * (((x * h3_) >> 11) + 32768)) >> 10) + 2097152) * h2_ + 8192) >> 14));
  x = x - (((((x >> 15) * (x >> 15)) >> 7) * h1_) >> 4);
  x = x < 0 ? 0 : x > 419430400 ? 419430400 : x;
  humidite = (x >> 12) / 1024.0f;
  return true;
}

void Forge::maj(const RappelCapteur& capteur) {
  uint32_t t = millis();
  if (!present_ || t < prochain_) return;
  prochain_ = t + 200;
  float temperature, humidite;
  if (!lire(temperature, humidite)) return;
  float mesure = humidite_ ? humidite : temperature;
  float seuil = humidite_ ? broches::FORGE_HUMIDITE : broches::FORGE_TEMPERATURE;
  if (repos_ <= 0) repos_ = mesure;
  bool souffle = mesure - repos_ > (souffle_ ? seuil * 0.5f : seuil);
  // Le souffle passé, l'humidité redescend lentement : on la laisse reprendre sa valeur de repos.
  if (!souffle) repos_ = repos_ * 0.97f + mesure * 0.03f;
  if (souffle) finSouffle_ = t + 1500;
  bool actif = souffle || t < finSouffle_;
  if (actif != souffle_) {
    souffle_ = actif;
    Serial.printf("Forge : %s (%.1f, repos %.1f %s)\n", actif ? "souffle" : "fin du souffle", mesure, repos_,
                  humidite_ ? "% d'humidité" : "°C");
    capteur("souffle", actif);
  }
}

// ======================================================================
// L'afficheur à 4 chiffres : HT16K33 (afficheur 7 segments, câblage Adafruit)
// ======================================================================

namespace ht {
constexpr uint8_t ADRESSE = 0x70;
// Les octets de l'afficheur : chiffres 1 et 2, les deux-points, chiffres 3 et 4.
constexpr uint8_t POSITIONS[4] = {0, 2, 6, 8};
constexpr uint8_t DEUX_POINTS = 4;
constexpr uint8_t CHIFFRES[10] = {0x3f, 0x06, 0x5b, 0x4f, 0x66, 0x6d, 0x7d, 0x07, 0x7f, 0x6f};
}  // namespace ht

void Afficheur::demarrer() {
  Wire.beginTransmission(ht::ADRESSE);
  Wire.write(0x21);  // oscillateur en marche
  present_ = Wire.endTransmission() == 0;
  if (!present_) {
    Serial.println("Afficheur (HT16K33) : absent");
    return;
  }
  Wire.beginTransmission(ht::ADRESSE);
  Wire.write(0x81);  // affichage allumé, sans clignotement
  Wire.endTransmission();
  Wire.beginTransmission(ht::ADRESSE);
  Wire.write(0xe0 | broches::AFFICHEUR_LUMINOSITE);
  Wire.endTransmission();
  montrer("");
  Serial.println("Afficheur (HT16K33) : présent");
}

void Afficheur::montrer(const std::string& texte) {
  if (!present_ || texte == affiche_) return;
  affiche_ = texte;
  uint8_t ram[10] = {0};
  int position = 0;
  for (char c : texte) {
    if (c == ':') {
      ram[ht::DEUX_POINTS] = 0x02;
      continue;
    }
    if (position >= 4) break;
    uint8_t segments = c >= '0' && c <= '9' ? ht::CHIFFRES[c - '0'] : c == '-' ? 0x40 : c == '_' ? 0x08 : 0;
    ram[ht::POSITIONS[position++]] = segments;
  }
  Wire.beginTransmission(ht::ADRESSE);
  Wire.write(0x00);
  Wire.write(ram, sizeof(ram));
  Wire.endTransmission();
}
