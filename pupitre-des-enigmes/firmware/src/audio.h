// Un lecteur DFPlayer Mini : fichiers /DD/NNN.mp3 sur sa carte micro-SD.
#pragma once

#include <Arduino.h>
#include <DFRobotDFPlayerMini.h>

class LecteurAudio {
 public:
  explicit LecteurAudio(HardwareSerial& serie) : serie_(serie) {}

  // Le démarrage en trois temps, pour attendre les deux lecteurs en même temps (main.cpp) :
  // commencer() les réveille, reessayer() les interroge encore (ceux qui inventorient une
  // grosse carte répondent tard), finir() règle le volume de ceux qui ont répondu.
  void commencer(int rx, int tx);
  void reessayer();
  void finir(uint8_t volume);
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
