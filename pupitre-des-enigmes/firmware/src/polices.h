// Les polices lissées de l'écran (Inter), produites par outils/polices_ecran.py.
#pragma once

#define LGFX_USE_V1
#include <LovyanGFX.hpp>

namespace polices {

void charger();  // une fois, avant le premier dessin

extern lgfx::VLWfont texte;      // 18 px, le corps du texte
extern lgfx::VLWfont texteGras;  // 18 px, demi-gras
extern lgfx::VLWfont compact;    // 15 px, les longs textes
extern lgfx::VLWfont petit;      // 13 px, étiquettes
extern lgfx::VLWfont titre;      // 26 px, titres
extern lgfx::VLWfont grand;      // 36 px, consignes des épreuves, fin de partie
extern lgfx::VLWfont chiffres;   // 54 px, codes et chronos (chiffres, A–Z)

}  // namespace polices
