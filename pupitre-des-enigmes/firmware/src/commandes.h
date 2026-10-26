// Les quatre boutons de couleur, le gros bouton, la molette et le clavier 4×4.
#pragma once

#include <cstdint>
#include <functional>

class Commandes {
 public:
  struct Rappels {
    std::function<void(int bouton)> bouton;  // 0 rouge … 3 vert, 4 gros
    std::function<void(uint8_t masque)> enfonces;
    std::function<void(int pas)> molette;
    std::function<void()> appui;
    std::function<void(char touche)> touche;  // clavier : '0'–'9', 'A'–'D', '*', '#'
  };

  void demarrer();
  void maj(const Rappels& r);

 private:
  struct Anti {  // anti-rebond
    bool stable = false, lu = false;
    uint32_t depuis = 0;
  };
  bool filtrer(Anti& a, bool brut, uint32_t t);
  char lireClavier();

  Anti boutons_[5], sw_;
  uint8_t masque_ = 0;
  int32_t dernierePosition_ = 0;
  uint8_t adresseClavier_ = 0, adresseBoutons_ = 0;  // 0 : module absent
  char toucheLue_ = 0, toucheStable_ = 0;
  uint32_t toucheDepuis_ = 0, dernierBalayage_ = 0;
};
