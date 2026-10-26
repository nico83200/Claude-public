// Le capteur de couleur TCS34725 (I2C, adresse 0x29), sous une fenêtre de la façade : il
// reconnaît la couleur d'un objet posé dessus (« rouge », « vert »…), pour les missions qui
// s'en servent. Sans capteur branché, il ne se passe rien.
#pragma once

#include <cstdint>
#include <functional>
#include <string>

class CapteurCouleur {
 public:
  using Rappel = std::function<void(const std::string& couleur)>;  // "" : l'objet est retiré

  void demarrer();
  void maj(const Rappel& rappel);
  bool present() const { return present_; }
  uint16_t clair() const { return clair_; }
  const std::string& couleur() const { return stable_; }

 private:
  bool lire(uint16_t& c, uint16_t& r, uint16_t& v, uint16_t& b);
  std::string nommer(uint16_t c, uint16_t r, uint16_t v, uint16_t b) const;

  bool present_ = false;
  float aVide_ = 0;          // la lumière reçue sans objet (moyenne glissante)
  std::string lu_, stable_;  // dernière lecture, et ce qui a été annoncé
  int confirmations_ = 0;
  uint16_t clair_ = 0;
  uint32_t prochain_ = 0;
};
