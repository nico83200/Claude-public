// Câblage du pupitre (voir console/README.md, § 3) et réglages matériels.
#pragma once

#include <cstdint>

namespace broches {

// Bus SPI commun : écran, carte SD de l'écran et les 3 lecteurs RFID.
constexpr int SCK = 18, MOSI = 23, MISO = 19;
constexpr int ECRAN_CS = 5, ECRAN_DC = 21;  // RST de l'écran sur EN
constexpr int ECRAN_LED = 12;               // rétroéclairage (broche LED de l'écran) ; -1 s'il reste sur 3,3 V
constexpr int SD_CS = 4;
constexpr int RFID_CS[3] = {13, 22, 15};    // QUI, OÙ, QUOI (RST des lecteurs sur 3,3 V)

// Lecteurs audio (DFPlayer Mini) : haut-parleur sur UART2, casque sur UART1.
constexpr int HP_RX = 16, HP_TX = 17;
constexpr int CASQUE_RX = 36, CASQUE_TX = 2;

// LED WS2812B : l'anneau de 12, puis QUI, OÙ, QUOI.
constexpr int LED = 25;
constexpr int NB_LED = 15;
// Le ruban sous la boîte, tourné vers la table (console/README.md, § 3) :
// - branché en parallèle sur le fil de l'anneau : laisser 0, il répète les premières LED de l'anneau ;
// - branché à la suite des LED des lecteurs : indiquer son nombre de LED, il prend la couleur
//   moyenne de l'anneau (jauge, alertes, réussites…).
constexpr int NB_LED_SOCLE = 0;

// Molette KY-040 et gros bouton (la dynamo de la mission 4), actifs à l'état bas.
constexpr int MOLETTE_CLK = 34, MOLETTE_DT = 35, MOLETTE_SW = 32;
constexpr int GROS_BOUTON = 39;  // tirage de 10 kΩ vers 3,3 V (GPIO 39 n'en a pas)

// Bus I2C : le clavier 4×4 et les quatre boutons de couleur, chacun sur un module PCF8574.
// (Il ne restait plus de GPIO libre : les boutons de couleur ont quitté les GPIO 33, 26, 27
// et 14, ce qui libère 26 et 27 pour le bus, et laisse 14 et 33 en réserve.)
constexpr int I2C_SDA = 26, I2C_SCL = 27;
constexpr uint8_t ADRESSE_CLAVIER = 0x20;  // A0, A1, A2 à la masse (un PCF8574A répond en 0x38)
constexpr uint8_t ADRESSE_BOUTONS = 0x21;  // A0 à 3,3 V (PCF8574A : 0x39)
constexpr int BOUTONS_PCF[4] = {0, 1, 2, 3};  // rouge, bleu, jaune, vert sur P0 à P3

// Le clavier membrane : nappe de 8 fils, lignes sur P0 à P3, colonnes sur P4 à P7.
// Si les touches lues ne correspondent pas aux touches appuyées (journal série), corrigez ici.
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

// Le panneau Wi-Fi du maître du jeu : se connecter au réseau, puis ouvrir http://192.168.4.1
constexpr const char* WIFI_NOM = "Pupitre-des-Enigmes";
constexpr const char* WIFI_MOT_DE_PASSE = "enigmes123";  // 8 caractères au moins

}  // namespace reglages
