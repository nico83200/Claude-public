// Dessine une Vue du moteur sur un écran LovyanGFX (480 × 320, à l'horizontale).
//
// Seules les parties qui ont changé sont redessinées : l'en-tête, le corps, la ligne
// d'information et la barre des zones. Le même code sert au pupitre et au
// simulateur PC (qui dessine dans une image en mémoire).
#pragma once

#define LGFX_USE_V1
#include <LovyanGFX.hpp>

#include "plateforme.h"

class Dessin {
 public:
  explicit Dessin(lgfx::LovyanGFX& g) : g_(g) {}
  void afficher(const pupitre::Vue& v, uint32_t maintenant);
  void message(const char* titre, const char* texte);

 private:
  void entete(const pupitre::Vue& v);
  void corps(const pupitre::Vue& v);
  void ligneInfo(const pupitre::Vue& v);
  void barreZones(const pupitre::Vue& v);
  void corpsCode(const pupitre::Vue& v);
  void corpsChoix(const pupitre::Vue& v);
  void corpsEnergie(const pupitre::Vue& v);
  void corpsAccusation(const pupitre::Vue& v);
  int paragraphe(const std::string& texte, int x, int y, int largeur, int yMax, uint16_t couleur, bool centre = false);
  void police(bool grande);
  uint16_t c(uint32_t rgb) const;

  lgfx::LovyanGFX& g_;
  pupitre::Vue derniere_;
  bool premiere_ = true;
  bool clignote_ = false;
  uint32_t dernierClignotement_ = 0;
  bool grande_ = false;
};
