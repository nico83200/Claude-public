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
  enum Ecran { ACCUEIL, MENU, ROLES, PRINCIPAL, CODE, CHOIX, ENERGIE, EPREUVE, CASQUE, ACCUSATION, RESULTAT, ENROLEMENT, REGLAGES,
               TABLEAU };
  Ecran ecran = ACCUEIL;
  uint32_t version = 0;          // change dès que le corps de l'écran change
  uint32_t versionEntete = 0;    // change quand seule la ligne du haut change (chrono)
  Theme theme;
  std::string monde;             // nom du monde chargé
  std::string mission;           // titre de la mission en cours
  std::string entete;            // « 8:14 », « O₂ 61:20 »…
  std::string compteurs;         // « Soupçon 3/12 · ✗ 0/3 · indices 2 »
  std::string titre, texte, auteur, bandeau, info;
  std::string zones[3];          // noms des cartes posées sur QUI, OÙ, QUOI
  std::string nomsZones[3] = {"QUI", "OÙ", "QUOI"};  // le nom des lecteurs (la mission peut les renommer)
  std::vector<std::string> menu; // les lignes d'un menu (la ligne choisie : curseur)
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
  int restantPourMille = -1;     // part du temps restant (barre de l'en-tête) ; -1 : pas de chrono
  bool dynamo = false;           // la lumière dépend d'une dynamo à actionner
  int lumiere = 100;             // 0 (noir complet) … 100
  int secondes = -1, secondesTotal = 0;  // épreuve minutée : secondes restantes
  int pasFaits = 0, pasTotal = 0;        // avancée d'une épreuve en plusieurs pas
  int voyant = -1;                       // grand voyant : 0-3 couleur d'un bouton, 4 feu vert, 5 feu rouge
  std::string image;                     // illustration (chemin sur la carte SD) ; vide : pas d'image
  std::string imageDonnees;              // son contenu (JPEG ou PNG), lu par le moteur
  std::vector<std::string> objectifs;    // tableau de bord
  std::vector<int> etatsObjectifs;       // 1 : en cours, 2 : fait
};

// Les réglages du pupitre, enregistrés sur la carte SD (/reglages.json).
struct Reglages {
  int ecran = 100;   // luminosité de l'écran, en %
  int hp = 24;       // volume du haut-parleur, 0 à 30
  int casque = 18;   // volume du casque, 0 à 30
  int led = 30;      // luminosité des LED, en %
};

// Les LED. Sur le pupitre, elles sont chaînées dans cet ordre : l'anneau, une LED par lecteur,
// le panneau de 10 voyants (visible ou caché derrière la façade, selon la mission), puis le
// ruban sous la boîte (toutes ses LED de la même couleur), et enfin la LED du tiroir secret.
constexpr int NB_VOYANTS = 10;
struct Lumieres {
  uint32_t anneau[12] = {0};
  uint32_t lecteurs[3] = {0};  // QUI, OÙ, QUOI ; sur le pupitre à deux lecteurs : la fente, le plateau
  uint32_t voyants[NB_VOYANTS] = {0};
  uint32_t socle = 0;          // le ruban sous la boîte
  uint32_t tiroir = 0;         // la LED cachée au-dessus du tiroir secret (allumée quand il sort)
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
  // Le tiroir secret : son servomoteur libère le tiroir (vrai) ou le verrouille (faux).
  virtual void tiroir(bool ouvrir) { (void)ouvrir; }
  virtual bool tiroirPresent() { return false; }
};

}  // namespace pupitre
