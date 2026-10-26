#include "plateforme_esp32.h"

#include <SD.h>

#include "broches.h"

bool PlateformeESP32::lireFichier(const std::string& chemin, std::string& contenu) {
  File f = SD.open(chemin.c_str(), FILE_READ);
  if (!f) return false;
  contenu.clear();
  contenu.reserve(f.size());
  char tampon[512];
  while (f.available()) {
    int n = f.read(reinterpret_cast<uint8_t*>(tampon), sizeof(tampon));
    if (n <= 0) break;
    contenu.append(tampon, n);
  }
  f.close();
  return true;
}

// Écriture dans un fichier temporaire, puis remplacement : une coupure de courant
// pendant l'écriture ne laisse pas de fichier à moitié écrit.
bool PlateformeESP32::ecrireFichier(const std::string& chemin, const std::string& contenu) {
  std::string temporaire = chemin + ".tmp";
  File f = SD.open(temporaire.c_str(), FILE_WRITE);
  if (!f) return false;
  size_t n = f.write(reinterpret_cast<const uint8_t*>(contenu.data()), contenu.size());
  f.close();
  if (n != contenu.size()) return false;
  SD.remove(chemin.c_str());
  return SD.rename(temporaire.c_str(), chemin.c_str());
}

void PlateformeESP32::appliquer(const pupitre::Reglages& r) {
  ecran_.luminosite(r.ecran);
  hp_.volume(r.hp);
  casque_.volume(r.casque);
  FastLED.setBrightness(static_cast<uint8_t>(r.led * 255 / 100));
}

void PlateformeESP32::eclairer(const pupitre::Lumieres& l) {
  uint32_t t = millis();
  if (t - dernierAffichage_ < 20) return;  // 50 images par seconde suffisent
  dernierAffichage_ = t;
  int n = 0;  // dans l'ordre de la chaîne : anneau, lecteurs, voyants, ruban sous la boîte, tiroir
  for (uint32_t c : l.anneau) leds_[n++] = CRGB(c);
  for (int i = 0; i < broches::NB_LECTEURS; i++) leds_[n++] = CRGB(l.lecteurs[i]);
  for (uint32_t c : l.voyants) leds_[n++] = CRGB(c);
  for (int i = 0; i < broches::NB_LED_SOCLE; i++) leds_[n++] = CRGB(l.socle);
  for (int i = 0; i < broches::NB_LED_TIROIR; i++) leds_[n++] = CRGB(l.tiroir);
  FastLED.show();
}
