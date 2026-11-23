// La sonde : un programme de test du câblage, sans multimètre. Il remplace le programme du
// pupitre le temps des essais (pio run -e sonde -t upload, puis pio device monitor).
//
// La GPIO 15 (broche J3-3 de la plaque A, la réserve) sert de sonde. On y enfiche le bout
// femelle d'un fil Dupont dont l'autre bout est branché sur ce qu'on veut tester : une broche
// d'un rail de D, ou le bout d'un fil de module retiré de son module. Le programme envoie un
// signal sur chaque broche de l'ESP32, l'une après l'autre, et dit laquelle arrive à la sonde :
// « SCK (GPIO 18) » si le fil est bon, « rien » s'il est coupé, deux noms s'il y a un
// court-circuit entre deux lignes.
//
// ⚠️ Jamais la sonde sur le 5 V (rail 5 V, VCC de l'écran, J1, J12) : 5 V abîment l'ESP32.
#include <Arduino.h>

namespace {

constexpr int SONDE = 15;

struct Ligne {
  int gpio;
  const char* nom;
};

// Toutes les broches de l'ESP32 qui partent vers un module (sauf les entrées seules 34, 35, 36,
// 39, qu'on ne peut pas piloter, et la sonde).
const Ligne LIGNES[] = {
    {18, "SCK (GPIO 18) : rail SCK de D, J16-2"},
    {23, "MOSI (GPIO 23) : rail MOSI de D, J16-1"},
    {19, "MISO (GPIO 19) : rail MISO de D, J16-3"},
    {5, "CS de l'écran (GPIO 5) : J2-1"},
    {21, "DC de l'écran (GPIO 21) : J2-3"},
    {12, "LED de l'écran (GPIO 12) : J2-4"},
    {4, "SD_CS (GPIO 4) : J2-5"},
    {14, "T_CS (GPIO 14) : J2-6"},
    {13, "SDA du lecteur de la fente (GPIO 13) : J3-1"},
    {22, "SDA du lecteur à plat (GPIO 22) : J3-2"},
    {17, "Son (GPIO 17) : J14-3"},
    {16, "Son (GPIO 16) : J14-4"},
    {2, "Son (GPIO 2) : J14-5"},
    {26, "SDA de l'I2C (GPIO 26) : J15"},
    {27, "SCL de l'I2C (GPIO 27) : J15"},
    {32, "SW de la molette (GPIO 32) : J8-3"},
    {33, "La clé (GPIO 33) : J8-4"},
    {25, "DIN des LED (GPIO 25, par R3) : J7"},
};

void tout(int niveau) {
  for (const auto& l : LIGNES) digitalWrite(l.gpio, niveau);
}

// Ce que touche la sonde, en une phrase.
String mesurer() {
  // 1. Une ligne à la fois au niveau haut, la sonde tirée vers le bas : qui arrive ?
  pinMode(SONDE, INPUT_PULLDOWN);
  String trouve;
  int n = 0;
  for (const auto& l : LIGNES) {
    tout(LOW);
    digitalWrite(l.gpio, HIGH);
    delayMicroseconds(200);
    if (digitalRead(SONDE) == HIGH) {
      tout(LOW);
      delayMicroseconds(200);
      if (digitalRead(SONDE) == LOW) {  // elle suit la ligne : c'est bien elle
        if (n++) trouve += "\n   + ";
        trouve += l.nom;
      }
    }
  }
  tout(LOW);
  delayMicroseconds(200);
  const bool hautSeul = digitalRead(SONDE) == HIGH;  // haute alors que tout est bas : le 3,3 V
  // 2. Toutes les lignes hautes, la sonde tirée vers le haut : basse = la masse.
  pinMode(SONDE, INPUT_PULLUP);
  tout(HIGH);
  delayMicroseconds(200);
  const bool basSeul = digitalRead(SONDE) == LOW;
  tout(LOW);
  pinMode(SONDE, INPUT_PULLDOWN);

  if (hautSeul) return "le 3,3 V";
  if (basSeul) return "la masse";
  if (n == 0) return "rien (fil coupé, ou soudure manquante)";
  if (n > 1) return "PLUSIEURS LIGNES : court-circuit entre\n   " + trouve;
  return trouve;
}

}  // namespace

void setup() {
  Serial.begin(115200);
  delay(300);
  for (const auto& l : LIGNES) {
    pinMode(l.gpio, OUTPUT);
    digitalWrite(l.gpio, LOW);
  }
  pinMode(SONDE, INPUT_PULLDOWN);
  Serial.println("\n=== La sonde du Pupitre des Énigmes ===");
  Serial.println("Enfichez un fil Dupont sur J3-3 (plaque A) et touchez une broche à tester.");
  Serial.println("Jamais sur le 5 V. Le résultat s'affiche à chaque changement.\n");
}

void loop() {
  static String avant = "?";
  const String m = mesurer();
  if (m != avant) {
    Serial.println("La sonde touche : " + m);
    avant = m;
  }
  delay(250);
}
