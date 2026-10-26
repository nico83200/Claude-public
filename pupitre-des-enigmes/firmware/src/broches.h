// Câblage du pupitre (voir console/README.md, § 3) et réglages matériels.
#pragma once

#include <cstdint>

namespace broches {

// Bus SPI commun : écran, carte SD de l'écran et les 3 lecteurs RFID.
constexpr int SCK = 18, MOSI = 23, MISO = 19;
constexpr int ECRAN_CS = 5, ECRAN_DC = 21;  // RST de l'écran sur EN
constexpr int ECRAN_LED = 12;               // rétroéclairage (broche LED de l'écran) ; -1 s'il reste sur 3,3 V
constexpr int SD_CS = 4;
// Les lecteurs RFID (RST sur 3,3 V) : la fente à cartes, puis le lecteur à plat sous le
// contreplaqué (un objet posé). Avec trois lecteurs (QUI, OÙ, QUOI), mettre NB_LECTEURS = 3
// et ajouter le troisième CS (15).
constexpr int NB_LECTEURS = 2;
constexpr int RFID_CS[3] = {13, 22, 15};

// Lecteurs audio (DFPlayer Mini) : haut-parleur sur UART2, casque sur UART1.
constexpr int HP_RX = 16, HP_TX = 17;
constexpr int CASQUE_RX = 36, CASQUE_TX = 2;

// LED WS2812B, toutes sur le même fil, dans cet ordre : l'anneau de 12, une LED par lecteur,
// le panneau de 10 voyants, puis le ruban sous la boîte (NB_LED_SOCLE LED, tournées vers la
// table : l'ambiance, que les missions peuvent aussi piloter), et la LED cachée au-dessus du
// tiroir secret, qui l'éclaire quand il sort (0 sans tiroir).
constexpr int LED = 25;
constexpr int NB_VOYANTS = 10;
constexpr int NB_LED_SOCLE = 8;
constexpr int NB_LED_TIROIR = 1;
constexpr int NB_LED = 12 + NB_LECTEURS + NB_VOYANTS + NB_LED_SOCLE + NB_LED_TIROIR;

// Molette KY-040 et gros bouton (la dynamo de la mission 4), actifs à l'état bas.
constexpr int MOLETTE_CLK = 34, MOLETTE_DT = 35, MOLETTE_SW = 32;
constexpr int GROS_BOUTON = 39;  // tirage de 10 kΩ vers 3,3 V (GPIO 39 n'en a pas)
constexpr int CLE = 33;           // l'interrupteur à clé : à la masse quand la clé est tournée

// Bus I2C : le clavier 4×4 et les quatre boutons de couleur (chacun sur un module PCF8574),
// et le capteur de couleur TCS34725 (0x29). Il ne restait plus de GPIO libre : les boutons de
// couleur ont quitté les GPIO 33, 26, 27 et 14, ce qui libère 26 et 27 pour le bus, et laisse
// 14 et 15 en réserve (33 sert à l'interrupteur à clé).
constexpr int I2C_SDA = 26, I2C_SCL = 27;
constexpr uint8_t ADRESSE_CLAVIER = 0x20;  // A0, A1, A2 à la masse (un PCF8574A répond en 0x38)
constexpr uint8_t ADRESSE_BOUTONS = 0x21;  // A0 à 3,3 V (PCF8574A : 0x39)
constexpr int BOUTONS_PCF[4] = {0, 1, 2, 3};  // rouge, bleu, jaune, vert sur P0 à P3

// Le clavier membrane : nappe de 8 fils, lignes sur P0 à P3, colonnes sur P4 à P7.
// Si les touches lues ne correspondent pas aux touches appuyées (journal série), corrigez ici.
// Le capteur de couleur : un objet est « posé » quand la lumière reçue s'écarte nettement de
// celle mesurée à vide (le journal série affiche les mesures, pour régler ces valeurs).
constexpr float CAPTEUR_PLUS_CLAIR = 1.8f, CAPTEUR_PLUS_SOMBRE = 0.5f;  // × la mesure à vide
constexpr float CAPTEUR_BALANCE[3] = {1.0f, 0.82f, 1.25f};               // rouge, vert, bleu (blanc = égaux)

// Les autres modules I2C, facultatifs : l'œil (APDS-9960, 0x39), l'enclume (QMC5883L, 0x0D),
// la forge (BME280, 0x76 ou 0x77) et l'afficheur (HT16K33, 0x70). Le journal série affiche leurs
// mesures : il sert à régler ces valeurs.
constexpr int OEIL_ROTATION = 0;            // 90, 180 ou 270 si les gestes sortent tournés (haut lu « droite »…)
constexpr float OEIL_LAMPE = 3.0f;          // une lampe : 3 × la lumière de la pièce…
constexpr float OEIL_LAMPE_MIN = 1500;      // … et au moins 1 500 de plus
constexpr float ENCLUME_SEUIL = 1500;       // écart du champ magnétique (1 500 ≈ 0,5 gauss) : un aimant
constexpr float FORGE_HUMIDITE = 6.0f;      // un souffle : + 6 points d'humidité…
constexpr float FORGE_TEMPERATURE = 0.8f;   // … ou + 0,8 °C avec un BMP280 (sans humidité)
constexpr uint8_t AFFICHEUR_LUMINOSITE = 8; // 0 à 15

// Le tiroir secret : le servomoteur sur la voie 0 du PCA9685 (0x40), et son contact de fin de
// course (fermé quand le tiroir est au fond) sur la broche P4 du PCF8574 des boutons.
constexpr int TIROIR_VOIE = 0;
constexpr int TIROIR_FERME = 90, TIROIR_OUVERT = 0;  // angles du servomoteur, à régler au montage
constexpr int TIROIR_PCF = 4;

constexpr char TOUCHES[4][4] = {
    {'1', '2', '3', 'A'},
    {'4', '5', '6', 'B'},
    {'7', '8', '9', 'C'},
    {'*', '0', '#', 'D'},
};

}  // namespace broches

// Volumes et luminosités : dans le menu « Réglages du pupitre » (enregistrés sur la
// carte SD, dans /reglages.json). Valeurs par défaut : pupitre::Reglages (plateforme.h).
namespace reglages {

constexpr int TRANSITIONS_PAR_CRAN = 4;  // KY-040 : 4 fronts par cran (mettre 2 si la molette saute un cran sur deux)
constexpr uint32_t FREQ_ECRAN = 27000000;
constexpr uint32_t DUREE_LOGO_MS = 2500;  // le logo au démarrage

// Le panneau Wi-Fi du maître du jeu : se connecter au réseau, puis ouvrir http://192.168.4.1
constexpr const char* WIFI_NOM = "Pupitre-des-Enigmes";
constexpr const char* WIFI_MOT_DE_PASSE = "enigmes123";  // 8 caractères au moins

}  // namespace reglages
