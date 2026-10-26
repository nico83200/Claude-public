// L'écran ILI9488 de 3,5 pouces du pupitre, piloté par LovyanGFX sur le bus SPI commun.
#pragma once

#include "dessin.h"

class Ecran {
 public:
  Ecran() : dessin_(lcd_) {}
  void demarrer();
  void afficher(const pupitre::Vue& v) { dessin_.afficher(v, millis()); }
  void message(const char* titre, const char* texte) { dessin_.message(titre, texte); }
  void logo() { dessin_.logo(); }
  void luminosite(int pourcent) { lcd_.setBrightness(static_cast<uint8_t>(pourcent * 255 / 100)); }

 private:
  class Materiel : public lgfx::LGFX_Device {
   public:
    Materiel();
   private:
    lgfx::Panel_ILI9488 panneau_;
    lgfx::Bus_SPI bus_;
    lgfx::Light_PWM retroeclairage_;
  };

  Materiel lcd_;
  Dessin dessin_;
};
