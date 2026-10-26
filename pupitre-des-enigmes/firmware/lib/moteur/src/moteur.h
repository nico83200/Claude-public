// Le moteur de jeu du Pupitre des Énigmes.
//
// Il ne connaît aucune mission : il exécute les scripts JSON rangés sur la carte SD
// (voir firmware/SCRIPTS.md). Le matériel lui transmet les cartes posées, les boutons
// et la molette ; il renvoie ce qu'il faut afficher, jouer et allumer.
#pragma once

#include <ArduinoJson.h>

#include <cstdint>
#include <deque>
#include <map>
#include <set>
#include <string>
#include <vector>

#include "plateforme.h"

namespace pupitre {

class Moteur {
 public:
  explicit Moteur(Plateforme& plateforme);

  void demarrer();      // à appeler une fois, carte SD prête
  void miseAJour();     // à appeler en boucle (toutes les 10 ms environ)

  // Entrées du matériel.
  void carte(Zone zone, const std::string& uid);  // uid vide : la carte a été retirée
  void bouton(Bouton bouton);                     // appui
  void boutonsEnfonces(uint8_t masque);           // état courant (bit i = bouton i)
  void molette(int pas);
  void appuiMolette();
  void commande(const std::string& commande);     // panneau Wi-Fi : pause, reprise, temps:+5…

  std::string etatJson();                         // pour le panneau Wi-Fi
  const Vue& vue() const { return vue_; }

  // Pour le simulateur et les tests.
  bool drapeau(const std::string& nom) const { return drapeaux_.count(nom) > 0; }
  int compteur(const std::string& nom) const;
  std::string phaseTexte() const;
  int secondesRestantes() const;
  void avancerHorloge(uint32_t ms) { decalage_ += ms; }
  bool calme() const { return file_.empty() && enAttente_.empty() && attente_ == Attente::RIEN && !ep_.active && !accusationOuverte_; }
  // Une épreuve ou une accusation attend une réponse des joueurs.
  bool attendJoueurs() const {
    return file_.empty() && enAttente_.empty() && attente_ == Attente::RIEN && (ep_.active || accusationOuverte_);
  }
  bool epreuveEnCours() const { return ep_.active; }
  std::string epreuveType() const { return ep_.active ? ep_.type : ""; }
  bool epreuveVert() const { return ep_.vert; }
  int epreuveEtape() const { return ep_.etape; }
  std::vector<int> epreuveSequence() const { return ep_.sequence; }
  std::vector<int> epreuveValeurs() const { return ep_.valeurs; }
  int epreuveCurseur() const { return ep_.curseur; }
  const Reglages& reglages() const { return reglages_; }
  int lumiere() const { return static_cast<int>(lumiere_); }

 private:
  // ---------- Types internes ----------
  enum class Phase { ACCUEIL, CHOIX_MONDE, MENU, ROLES, JEU, FIN, ENROLEMENT, REGLAGES };
  enum class Attente { RIEN, AUDIO, CASQUE, DELAI, MOLETTE, VERT_ROUGE };

  struct Replique {
    std::string texte, voix;
    bool casque = false;
  };

  struct Evenement {
    std::string type;  // lieu, qui, quoi, paire, role, evenement
    std::string lieu, qui, quoi, role, nom;
  };

  // Une action du script (json) ou une action interne (type ≠ AUCUNE).
  struct Action {
    enum Interne { AUCUNE, DIRE, SON, CASQUE_ANNONCE, CASQUE_JOUER, CASQUE_FIN, ATTENDRE, EVENEMENT, ACCUSER, SCORE };
    JsonVariantConst json;
    Interne interne = AUCUNE;
    int a = 0, b = 0;
    std::string s;

    static Action de(Interne type, int a = 0, int b = 0, const std::string& s = "") {
      Action x;
      x.interne = type;
      x.a = a;
      x.b = b;
      x.s = s;
      return x;
    }
  };

  struct Epreuve {
    std::string type;
    JsonVariantConst def;
    uint32_t debut = 0, prochain = 0;
    int etape = 0, curseur = 0, pas = 0, barre = -1, joueur = 0, nbJoueurs = 0, traitre = -1;
    std::vector<int> valeurs, solution, largeurs, sequence;
    std::vector<uint32_t> appuis;
    bool vert = false, fini = false, reussi = false, active = false;
  };

  struct Fenetre {  // pouvoir qui peut annuler une pénalité pendant quelques secondes
    std::string role, cle;  // la carte à poser, et le compteur de pouvoir consommé
    uint32_t fin = 0;
    JsonVariantConst alors;
    int soupconAnnulable = 0;
    bool active = false;
  };

  // ---------- Chargement ----------
  bool chargerJson(const std::string& chemin, JsonDocument& doc);
  void chargerIndex();
  bool chargerMonde(const std::string& id);
  bool chargerMission(int numero);
  void chargerRepliques(const std::string& chemin, std::map<int, Replique>& cible);
  void chargerCartes();
  std::string idCarte(const std::string& uid) const;
  std::string typeCarte(const std::string& id) const;
  std::string nomCarte(const std::string& id) const;
  uint32_t couleur(const char* texte, uint32_t defaut) const;

  // ---------- Phases ----------
  void allerAccueil();
  void allerMenu(bool annoncer = true);
  void afficherChoixMonde();
  void lancerMission(bool reprise);
  void commencerJeu();
  void terminer(bool victoire, const std::string& cause);
  void carteHorsJeu(Zone zone, const std::string& id);
  void carteEnJeu(Zone zone, const std::string& id);
  void enrolement(const std::string& uid);

  // ---------- Dynamo, objectifs ----------
  void majDynamo();
  void majObjectifs();

  // ---------- Réglages du pupitre ----------
  void chargerReglages();
  void ouvrirReglages();
  void reglerValeur(int pas);
  void fermerReglages(bool enregistrer);
  void vueReglages();

  // ---------- Règles et actions ----------
  void declencher(const Evenement& ev);
  bool correspond(JsonVariantConst quand, const Evenement& ev) const;
  bool motif(JsonVariantConst attendu, const std::string& valeur) const;
  bool condition(const char* expression);
  int valeur(const std::string& nom);
  void empiler(JsonVariantConst actions);             // en tête de file, dans l'ordre
  void empilerInterne(const Action& action);
  void ajouterInterne(const Action& action);          // en fin de file
  void traiterFile();
  void executer(JsonVariantConst action);
  void executerInterne(const Action& action);
  void executerSimple(JsonVariantConst action);       // depuis une épreuve en cours
  void dire(int dossier, int numero, bool casqueForce, bool sansTexte, bool attendre);
  void ajouterCompteur(const std::string& nom, int delta);
  void afficherTexte(const std::string& titre, const std::string& texte, const std::string& auteur = "");

  // ---------- Épreuves ----------
  void lancerEpreuve(JsonVariantConst def);
  void majEpreuve();
  void finEpreuve(bool reussi);
  void epreuveBouton(Bouton b);
  void epreuveMolette(int pas);
  void epreuveAppui();
  void epreuveCarte(const std::string& id);
  void vueEpreuve();
  bool energieValide(std::string* regleFausse);

  // ---------- Aide, accusation, score ----------
  void demanderAide();
  void accuser();
  void reponseAccusation(bool confirmer);
  void enregistrerScore();

  // ---------- Temps, vue, lumières, sauvegarde ----------
  uint32_t maintenant() { return p_.maintenant() + decalage_; }
  int secondesEcoulees() const;
  void majChrono();
  void majImprevus();
  void majEntete();
  void majZones();
  void rafraichir() { vue_.version++; }
  void majLumieres();
  void sauvegarder();
  bool restaurer();

  Plateforme& p_;
  Vue vue_;
  Lumieres lumieres_;
  Phase phase_ = Phase::ACCUEIL;
  uint32_t decalage_ = 0;

  JsonDocument index_, monde_, script_, cartesDoc_;
  std::map<int, Replique> repliques_, repliquesPupitre_;
  std::map<std::string, std::string> uidVersId_, mondeParUid_;
  std::vector<std::string> mondes_;
  std::string mondeId_;
  int missionNumero_ = 0, dossierVoix_ = 0, dossierSons_ = 0;

  std::string zones_[3];
  std::set<std::string> drapeaux_, rolesPresents_, imprevusFaits_;
  std::map<std::string, int> compteurs_, pouvoirs_, niveauxAide_;

  std::deque<Action> file_;
  std::deque<Evenement> enAttente_;
  Attente attente_ = Attente::RIEN;
  uint32_t finAttente_ = 0, debutAttente_ = 0;
  int delaiCasque_ = 0;          // secondes laissées pour prendre le casque (0 : illimité)
  bool casqueRate_ = false;      // personne n'a pris le casque à temps
  JsonVariantConst rateCasque_;  // actions à jouer dans ce cas
  std::string titreAvantCasque_;
  Epreuve ep_;
  Fenetre fenetre_;

  // Chrono : secondes de jeu écoulées = (maintenant - debutJeu_ - pauses) / 1000.
  uint32_t debutJeu_ = 0, finJeu_ = 0, debutPause_ = 0, cumulPause_ = 0, derniereSauvegarde_ = 0, derniereSeconde_ = 0;
  int bonusSecondes_ = 0;
  bool pause_ = false, chronoActif_ = false;
  int ambiance_ = 0;
  bool ambianceEnCours_ = false;
  uint32_t silenceDepuis_ = 0;

  // Accusation.
  bool accusationOuverte_ = false;
  bool terminerApres_ = false, defaiteApres_ = false;
  int erreurs_ = 0, indicesDonnes_ = 0, indicesPayants_ = 0;
  uint32_t flashZone_[3] = {0, 0, 0};
  uint32_t couleurZone_[3] = {0, 0, 0};
  uint32_t finEffet_ = 0;
  std::string effet_;
  bool victoire_ = false;

  // Menu et enrôlement des cartes.
  int choixMenu_ = 0;
  std::vector<std::string> optionsMenu_;
  std::vector<std::string> aEnroler_;
  size_t enroleIndex_ = 0;
  uint8_t masqueBoutons_ = 0;

  // Dynamo : la lumière baisse ; un bouton la recharge.
  float lumiere_ = 100.0f;
  float dureeDynamo_ = 0.0f;  // secondes pour passer de 100 à 0 ; 0 : pas de dynamo
  uint32_t derniereDynamo_ = 0;
  int paliersLumiere_ = 10;
  Vue::Ecran ecranAvantTableau_ = Vue::PRINCIPAL;

  // Réglages.
  Reglages reglages_, reglagesAvant_;
  int ligneReglage_ = 0;
  Phase phaseAvantReglages_ = Phase::ACCUEIL;
  uint32_t debutEnsemble_ = 0;
};

}  // namespace pupitre
