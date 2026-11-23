#include "plateforme_esp32.h"

#include <SD.h>

#include "broches.h"

bool PlateformeESP32::lireFichier(const std::string& chemin, std::string& contenu) {
  File f = SD.open(chemin.c_str(), FILE_READ);
  // Une coupure pile entre l'effacement de l'ancien fichier et la mise en place du nouveau
  // (ecrireFichier) laisse seulement le « .tmp », complet : on le reprend (réglages, classement…).
  if (!f) f = SD.open((chemin + ".tmp").c_str(), FILE_READ);
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

namespace {

// Un fichier de la carte SD lu par morceaux de 512 octets.
class FichierSD : public pupitre::FichierLu {
 public:
  explicit FichierSD(File f) : f_(f) {}
  ~FichierSD() override { f_.close(); }
  int read() override {
    if (pos_ >= n_) {
      n_ = f_.read(reinterpret_cast<uint8_t*>(tampon_), sizeof(tampon_));
      pos_ = 0;
      if (n_ <= 0) {
        n_ = 0;
        return -1;
      }
    }
    return static_cast<uint8_t>(tampon_[pos_++]);
  }
  size_t readBytes(char* dest, size_t taille) override {
    size_t lus = 0;
    while (lus < taille) {
      int c = read();
      if (c < 0) break;
      dest[lus++] = static_cast<char>(c);
    }
    return lus;
  }

 private:
  File f_;
  char tampon_[512];
  int n_ = 0, pos_ = 0;
};

}  // namespace

std::unique_ptr<pupitre::FichierLu> PlateformeESP32::ouvrir(const std::string& chemin) {
  File f = SD.open(chemin.c_str(), FILE_READ);
  if (!f) return nullptr;  // lireFichier() essaiera le « .tmp »
  return std::unique_ptr<pupitre::FichierLu>(new FichierSD(f));
}

// Écriture dans un fichier temporaire, puis remplacement : une coupure de courant
// pendant l'écriture ne laisse pas de fichier à moitié écrit.
bool PlateformeESP32::ecrireFichier(const std::string& chemin, const std::string& contenu) {
  std::string temporaire = chemin + ".tmp";
  File f = SD.open(temporaire.c_str(), FILE_WRITE);
  if (!f) {
    Serial.printf("Carte SD : impossible d'écrire %s (carte protégée en écriture, ou mal reliée ?)\n", chemin.c_str());
    return false;
  }
  size_t n = f.write(reinterpret_cast<const uint8_t*>(contenu.data()), contenu.size());
  f.close();
  if (n != contenu.size()) {
    Serial.printf("Carte SD : écriture incomplète de %s (%u octets sur %u)\n", chemin.c_str(), static_cast<unsigned>(n),
                  static_cast<unsigned>(contenu.size()));
    return false;
  }
  SD.remove(chemin.c_str());
  if (!SD.rename(temporaire.c_str(), chemin.c_str())) {
    Serial.printf("Carte SD : impossible de remplacer %s\n", chemin.c_str());
    return false;
  }
  Serial.printf("Carte SD : %s enregistré\n", chemin.c_str());
  return true;
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
  if (t < essaiLed_) {  // l'atelier : chaque LED de la chaîne, l'une après l'autre, en arc-en-ciel
    for (int i = 0; i < broches::NB_LED; i++) leds_[i] = CHSV(static_cast<uint8_t>(i * 255 / broches::NB_LED + t / 8), 255, 255);
    FastLED.show();
    return;
  }
  int n = 0;  // dans l'ordre de la chaîne : anneau, lecteurs, voyants, ruban sous la boîte, tiroir
  for (uint32_t c : l.anneau) leds_[n++] = CRGB(c);
  for (int i = 0; i < broches::NB_LECTEURS; i++) leds_[n++] = CRGB(l.lecteurs[i]);
  for (uint32_t c : l.voyants) leds_[n++] = CRGB(c);
  for (int i = 0; i < broches::NB_LED_SOCLE; i++) leds_[n++] = CRGB(l.socle);
  for (int i = 0; i < broches::NB_LED_TIROIR; i++) leds_[n++] = CRGB(l.tiroir);
  FastLED.show();
}
