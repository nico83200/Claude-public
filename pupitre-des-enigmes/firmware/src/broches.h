// Câblage du pupitre (voir console/README.md, § 3) et réglages matériels.
#pragma once

#include <cstdint>

namespace broches {

// Bus SPI commun : écran, carte SD de l'écran et les 3 lecteurs RFID.
constexpr int SCK = 18, MOSI = 23, MISO = 19;
constexpr int ECRAN_CS = 5, ECRAN_DC = 21;  // RST de l'écran sur EN
constexpr int SD_CS = 4;
constexpr int RFID_CS[3] = {13, 22, 15};    // QUI, OÙ, QUOI (RST des lecteurs sur 3,3 V)

// Lecteurs audio (DFPlayer Mini) : haut-parleur sur UART2, casque sur UART1.
constexpr int HP_RX = 16, HP_TX = 17;
constexpr int CASQUE_RX = 36, CASQUE_TX = 2;

// LED WS2812B : l'anneau de 12, puis QUI, OÙ, QUOI.
constexpr int LED = 25;
constexpr int NB_LED = 15;

// Molette KY-040 et boutons (actifs à l'état bas).
constexpr int MOLETTE_CLK = 34, MOLETTE_DT = 35, MOLETTE_SW = 32;
constexpr int BOUTONS[5] = {33, 26, 27, 14, 39};  // rouge, bleu, jaune, vert, gros

}  // namespace broches

namespace reglages {

constexpr uint8_t VOLUME_HP = 24;      // 0 à 30
constexpr uint8_t VOLUME_CASQUE = 18;  // 0 à 30
constexpr uint8_t LUMINOSITE_LED = 70; // 0 à 255
constexpr int TRANSITIONS_PAR_CRAN = 4;  // KY-040 : 4 fronts par cran (mettre 2 si la molette saute un cran sur deux)
constexpr uint32_t FREQ_ECRAN = 27000000;

// Le panneau Wi-Fi du maître du jeu : se connecter au réseau, puis ouvrir http://192.168.4.1
constexpr const char* WIFI_NOM = "Pupitre-des-Enigmes";
constexpr const char* WIFI_MOT_DE_PASSE = "enigmes123";  // 8 caractères au moins

}  // namespace reglages
