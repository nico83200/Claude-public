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
  setPanel(&panneau_);
}

void Ecran::demarrer() {
  lcd_.init();
  lcd_.setRotation(1);
  lcd_.setTextWrap(false);
  lcd_.setTextDatum(textdatum_t::top_left);
  lcd_.fillScreen(TFT_BLACK);
}
