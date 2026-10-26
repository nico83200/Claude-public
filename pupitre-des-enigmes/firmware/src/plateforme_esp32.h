// Le pupitre réel : relie le moteur à l'écran, la carte SD, l'audio et les LED.
#pragma once

#include <FastLED.h>

#include <functional>

#include "audio.h"
#include "capteurs_i2c.h"
#include "ecran.h"
#include "plateforme.h"

class PlateformeESP32 : public pupitre::Plateforme {
 public:
  PlateformeESP32(Ecran& ecran, LecteurAudio& hp, LecteurAudio& casque, CRGB* leds)
      : ecran_(ecran), hp_(hp), casque_(casque), leds_(leds) {}

  uint32_t maintenant() override { return millis(); }
  bool lireFichier(const std::string& chemin, std::string& contenu) override;
  bool ecrireFichier(const std::string& chemin, const std::string& contenu) override;
  void jouer(int dossier, int numero, bool casque) override { (casque ? casque_ : hp_).jouer(dossier, numero); }
  bool joue(bool casque) override { return (casque ? casque_ : hp_).joue(); }
  void arreter(bool casque) override { (casque ? casque_ : hp_).arreter(); }
  bool audioPresent() override { return hp_.present(); }
  void afficher(const pupitre::Vue& vue) override { ecran_.afficher(vue); }
  void eclairer(const pupitre::Lumieres& lumieres) override;
  uint32_t aleatoire(uint32_t borne) override { return borne ? esp_random() % borne : 0; }
  void journal(const std::string& message) override { Serial.println(message.c_str()); }
  void appliquer(const pupitre::Reglages& r) override;
  void tiroir(bool ouvrir) override { tiroir_.commander(ouvrir); }
  bool tiroirPresent() override { return tiroir_.present(); }
  std::vector<pupitre::EtatElement> etatMateriel() override { return etat ? etat() : std::vector<pupitre::EtatElement>{}; }
  std::function<std::vector<pupitre::EtatElement>()> etat;  // rempli par main.cpp, qui connaît tous les modules
  Tiroir& servomoteurTiroir() { return tiroir_; }

 private:
  Ecran& ecran_;
  LecteurAudio& hp_;
  LecteurAudio& casque_;
  CRGB* leds_;
  Tiroir tiroir_;
  uint32_t dernierAffichage_ = 0;
};
