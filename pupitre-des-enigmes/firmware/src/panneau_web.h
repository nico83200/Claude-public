// Le panneau du maître du jeu : un petit point d'accès Wi-Fi et une page web
// (état de la partie, pause, temps, indice). Facultatif : le jeu s'en passe.
#pragma once

namespace pupitre {
class Moteur;
}

namespace panneau {
void demarrer(pupitre::Moteur& moteur);
void maj();
}  // namespace panneau
