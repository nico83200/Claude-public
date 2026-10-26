// Le panneau du maître du jeu : un point d'accès Wi-Fi et une page web, en six onglets :
// la partie (chrono, pause, temps, aide, déblocage, tiroir), l'équipe et ses prénoms, le
// souffleur (messages, répliques, journal de partie), le classement, l'atelier (essais et
// mesures des capteurs) et la mise à jour du programme. Facultatif : le jeu s'en passe.
#pragma once

#include <functional>
#include <string>

namespace pupitre {
class Moteur;
}

namespace panneau {

// Ce que l'atelier demande au reste du programme (main.cpp, qui connaît tous les modules).
struct Atelier {
  std::function<std::string()> mesures;               // JSON : les capteurs, en direct
  std::function<void(const std::string& essai)> essai;  // « led », « afficheur », « son_hp »…
};

void demarrer(pupitre::Moteur& moteur, const Atelier& atelier);
void maj();

}  // namespace panneau
