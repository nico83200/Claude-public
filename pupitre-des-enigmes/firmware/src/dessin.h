// Dessine une Vue du moteur sur un écran LovyanGFX (480 × 320, à l'horizontale).
//
// Interface en aplats de couleur, coins arrondis et polices lissées (Inter). Les
// textes sont toujours posés sur une couleur connue : le lissage n'a pas besoin de
// relire l'écran (l'ILI9488 du pupitre ne le permet pas).
//
// Seules les parties qui ont changé sont redessinées : l'en-tête, le corps, le
// minuteur d'une épreuve, la ligne d'information et la barre des lecteurs. Le même
// code sert au pupitre et au simulateur PC (qui dessine dans une image en mémoire).
#pragma once

#include "plateforme.h"
#include "polices.h"

class Dessin {
 public:
  explicit Dessin(lgfx::LovyanGFX& g) : g_(g) {}
  void afficher(const pupitre::Vue& v, uint32_t maintenant);
  void message(const char* titre, const char* texte);
  void logo();  // le logo du démarrage

 private:
  struct Palette {
    uint32_t fond, surface, surface2, trait, texte, doux, accent, accentDoux, alerte;
  };

  // Les zones de l'écran.
  void entete(const pupitre::Vue& v);
  void corps(const pupitre::Vue& v);
  void minuteur(const pupitre::Vue& v);
  void ligneInfo(const pupitre::Vue& v);
  void barreZones(const pupitre::Vue& v);
  void noir();

  // Les mises en page du corps.
  void corpsTexte(const pupitre::Vue& v);
  void corpsAccueil(const pupitre::Vue& v);
  void corpsMenu(const pupitre::Vue& v);
  void corpsCode(const pupitre::Vue& v);
  void corpsChoix(const pupitre::Vue& v);
  void corpsEnergie(const pupitre::Vue& v);
  void corpsEpreuve(const pupitre::Vue& v);
  void corpsCasque(const pupitre::Vue& v);
  void corpsResultat(const pupitre::Vue& v);
  void corpsAccusation(const pupitre::Vue& v);
  void corpsTableau(const pupitre::Vue& v);
  void corpsReglages(const pupitre::Vue& v);
  void corpsEtat(const pupitre::Vue& v);
  void corpsEnrolement(const pupitre::Vue& v);

  // Texte.
  void ecrire(const std::string& t, int x, int y, const lgfx::IFont* police, uint32_t fg, uint32_t bg, int ancrage = 0);
  int largeur(const std::string& t, const lgfx::IFont* police);
  int paragraphe(const std::string& t, int x, int y, int largeur, int yMax, const lgfx::IFont* police, uint32_t fg,
                 uint32_t bg, bool centre, int interligne, bool dessiner = true);
  const lgfx::IFont* policeQuiTient(const std::string& t, int largeur, int hauteur, int* interligne);
  std::string couper(const std::string& t, const lgfx::IFont* police, int largeur);

  // Formes et pictogrammes.
  void pastille(int x, int y, int w, int h, uint32_t fond);
  void bandeau(const std::string& t, int x, int y, int w, bool enveloppe);
  void coche(int x, int y, int taille, uint32_t couleur);
  void iconeEnveloppe(int x, int y, uint32_t couleur, uint32_t fond);
  void iconeCasque(int cx, int cy, int r, uint32_t couleur);
  void iconeAmpoule(int cx, int cy, uint32_t couleur);
  void iconeHorloge(int cx, int cy, uint32_t couleur, uint32_t fond);
  void points(int cx, int y, int faits, int total);
  uint32_t c(uint32_t rgb) const;  // la couleur, assombrie quand la lumière baisse

  lgfx::LovyanGFX& g_;
  pupitre::Vue derniere_;
  Palette pal_{};
  float facteur_ = 1.0f;
  bool premiere_ = true;
  bool clignote_ = false;
  uint32_t dernierClignotement_ = 0;
};
