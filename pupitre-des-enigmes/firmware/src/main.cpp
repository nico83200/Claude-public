// Le Pupitre des Énigmes — programme de l'ESP32.
//
// Le matériel lit les cartes, les boutons et la molette, et les transmet au moteur
// de jeu (lib/moteur), qui exécute les scripts des missions rangés sur la carte SD
// de l'écran. Voir firmware/README.md.
#include <Arduino.h>
#include <FastLED.h>
#include <SD.h>
#include <SPI.h>

#include "audio.h"
#include "broches.h"
#include "commandes.h"
#include "ecran.h"
#include "lecteurs.h"
#include "moteur.h"
#include "panneau_web.h"
#include "plateforme_esp32.h"

namespace {

Ecran ecran;
LecteurAudio hp(Serial2);
LecteurAudio casque(Serial1);
CRGB leds[broches::NB_LED];
Lecteurs lecteurs;
Commandes commandes;
PlateformeESP32 plateforme(ecran, hp, casque, leds);
pupitre::Moteur moteur(plateforme);

Commandes::Rappels rappels;

}  // namespace

void setup() {
  Serial.begin(115200);
  Serial.println("\nLe Pupitre des Énigmes");

  // Toutes les puces du bus SPI désélectionnées avant d'initialiser quoi que ce soit.
  for (int cs : {broches::ECRAN_CS, broches::SD_CS, broches::RFID_CS[0], broches::RFID_CS[1], broches::RFID_CS[2]}) {
    pinMode(cs, OUTPUT);
    digitalWrite(cs, HIGH);
  }
  SPI.begin(broches::SCK, broches::MISO, broches::MOSI);
  ecran.demarrer();
  ecran.message("LE PUPITRE DES ÉNIGMES", "Démarrage…");

  FastLED.addLeds<WS2812B, broches::LED, GRB>(leds, broches::NB_LED);
  FastLED.setBrightness(reglages::LUMINOSITE_LED);
  FastLED.clear(true);

  if (!SD.begin(broches::SD_CS, SPI, 20000000)) {
    ecran.message("CARTE SD ABSENTE",
                  "Insérez la carte micro-SD dans le lecteur de l'écran (préparée avec outils/preparer_sd.py), "
                  "puis redémarrez le pupitre.");
    Serial.println("Carte SD introuvable.");
    for (;;) delay(1000);
  }

  lecteurs.demarrer();
  commandes.demarrer();
  if (!hp.demarrer(broches::HP_RX, broches::HP_TX, reglages::VOLUME_HP)) Serial.println("DFPlayer haut-parleur absent.");
  if (!casque.demarrer(broches::CASQUE_RX, broches::CASQUE_TX, reglages::VOLUME_CASQUE)) Serial.println("DFPlayer casque absent.");

  rappels.bouton = [](int b) { moteur.bouton(static_cast<pupitre::Bouton>(b)); };
  rappels.enfonces = [](uint8_t masque) { moteur.boutonsEnfonces(masque); };
  rappels.molette = [](int pas) { moteur.molette(pas); };
  rappels.appui = [] { moteur.appuiMolette(); };

  moteur.demarrer();
  panneau::demarrer(moteur);
}

void loop() {
  hp.maj();
  casque.maj();
  commandes.maj(rappels);
  lecteurs.maj([](int zone, const std::string& uid) {
    Serial.printf("Zone %d : %s\n", zone, uid.empty() ? "carte retirée" : uid.c_str());
    moteur.carte(static_cast<pupitre::Zone>(zone), uid);
  });
  moteur.miseAJour();
  panneau::maj();
  delay(2);
}
