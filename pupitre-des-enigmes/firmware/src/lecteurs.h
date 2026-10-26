// Les lecteurs RFID (RC522) : la fente à cartes et le lecteur à plat (ou les trois zones QUI,
// OÙ et QUOI d'un pupitre à trois lecteurs).
//
// Ils sont interrogés l'un après l'autre, antenne coupée entre deux lectures pour
// éviter qu'ils ne se brouillent. Une carte est déclarée retirée après plusieurs
// lectures vides de suite.
#pragma once

#include <MFRC522.h>

#include <functional>
#include <string>

class Lecteurs {
 public:
  using Rappel = std::function<void(int lecteur, const std::string& uid)>;  // uid vide : carte retirée

  void demarrer();
  void maj(const Rappel& rappel);
  bool present(int zone) const { return ok_[zone]; }

 private:
  std::string lire(MFRC522& lecteur);

  MFRC522 lecteurs_[3];
  bool ok_[3] = {false, false, false};
  std::string uid_[3];
  int absences_[3] = {0, 0, 0};
  int courant_ = 0;
  uint32_t prochain_ = 0;
};
