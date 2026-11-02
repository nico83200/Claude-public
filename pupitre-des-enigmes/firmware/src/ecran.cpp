#include "ecran.h"

#include "broches.h"

Ecran::Materiel::Materiel() {
  auto b = bus_.config();
  b.spi_host = VSPI_HOST;
  b.spi_mode = 0;
  b.freq_write = reglages::FREQ_ECRAN;
  b.freq_read = 16000000;
  b.spi_3wire = false;
  b.use_lock = true;
  b.dma_channel = SPI_DMA_CH_AUTO;
  b.pin_sclk = broches::SCK;
  b.pin_mosi = broches::MOSI;
  b.pin_miso = broches::MISO;  // celui de la carte SD : le MISO de l'écran n'est pas branché
  b.pin_dc = broches::ECRAN_DC;
  bus_.config(b);
  panneau_.setBus(&bus_);

  auto p = panneau_.config();
  p.pin_cs = broches::ECRAN_CS;
  p.pin_rst = -1;  // sur EN
  p.pin_busy = -1;
  p.panel_width = 320;
  p.panel_height = 480;
  p.readable = false;
  p.bus_shared = true;  // la carte SD et les lecteurs RFID partagent le bus
  p.invert = false;
  p.rgb_order = false;
  panneau_.config(p);

  // Rétroéclairage réglable en MLI (canal 7 du LEDC ; les LED, elles, utilisent le RMT).
  if (broches::ECRAN_LED >= 0) {
    auto l = retroeclairage_.config();
    l.pin_bl = broches::ECRAN_LED;
    l.invert = false;
    l.freq = 12000;
    l.pwm_channel = 7;
    retroeclairage_.config(l);
    panneau_.setLight(&retroeclairage_);
  }
  // La dalle tactile XPT2046, sur le même bus SPI (valeurs brutes typiques ; l'étalonnage les affine).
  if (broches::TACTILE_CS >= 0) {
    auto t = tactile_.config();
    t.spi_host = VSPI_HOST;
    t.freq = 1000000;
    t.pin_sclk = broches::SCK;
    t.pin_mosi = broches::MOSI;
    t.pin_miso = broches::MISO;
    t.pin_cs = broches::TACTILE_CS;
    t.pin_int = -1;
    t.bus_shared = true;
    t.offset_rotation = 0;
    t.x_min = 300;
    t.x_max = 3900;
    t.y_min = 200;
    t.y_max = 3900;
    tactile_.config(t);
    panneau_.setTouch(&tactile_);
  }
  setPanel(&panneau_);
}

bool Ecran::touche(int& x, int& y) {
  if (broches::TACTILE_CS < 0) return false;
  lgfx::touch_point_t p;
  if (lcd_.getTouch(&p, 1) == 0) return false;
  x = p.x;
  y = p.y;
  return x >= 0 && x < 480 && y >= 0 && y < 320;
}

// Quatre cibles dans les coins, à toucher l'une après l'autre (avec la pointe d'un stylo, c'est mieux).
void Ecran::etalonner(uint16_t parametres[8]) {
  lcd_.fillScreen(TFT_BLACK);
  lcd_.setTextColor(TFT_WHITE, TFT_BLACK);
  lcd_.setTextDatum(textdatum_t::middle_center);
  lcd_.drawString("Touchez chaque cible, une par une", 240, 150);
  lcd_.setTextDatum(textdatum_t::top_left);
  lcd_.calibrateTouch(parametres, TFT_WHITE, TFT_BLACK, 20);
  dessin_.toutRedessiner();
}

void Ecran::demarrer() {
  lcd_.init();
  lcd_.setRotation(1);
  lcd_.setTextWrap(false);
  lcd_.setTextDatum(textdatum_t::top_left);
  lcd_.fillScreen(TFT_BLACK);
}
