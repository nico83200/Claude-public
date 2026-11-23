// Le Pupitre des Énigmes — programme de l'ESP32.
//
// Le matériel lit les cartes, les boutons et la molette, et les transmet au moteur
// de jeu (lib/moteur), qui exécute les scripts des missions rangés sur la carte SD
// de l'écran. Voir firmware/README.md.
#include <Arduino.h>
#include <ArduinoJson.h>
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

// La boucle du pupitre (moteur, dessin, JSON) a besoin de plus que les 8 Ko par défaut.
SET_LOOP_TASK_STACK_SIZE(16 * 1024);

namespace {

// Pourquoi l'ESP32 a redémarré la dernière fois : affiché au démarrage si ce n'était pas normal,
// pour qu'on le sache même sans journal série.
const char* causeRedemarrage() {
  switch (esp_reset_reason()) {
    case ESP_RST_PANIC: return "un plantage du programme (panic)";
    case ESP_RST_INT_WDT: return "un blocage du programme (chien de garde des interruptions)";
    case ESP_RST_TASK_WDT: return "un blocage du programme (chien de garde des tâches)";
    case ESP_RST_WDT: return "un blocage du programme (chien de garde)";
    case ESP_RST_BROWNOUT: return "une chute de tension de l'alimentation (brownout)";
    default: return nullptr;
  }
}

// Le journal série note chaque nouveau minimum de mémoire libre et de pile restante.
void surveillerMemoire() {
  static uint32_t tasMin = UINT32_MAX, pileMin = UINT32_MAX, dernier = 0;
  if (millis() - dernier < 500) return;
  dernier = millis();
  const uint32_t tas = ESP.getMinFreeHeap(), pile = uxTaskGetStackHighWaterMark(nullptr);
  if (tas < tasMin || pile < pileMin) {
    tasMin = std::min(tasMin, tas);
    pileMin = std::min(pileMin, pile);
    Serial.printf("Mémoire : tas libre au plus bas %u octets (plus grand bloc %u) · pile restante %u octets\n", tasMin,
                  ESP.getMaxAllocHeap(), pileMin);
  }
}

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

// L'écran tactile : l'étalonnage (sur la carte SD, /tactile.json), demandé par l'atelier du
// panneau Wi-Fi ou en gardant le doigt sur l'écran pendant le logo du démarrage.
const char* const CHEMIN_TACTILE = "/tactile.json";
bool etalonnageDemande = false;

void etalonnerTactile() {
  uint16_t p[8];
  ecran.etalonner(p);
  ecran.appliquerEtalonnage(p);
  File f = SD.open(CHEMIN_TACTILE, FILE_WRITE);
  if (f) {
    f.printf("[%u,%u,%u,%u,%u,%u,%u,%u]\n", p[0], p[1], p[2], p[3], p[4], p[5], p[6], p[7]);
    f.close();
  }
  Serial.println("Écran tactile étalonné.");
}

void chargerEtalonnage() {
  File f = SD.open(CHEMIN_TACTILE);
  if (!f) return;
  String t = f.readString();
  f.close();
  uint16_t p[8];
  unsigned v[8];
  if (sscanf(t.c_str(), "[%u,%u,%u,%u,%u,%u,%u,%u]", &v[0], &v[1], &v[2], &v[3], &v[4], &v[5], &v[6], &v[7]) != 8) return;
  for (int i = 0; i < 8; i++) p[i] = static_cast<uint16_t>(v[i]);
  ecran.appliquerEtalonnage(p);
}

// Un toucher (au moment où le doigt se pose) devient un choix de menu, un réglage ou une lettre.
void lireTactile() {
  static uint32_t prochaineLecture = 0;
  static bool appuye = false;
  if (broches::TACTILE_CS < 0 || millis() < prochaineLecture) return;
  prochaineLecture = millis() + 25;
  int x, y;
  if (!ecran.touche(x, y)) {
    appuye = false;
    return;
  }
  if (appuye) return;
  appuye = true;
  pupitre::Toucher t = ecran.cible(moteur.vue(), x, y);
  Serial.printf("Écran tactile : %d, %d (%d)\n", x, y, static_cast<int>(t.type));
  moteur.toucher(t);
}

}  // namespace

void setup() {
  Serial.begin(115200);
  Serial.println("\nLe Pupitre des Énigmes");

  // Toutes les puces du bus SPI désélectionnées avant d'initialiser quoi que ce soit.
  for (int cs : {broches::ECRAN_CS, broches::SD_CS, broches::RFID_CS[0], broches::RFID_CS[1], broches::RFID_CS[2],
                 broches::TACTILE_CS}) {
    if (cs < 0) continue;
    pinMode(cs, OUTPUT);
    digitalWrite(cs, HIGH);
  }
  SPI.begin(broches::SCK, broches::MISO, broches::MOSI);
  ecran.demarrer();
  if (const char* cause = causeRedemarrage()) {
    Serial.printf("Redémarrage précédent : %s\n", cause);
    std::string texte = std::string("Le pupitre a redémarré à cause d'") + cause +
                        ". Notez ce message (ou prenez-le en photo) pour le dépannage.";
    ecran.message("REDÉMARRAGE INATTENDU", texte.c_str());
    delay(6000);
  }
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

  // La durée du logo : le réglage « Logo au démarrage » (/reglages.json), sinon DUREE_LOGO_MS.
  uint32_t dureeLogo = reglages::DUREE_LOGO_MS;
  if (File f = SD.open("/reglages.json")) {
    JsonDocument d;
    if (!deserializeJson(d, f) && d["logo"].is<int>()) dureeLogo = static_cast<uint32_t>(constrain(d["logo"].as<int>(), 0, 15)) * 1000;
    f.close();
  }

  chargerEtalonnage();
  lecteurs.demarrer();
  commandes.demarrer();  // démarre aussi le bus I2C
  capteur.demarrer();
  oeil.demarrer();
  enclume.demarrer();
  forge.demarrer();
  // Le PCA9685 répond aussi à 0x70 tant qu'on ne le lui a pas interdit : avant l'horloge.
  plateforme.servomoteurTiroir().demarrer();
  afficheur.demarrer();
  // Les deux DFPlayer en même temps : on leur laisse jusqu'à 6 s de plus pour inventorier leur
  // carte, une seule fois pour les deux (sans plaque B, le démarrage ne traîne plus 30 s).
  hp.commencer(broches::HP_RX, broches::HP_TX);
  casque.commencer(broches::CASQUE_RX, broches::CASQUE_TX);
  for (int essai = 0; essai < 6 && !(hp.present() && casque.present()); essai++) {
    delay(1000);
    hp.reessayer();
    casque.reessayer();
  }
  hp.finir(defaut.hp);
  casque.finir(defaut.casque);
  if (!hp.present()) Serial.println("DFPlayer haut-parleur absent.");
  if (!casque.present()) Serial.println("DFPlayer casque absent.");

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

  Serial.printf("Démarrage des modules : %lu ms (le logo reste au moins %lu ms)\n", millis() - debutLogo, dureeLogo);
  // Le logo reste le temps choisi dans les réglages (de 0 à 15 s ; 7 s au départ). C'est un
  // minimum : il couvre aussi le démarrage des modules, plus long quand un module manque.
  while (millis() - debutLogo < dureeLogo) delay(10);
  int tx, ty;
  if (ecran.touche(tx, ty)) etalonnerTactile();  // le doigt posé sur l'écran pendant le logo
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
    else if (a == "tactile") etalonnageDemande = true;
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
  lireTactile();
  if (etalonnageDemande) {
    etalonnageDemande = false;
    etalonnerTactile();
  }
  moteur.miseAJour();
  panneau::maj();
  surveillerMemoire();
  delay(2);
}
