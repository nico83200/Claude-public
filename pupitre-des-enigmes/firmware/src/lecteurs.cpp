#include "lecteurs.h"

#include <Arduino.h>

#include "broches.h"

namespace {
constexpr uint32_t INTERVALLE = 35;  // ms entre deux lectures (chaque lecteur : ~1 fois sur 3)
constexpr int ABSENCES_AVANT_RETRAIT = 3;
}  // namespace

void Lecteurs::demarrer() {
  for (int i = 0; i < 3; i++) {
    pinMode(broches::RFID_CS[i], OUTPUT);
    digitalWrite(broches::RFID_CS[i], HIGH);
  }
  for (int i = 0; i < 3; i++) {
    MFRC522& l = lecteurs_[i];
    l.PCD_Init(broches::RFID_CS[i], MFRC522::UNUSED_PIN);  // RST sur 3,3 V : réinitialisation logicielle
    byte version = l.PCD_ReadRegister(MFRC522::VersionReg);
    ok_[i] = version != 0x00 && version != 0xFF;
    l.PCD_SetAntennaGain(MFRC522::RxGain_max);
    l.PCD_AntennaOff();
    Serial.printf("Lecteur RFID %d : %s (version 0x%02X)\n", i, ok_[i] ? "OK" : "absent", version);
  }
}

std::string Lecteurs::lire(MFRC522& l) {
  l.PCD_AntennaOn();
  delay(3);  // le temps que la puce de la carte s'alimente
  byte atqa[2];
  byte taille = sizeof(atqa);
  std::string uid;
  // WakeupA réveille aussi une carte déjà lue (mise en veille par HaltA) : on sait
  // ainsi si elle est toujours posée.
  MFRC522::StatusCode s = l.PICC_WakeupA(atqa, &taille);
  if ((s == MFRC522::STATUS_OK || s == MFRC522::STATUS_COLLISION) && l.PICC_ReadCardSerial()) {
    char hex[3];
    for (byte i = 0; i < l.uid.size; i++) {
      snprintf(hex, sizeof(hex), "%02X", l.uid.uidByte[i]);
      uid += hex;
    }
    l.PICC_HaltA();
  }
  l.PCD_AntennaOff();
  return uid;
}

void Lecteurs::maj(const Rappel& rappel) {
  uint32_t t = millis();
  if (t < prochain_) return;
  prochain_ = t + INTERVALLE;
  int z = courant_;
  courant_ = (courant_ + 1) % 3;
  if (!ok_[z]) return;

  std::string uid = lire(lecteurs_[z]);
  if (!uid.empty()) {
    absences_[z] = 0;
    if (uid != uid_[z]) {
      uid_[z] = uid;
      rappel(z, uid);
    }
  } else if (!uid_[z].empty() && ++absences_[z] >= ABSENCES_AVANT_RETRAIT) {
    uid_[z].clear();
    rappel(z, "");
  }
}
