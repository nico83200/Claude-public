// Interface entre le moteur de jeu (portable) et le matériel.
// Le pupitre ESP32 et le simulateur PC en fournissent chacun une implémentation.
#pragma once

#include <cstdint>
#include <string>
#include <vector>

namespace pupitre {

enum Zone : int { QUI = 0, OU = 1, QUOI = 2 };
enum Bouton : int { ROUGE = 0, BLEU = 1, JAUNE = 2, VERT = 3, GROS = 4 };

// Couleurs au format 0xRRGGBB.
struct Theme {
  uint32_t fond = 0x1f3b2d, texte = 0xf3ead8, accent = 0xc9a54a, alerte = 0xc0392b, doux = 0x9aa79c;
};

// Ce que l'écran doit montrer. Le moteur la remplit, le matériel la dessine.
struct Vue {
  enum Ecran { ACCUEIL, MENU, ROLES, PRINCIPAL, CODE, CHOIX, ENERGIE, EPREUVE, CASQUE, ACCUSATION, RESULTAT, ENROLEMENT, REGLAGES };
  Ecran ecran = ACCUEIL;
  uint32_t version = 0;          // change dès que le corps de l'écran change
  uint32_t versionEntete = 0;    // change quand seule la ligne du haut change (chrono)
  Theme theme;
  std::string monde;             // nom du monde chargé
  std::string entete;            // « 8:14 », « O₂ 61:20 »…
  std::string compteurs;         // « Soupçon 3/12 · ✗ 0/3 · indices 2 »
  std::string titre, texte, auteur, bandeau, info;
  std::string zones[3];          // noms des cartes posées sur QUI, OÙ, QUOI
  std::string options[4];        // choix rouge, bleu, jaune, vert
  int barre = -1;                // option éliminée (pouvoir)
  std::vector<std::string> champs;   // saisie de code
  int curseur = 0;
  std::vector<std::string> modules;  // écrans d'énergie et de réglages
  std::vector<int> valeurs;
  std::vector<int> maximums;               // réglages : valeur maximale de chaque ligne
  std::vector<std::string> etiquettes;     // réglages : « 70 % », « 18 / 30 »
  std::string progression;       // « Écurie ✓  Bureau ·  … »
  bool aideClignote = false;
  int jauge = 0, jaugeMax = 12;  // anneau (pour l'affichage)
};

// Les réglages du pupitre, enregistrés sur la carte SD (/reglages.json).
struct Reglages {
  int ecran = 100;   // luminosité de l'écran, en %
  int hp = 24;       // volume du haut-parleur, 0 à 30
  int casque = 18;   // volume du casque, 0 à 30
  int led = 30;      // luminosité des LED, en %
};

// Les 15 LED : 12 pour l'anneau, puis QUI, OÙ, QUOI.
struct Lumieres {
  uint32_t couleurs[15] = {0};
};

class Plateforme {
 public:
  virtual ~Plateforme() = default;
  virtual uint32_t maintenant() = 0;                                   // millisecondes
  virtual bool lireFichier(const std::string& chemin, std::string& contenu) = 0;
  virtual bool ecrireFichier(const std::string& chemin, const std::string& contenu) = 0;
  virtual void jouer(int dossier, int numero, bool casque) = 0;        // interrompt la piste en cours
  virtual bool joue(bool casque) = 0;                                  // une piste est-elle en cours ?
  virtual void arreter(bool casque) = 0;
  virtual bool audioPresent() = 0;                                     // le lecteur audio répond-il ?
  virtual void afficher(const Vue& vue) = 0;
  virtual void eclairer(const Lumieres& lumieres) = 0;
  virtual uint32_t aleatoire(uint32_t borne) = 0;                      // 0 … borne-1
  virtual void journal(const std::string& message) = 0;
  virtual void appliquer(const Reglages& reglages) { (void)reglages; }    // volumes et luminosités
};

}  // namespace pupitre
