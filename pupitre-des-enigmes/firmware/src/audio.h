// Un lecteur DFPlayer Mini : fichiers /DD/NNN.mp3 sur sa carte micro-SD.
#pragma once

#include <Arduino.h>
#include <DFRobotDFPlayerMini.h>

class LecteurAudio {
 public:
  explicit LecteurAudio(HardwareSerial& serie) : serie_(serie) {}

  bool demarrer(int rx, int tx, uint8_t volume);
  void jouer(int dossier, int numero);
  void arreter();
  void volume(int v);  // 0 à 30
  void maj();  // à appeler souvent : lit les messages « piste terminée »
  bool joue() const { return enCours_; }
  bool present() const { return present_; }

 private:
  HardwareSerial& serie_;
  DFRobotDFPlayerMini df_;
  bool present_ = false, enCours_ = false;
  uint32_t debut_ = 0;
  int volume_ = -1;
};
