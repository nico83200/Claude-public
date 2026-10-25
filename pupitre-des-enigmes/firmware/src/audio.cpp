#include "audio.h"

namespace {
constexpr uint32_t DUREE_MAX = 180000;  // sécurité si le message de fin se perd
}

bool LecteurAudio::demarrer(int rx, int tx, uint8_t volume) {
  serie_.begin(9600, SERIAL_8N1, rx, tx);
  present_ = df_.begin(serie_, true, true);
  if (present_) {
    df_.setTimeOut(500);
    df_.volume(volume);
  }
  return present_;
}

void LecteurAudio::jouer(int dossier, int numero) {
  if (!present_) return;
  df_.playFolder(static_cast<uint8_t>(dossier), static_cast<uint8_t>(numero));
  enCours_ = true;
  debut_ = millis();
}

void LecteurAudio::arreter() {
  if (!present_) return;
  df_.stop();
  enCours_ = false;
}

void LecteurAudio::maj() {
  if (!present_) return;
  while (df_.available()) {
    uint8_t type = df_.readType();
    // Le DFPlayer envoie parfois deux fois le message de fin : on ignore celui qui
    // arrive juste après le lancement d'une nouvelle piste.
    if (type == DFPlayerPlayFinished && millis() - debut_ > 400) enCours_ = false;
    if (type == DFPlayerError) enCours_ = false;  // fichier absent, carte retirée…
  }
  if (enCours_ && millis() - debut_ > DUREE_MAX) enCours_ = false;
}
