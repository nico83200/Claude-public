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
  // La dalle tactile : le point touché (en pixels de l'écran), ce qu'il veut dire, et l'étalonnage.
  bool touche(int& x, int& y);
  pupitre::Toucher cible(const pupitre::Vue& v, int x, int y) const { return dessin_.cible(v, x, y); }
  void etalonner(uint16_t parametres[8]);
  void appliquerEtalonnage(const uint16_t parametres[8]) { lcd_.setTouchCalibrate(const_cast<uint16_t*>(parametres)); }

 private:
  class Materiel : public lgfx::LGFX_Device {
   public:
    Materiel();
   private:
    lgfx::Panel_ILI9488 panneau_;
    lgfx::Bus_SPI bus_;
    lgfx::Light_PWM retroeclairage_;
    lgfx::Touch_XPT2046 tactile_;
  };

  Materiel lcd_;
  Dessin dessin_;
};
