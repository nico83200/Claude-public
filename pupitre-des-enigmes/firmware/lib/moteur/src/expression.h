// Petit évaluateur d'expressions pour les conditions des scripts de mission.
//   « J1 && !J3 », « soupcon >= 8 », « m0 + m1 == 5 », « (a || b) && c > 2 »
// Les noms sont résolus par une fonction fournie par l'appelant ; un nom inconnu vaut 0.
#pragma once

#include <functional>
#include <string>

namespace pupitre {

using Resolveur = std::function<int(const std::string& nom)>;

// Renvoie la valeur entière de l'expression (vrai = 1, faux = 0).
// Une expression vide vaut 1 (toujours vraie). Une erreur de syntaxe vaut 0.
int evaluer(const std::string& expression, const Resolveur& valeur, std::string* erreur = nullptr);

}  // namespace pupitre
