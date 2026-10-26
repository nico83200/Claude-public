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
#include "capteurs_i2c.h"
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
Oeil oeil;
Enclume enclume;
Forge forge;
Afficheur afficheur;
PlateformeESP32 plateforme(ecran, hp, casque, leds);
pupitre::Moteur moteur(plateforme);

Commandes::Rappels rappels;
uint32_t essaiAfficheur = 0;  // l'atelier du panneau : « 88:88 » sur l'horloge jusqu'à cet instant

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
  oeil.demarrer();
  enclume.demarrer();
  forge.demarrer();
  // Le PCA9685 répond aussi à 0x70 tant qu'on ne le lui a pas interdit : avant l'horloge.
  plateforme.servomoteurTiroir().demarrer();
  afficheur.demarrer();
  if (!hp.demarrer(broches::HP_RX, broches::HP_TX, defaut.hp)) Serial.println("DFPlayer haut-parleur absent.");
  if (!casque.demarrer(broches::CASQUE_RX, broches::CASQUE_TX, defaut.casque)) Serial.println("DFPlayer casque absent.");

  rappels.bouton = [](int b) { moteur.bouton(static_cast<pupitre::Bouton>(b)); };
  rappels.enfonces = [](uint8_t masque) { moteur.boutonsEnfonces(masque); };
  rappels.molette = [](int pas) { moteur.molette(pas); };
  rappels.appui = [] { moteur.appuiMolette(); };
  rappels.touche = [](char k) { moteur.touche(k); };
  rappels.tiroir = [](bool auFond) {
    Serial.printf("Tiroir : %s\n", auFond ? "au fond" : "sorti");
    moteur.tiroirAuFond(auFond);
  };
  rappels.cle = [](bool tournee) {
    Serial.printf("Interrupteur à clé : %s\n", tournee ? "tourné" : "au repos");
    moteur.cle(tournee);
  };

  // Le logo reste au moins 2,5 secondes, le temps que tout démarre.
  while (millis() - debutLogo < reglages::DUREE_LOGO_MS) delay(10);
  plateforme.etat = [] {
    auto t = [](bool present) { return present ? 1 : 0; };
    return std::vector<pupitre::EtatElement>{
        {"", "Carte SD", 1},
        {"", "Écran", -1},
        {"fente", "Lecteur de la fente", t(lecteurs.present(0))},
        {"plateau", "Lecteur à plat", t(broches::NB_LECTEURS > 1 && lecteurs.present(1))},
        {"", "Haut-parleur", t(hp.present())},
        {"casque", "Casque", t(casque.present())},
        {"boutons", "Boutons de couleur", t(commandes.boutonsPresents())},
        {"clavier", "Clavier 4×4", t(commandes.clavierPresent())},
        {"molette", "Molette", -1},
        {"gros_bouton", "Gros bouton", -1},
        {"cle", "Clé", -1},
        {"anneau", "LED", -1},
        {"couleur", "Capteur de couleur", t(capteur.present())},
        {"gestes", "Œil (gestes, lampe)", t(oeil.present())},
        {"souffle", "Forge (souffle)", t(forge.present())},
        {"aimant", "Enclume (aimant)", t(enclume.present())},
        {"afficheur", "Horloge", t(afficheur.present())},
        {"tiroir", "Tiroir secret", t(plateforme.servomoteurTiroir().present())},
    };
  };
  moteur.demarrer();
  panneau::Atelier atelier;
  atelier.mesures = [] {
    char j[900];
    snprintf(j, sizeof j,
             "{\"Boutons enfoncés\":\"%s%s%s%s%s\",\"Dernière touche\":\"%c\",\"Molette\":\"%ld\",\"Clé\":\"%s\","
             "\"Tiroir\":\"%s\",\"Couleur\":\"%s (clair %u)\",\"Œil : lumière\":\"%.0f (pièce %.0f)%s\","
             "\"Œil : dernier geste\":\"%s\",\"Forge\":\"%.1f %s (repos %.1f)%s\",\"Enclume\":\"%.0f (repos %.0f)%s\"}",
             commandes.boutonsEnfonces() & 1 ? "rouge " : "", commandes.boutonsEnfonces() & 2 ? "bleu " : "",
             commandes.boutonsEnfonces() & 4 ? "jaune " : "", commandes.boutonsEnfonces() & 8 ? "vert " : "",
             commandes.boutonsEnfonces() & 16 ? "gros" : "", commandes.derniereTouche(), static_cast<long>(commandes.molette()),
             commandes.cleTournee() ? "tournée" : "repos", commandes.tiroirAuFond() ? "au fond" : "sorti",
             capteur.present() ? (capteur.couleur().empty() ? "rien" : capteur.couleur().c_str()) : "absent", capteur.clair(),
             oeil.lumiere(), oeil.piece(), oeil.eclaire() ? " · ÉCLAIRÉ" : "", oeil.present() ? oeil.dernierGeste() : "absent",
             forge.mesure(), forge.humidite() ? "%" : "°C", forge.repos(), forge.souffle() ? " · SOUFFLE" : "",
             enclume.champ(), enclume.repos(), enclume.aimant() ? " · AIMANT" : "");
    return std::string(j);
  };
  atelier.essai = [](const std::string& a) {
    if (a == "led") plateforme.essaiLed(millis() + 6000);
    else if (a == "afficheur") essaiAfficheur = millis() + 5000;
    else if (a == "son_hp") plateforme.jouer(90, 2, false);
    else if (a == "son_casque") plateforme.jouer(90, 2, true);
    else if (a == "tiroir_ouvrir") moteur.commande("tiroir");
    else if (a == "tiroir_fermer") moteur.commande("tiroir_fermer");
  };
  panneau::demarrer(moteur, atelier);
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
  auto capteurActif = [](const char* nom, bool actif) { moteur.capteur(nom, actif); };
  oeil.maj([](const char* sens) { moteur.geste(sens); }, capteurActif);
  enclume.maj(capteurActif);
  forge.maj(capteurActif);
  afficheur.montrer(millis() < essaiAfficheur ? "88:88" : moteur.afficheur());
  plateforme.servomoteurTiroir().maj();
  moteur.miseAJour();
  panneau::maj();
  delay(2);
}
