// Les modules I2C que certaines missions utilisent, tous facultatifs (absents : il ne se passe
// rien, et le journal série le dit au démarrage) :
//   - l'œil (APDS-9960, 0x39), sous une fenêtre de la façade : les gestes de la main
//     (haut, bas, gauche, droite) et une lampe braquée dessus (« eclaire ») ;
//   - l'enclume (QMC5883L, 0x0D), sous le contreplaqué : un aimant posé dessus (« aimant ») ;
//   - la forge (BME280, 0x76), sous une grille de trous : un souffle (« souffle ») ;
//   - l'afficheur à 4 chiffres (HT16K33, 0x70), derrière une fenêtre : l'horloge, un code ;
//   - le tiroir secret : un servomoteur sur un module PCA9685 (0x40) tient le verrou.
#pragma once

#include <cstdint>
#include <functional>
#include <string>

// Un capteur tout-ou-rien : le rappel reçoit son nom et son état à chaque changement.
using RappelCapteur = std::function<void(const char* nom, bool actif)>;

class Oeil {
 public:
  using RappelGeste = std::function<void(const char* sens)>;
  void demarrer();
  void maj(const RappelGeste& geste, const RappelCapteur& capteur);
  bool present() const { return present_; }

 private:
  bool decoder(const char*& sens);
  bool present_ = false;
  // Le geste en cours : la première et la dernière mesure nettes (haut, bas, gauche, droite).
  int premier_[4] = {0}, dernier_[4] = {0};
  bool enCours_ = false;
  float ambiance_ = 0;  // la lumière de la pièce (moyenne glissante)
  bool eclaire_ = false;
  uint32_t prochain_ = 0, prochaineLumiere_ = 0;
};

class Enclume {
 public:
  void demarrer();
  void maj(const RappelCapteur& capteur);
  bool present() const { return present_; }

 private:
  bool lire(float& champ);
  bool present_ = false;
  float repos_ = 0;  // le champ magnétique sans aimant (moyenne glissante)
  bool aimant_ = false;
  int confirmations_ = 0;
  uint32_t prochain_ = 0;
};

class Forge {
 public:
  void demarrer();
  void maj(const RappelCapteur& capteur);
  bool present() const { return present_; }

 private:
  bool lire(float& temperature, float& humidite);
  bool present_ = false, humidite_ = false;  // un BMP280 n'a pas d'humidité : on suit la température
  uint16_t t1_ = 0;
  int16_t t2_ = 0, t3_ = 0, h2_ = 0, h4_ = 0, h5_ = 0;
  uint8_t h1_ = 0, h3_ = 0;
  int8_t h6_ = 0;
  float repos_ = 0;  // humidité (ou température) de la pièce
  bool souffle_ = false;
  uint32_t prochain_ = 0, finSouffle_ = 0;
};

class Afficheur {
 public:
  void demarrer();
  void montrer(const std::string& texte);  // « 08:14 », « 37-- », "" pour éteindre
  bool present() const { return present_; }

 private:
  bool present_ = false;
  std::string affiche_ = "?";
};

// Le tiroir secret : le servomoteur tourne vers « ouvert » (le ressort pousse le tiroir dehors)
// ou vers « fermé » (son bras entre dans l'encoche du tiroir), puis se met au repos : le verrou
// tient tout seul, sans consommer ni grésiller.
class Tiroir {
 public:
  void demarrer();
  bool present() const { return present_; }
  void commander(bool ouvrir);
  void maj();

 private:
  void impulsion(int microsecondes);  // 0 : plus d'impulsion (servomoteur au repos)
  bool present_ = false;
  uint32_t repos_ = 0;
};
