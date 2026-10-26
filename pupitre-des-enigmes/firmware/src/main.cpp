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
#include "capteur_couleur.h"
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
CapteurCouleur capteur;
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
  ecran.logo();
  const uint32_t debutLogo = millis();

  FastLED.addLeds<WS2812B, broches::LED, GRB>(leds, broches::NB_LED);
  const pupitre::Reglages defaut;  // remplacés par /reglages.json au démarrage du moteur
  FastLED.setBrightness(static_cast<uint8_t>(defaut.led * 255 / 100));
  FastLED.clear(true);

  if (!SD.begin(broches::SD_CS, SPI, 20000000)) {
    ecran.message("CARTE SD ABSENTE",
                  "Insérez la carte micro-SD dans le lecteur de l'écran (préparée avec outils/preparer_sd.py), "
                  "puis redémarrez le pupitre.");
    Serial.println("Carte SD introuvable.");
    for (;;) delay(1000);
  }

  lecteurs.demarrer();
  commandes.demarrer();  // démarre aussi le bus I2C
  capteur.demarrer();
  if (!hp.demarrer(broches::HP_RX, broches::HP_TX, defaut.hp)) Serial.println("DFPlayer haut-parleur absent.");
  if (!casque.demarrer(broches::CASQUE_RX, broches::CASQUE_TX, defaut.casque)) Serial.println("DFPlayer casque absent.");

  rappels.bouton = [](int b) { moteur.bouton(static_cast<pupitre::Bouton>(b)); };
  rappels.enfonces = [](uint8_t masque) { moteur.boutonsEnfonces(masque); };
  rappels.molette = [](int pas) { moteur.molette(pas); };
  rappels.appui = [] { moteur.appuiMolette(); };
  rappels.touche = [](char k) { moteur.touche(k); };
  rappels.cle = [](bool tournee) {
    Serial.printf("Interrupteur à clé : %s\n", tournee ? "tourné" : "au repos");
    moteur.cle(tournee);
  };

  // Le logo reste au moins 2,5 secondes, le temps que tout démarre.
  while (millis() - debutLogo < reglages::DUREE_LOGO_MS) delay(10);
  moteur.demarrer();
  panneau::demarrer(moteur);
}

void loop() {
  hp.maj();
  casque.maj();
  commandes.maj(rappels);
  lecteurs.maj([](int n, const std::string& uid) {
    Serial.printf("Lecteur %d : %s\n", n, uid.empty() ? "carte retirée" : uid.c_str());
    if (broches::NB_LECTEURS == 2) moteur.lecteur(n, uid);
    else moteur.carte(static_cast<pupitre::Zone>(n), uid);
  });
  capteur.maj([](const std::string& couleur) {
    Serial.printf("Capteur de couleur : %s\n", couleur.empty() ? "objet retiré" : couleur.c_str());
    moteur.couleurPosee(couleur);
  });
  moteur.miseAJour();
  panneau::maj();
  delay(2);
}
