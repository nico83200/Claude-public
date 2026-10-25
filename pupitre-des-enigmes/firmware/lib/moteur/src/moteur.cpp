#include "moteur.h"

#include <algorithm>
#include <cstdio>
#include <cstdlib>

#include "expression.h"

namespace pupitre {

namespace {

const int DOSSIER_VOIX_PUPITRE = 91;
const int DOSSIER_SONS_PUPITRE = 90;
const char* const CHEMIN_SAUVEGARDE = "/sauvegarde.json";

std::string texte(JsonVariantConst v, const char* defaut = "") {
  const char* s = v.as<const char*>();
  return s ? s : defaut;
}

std::string deuxChiffres(int n) {
  char b[8];
  snprintf(b, sizeof b, "%02d", n);
  return b;
}

// « 8:00 » -> 480 minutes.
int minutesDe(const std::string& horaire) {
  int h = 0, m = 0;
  if (sscanf(horaire.c_str(), "%d:%d", &h, &m) == 2) return h * 60 + m;
  return 0;
}

uint32_t melange(uint32_t a, uint32_t b, float t) {
  auto canal = [&](int decalage) {
    int x = (a >> decalage) & 0xff, y = (b >> decalage) & 0xff;
    return static_cast<uint32_t>(x + (y - x) * t) << decalage;
  };
  return canal(16) | canal(8) | canal(0);
}

uint32_t attenuer(uint32_t c, float t) { return melange(0, c, t); }

const uint32_t COULEURS_BOUTONS[4] = {0xd02020, 0x2050e0, 0xe0b000, 0x20b040};
const char* const NOMS_COULEURS[4] = {"ROUGE", "BLEU", "JAUNE", "VERT"};

}  // namespace

Moteur::Moteur(Plateforme& plateforme) : p_(plateforme) {}

// ======================================================================
// Chargement
// ======================================================================

bool Moteur::chargerJson(const std::string& chemin, JsonDocument& doc) {
  std::string contenu;
  if (!p_.lireFichier(chemin, contenu)) {
    p_.journal("Fichier introuvable : " + chemin);
    return false;
  }
  // Les scripts imbriquent conditions, épreuves et actions : on autorise 32 niveaux.
  DeserializationError err = deserializeJson(doc, contenu, DeserializationOption::NestingLimit(32));
  if (err) {
    p_.journal("JSON invalide dans " + chemin + " : " + err.c_str());
    return false;
  }
  return true;
}

void Moteur::chargerRepliques(const std::string& chemin, std::map<int, Replique>& cible) {
  cible.clear();
  JsonDocument doc;
  if (!chargerJson(chemin, doc)) return;
  for (JsonVariantConst l : doc["lignes"].as<JsonArrayConst>()) {
    Replique r;
    r.texte = texte(l["texte"]);
    r.voix = texte(l["voix"]);
    r.casque = texte(l["sortie"]) == "casque";
    cible[l["n"].as<int>()] = r;
  }
}

// Repère toutes les cartes Monde connues, pour reconnaître celle qu'on pose à l'accueil.
void Moteur::chargerIndex() {
  mondes_.clear();
  mondeParUid_.clear();
  index_.clear();
  if (!chargerJson("/mondes/index.json", index_)) return;
  for (JsonVariantConst m : index_["mondes"].as<JsonArrayConst>()) {
    std::string id = texte(m["id"]);
    mondes_.push_back(id);
    JsonDocument monde, cartes;
    if (!chargerJson("/mondes/" + id + "/monde.json", monde)) continue;
    if (!chargerJson("/mondes/" + id + "/cartes.json", cartes)) continue;
    std::string uid = texte(cartes[texte(monde["carte"])]);
    if (!uid.empty()) mondeParUid_[uid] = id;
  }
}

bool Moteur::chargerMonde(const std::string& id) {
  monde_.clear();
  if (!chargerJson("/mondes/" + id + "/monde.json", monde_)) return false;
  mondeId_ = id;
  chargerCartes();
  JsonVariantConst t = monde_["theme"];
  vue_.theme.fond = couleur(t["fond"], vue_.theme.fond);
  vue_.theme.texte = couleur(t["texte"], vue_.theme.texte);
  vue_.theme.accent = couleur(t["accent"], vue_.theme.accent);
  vue_.theme.alerte = couleur(t["alerte"], vue_.theme.alerte);
  vue_.theme.doux = couleur(t["doux"], vue_.theme.doux);
  vue_.monde = texte(monde_["nom"]);
  return true;
}

void Moteur::chargerCartes() {
  cartesDoc_.clear();
  uidVersId_.clear();
  if (!chargerJson("/mondes/" + mondeId_ + "/cartes.json", cartesDoc_)) return;
  for (JsonPairConst kv : cartesDoc_.as<JsonObjectConst>()) {
    std::string uid = texte(kv.value());
    if (!uid.empty()) uidVersId_[uid] = kv.key().c_str();
  }
}

bool Moteur::chargerMission(int numero) {
  for (JsonVariantConst m : monde_["missions"].as<JsonArrayConst>()) {
    if (m["numero"].as<int>() != numero) continue;
    script_.clear();
    std::string dossier = "/mondes/" + mondeId_ + "/";
    if (!chargerJson(dossier + texte(m["script"]), script_)) return false;
    chargerRepliques(dossier + texte(m["repliques"]), repliques_);
    missionNumero_ = numero;
    dossierVoix_ = script_["dossier_voix"].as<int>();
    dossierSons_ = script_["dossier_sons"].as<int>();
    return true;
  }
  return false;
}

std::string Moteur::idCarte(const std::string& uid) const {
  auto it = uidVersId_.find(uid);
  return it == uidVersId_.end() ? "" : it->second;
}

std::string Moteur::typeCarte(const std::string& id) const {
  if (id.empty()) return "";
  if (id == texte(monde_["carte"])) return "monde";
  if (id == texte(monde_["aide"])) return "aide";
  return texte(script_["cartes"][id]["type"]);
}

std::string Moteur::nomCarte(const std::string& id) const {
  if (id.empty()) return "";
  if (id == texte(monde_["carte"])) return vue_.monde;
  std::string nom = texte(script_["cartes"][id]["nom"]);
  return nom.empty() ? id : nom;
}

uint32_t Moteur::couleur(const char* t, uint32_t defaut) const {
  if (!t || t[0] != '#') return defaut;
  return static_cast<uint32_t>(strtoul(t + 1, nullptr, 16));
}

// ======================================================================
// Démarrage et phases
// ======================================================================

void Moteur::demarrer() {
  chargerRepliques("/pupitre/repliques.json", repliquesPupitre_);
  chargerIndex();
  allerAccueil();
}

void Moteur::allerAccueil() {
  phase_ = Phase::ACCUEIL;
  file_.clear();
  enAttente_.clear();
  ep_ = Epreuve();
  attente_ = Attente::RIEN;
  accusationOuverte_ = false;
  chronoActif_ = false;
  ambiance_ = 0;
  vue_ = Vue();
  vue_.ecran = Vue::ACCUEIL;
  vue_.titre = "LE PUPITRE DES ÉNIGMES";
  vue_.texte = "Posez une carte Monde sur l'un des trois lecteurs.";
  vue_.info = "Molette : préparer les cartes d'un monde";
  rafraichir();
  ajouterInterne(Action::de(Action::SON, DOSSIER_SONS_PUPITRE, 1));
  ajouterInterne(Action::de(Action::DIRE, DOSSIER_VOIX_PUPITRE, 1));
  ajouterInterne(Action::de(Action::DIRE, DOSSIER_VOIX_PUPITRE, 2));
}

void Moteur::allerMenu() {
  phase_ = Phase::MENU;
  optionsMenu_.clear();
  for (JsonVariantConst m : monde_["missions"].as<JsonArrayConst>())
    optionsMenu_.push_back("Jouer · Mission " + std::to_string(m["numero"].as<int>()) + " · " + texte(m["titre"]));
  std::string sauvegarde;
  if (p_.lireFichier(CHEMIN_SAUVEGARDE, sauvegarde) && sauvegarde.find("\"" + mondeId_ + "\"") != std::string::npos) {
    optionsMenu_.insert(optionsMenu_.begin(), "Reprendre la partie en cours");
  }
  optionsMenu_.push_back("Enregistrer les cartes de ce monde");
  choixMenu_ = 0;
  vue_.ecran = Vue::MENU;
  vue_.titre = vue_.monde;
  vue_.info = "Installez la façade du monde · Molette : choisir · Appui : valider";
  vue_.texte.clear();
  for (size_t i = 0; i < optionsMenu_.size(); i++)
    vue_.texte += std::string(static_cast<int>(i) == choixMenu_ ? "▶ " : "   ") + optionsMenu_[i] + "\n";
  rafraichir();
  // Le monde se présente : jingle et réplique d'accueil de la première mission.
  JsonVariantConst accueil = monde_["accueil"];
  if (!accueil.isNull()) {
    std::string dossier = "/mondes/" + mondeId_ + "/";
    for (JsonVariantConst m : monde_["missions"].as<JsonArrayConst>()) {
      chargerRepliques(dossier + texte(m["repliques"]), repliques_);
      break;
    }
    ajouterInterne(Action::de(Action::SON, accueil["jingle_dossier"].as<int>(), accueil["jingle"].as<int>()));
    ajouterInterne(Action::de(Action::DIRE, accueil["dossier"].as<int>(), accueil["dire"].as<int>()));
  }
}

void Moteur::lancerMission(bool reprise) {
  drapeaux_.clear();
  compteurs_.clear();
  pouvoirs_.clear();
  niveauxAide_.clear();
  imprevusFaits_.clear();
  rolesPresents_.clear();
  file_.clear();
  enAttente_.clear();
  erreurs_ = indicesDonnes_ = indicesPayants_ = 0;
  bonusSecondes_ = 0;
  cumulPause_ = 0;
  pause_ = false;
  victoire_ = false;
  fenetre_ = Fenetre();
  for (int z = 0; z < 3; z++) zones_[z].clear();
  for (JsonPairConst kv : script_["roles"].as<JsonObjectConst>())
    pouvoirs_[kv.key().c_str()] = kv.value()["pouvoir"].as<int>();
  for (JsonPairConst kv : script_["pouvoirs"].as<JsonObjectConst>()) pouvoirs_[kv.key().c_str()] = kv.value().as<int>();

  if (reprise && restaurer()) {
    phase_ = Phase::JEU;
    chronoActif_ = true;
    vue_.ecran = Vue::PRINCIPAL;
    afficherTexte(texte(script_["titre"]), "Reprise de la partie.");
    ajouterInterne(Action::de(Action::DIRE, DOSSIER_VOIX_PUPITRE, 12));
    if (!script_["reprise"].isNull()) empiler(script_["reprise"]);
    return;
  }

  phase_ = Phase::ROLES;
  vue_.ecran = Vue::ROLES;
  vue_.titre = "POSEZ VOS CARTES RÔLE";
  vue_.info = "Bouton vert : commencer sans attendre tous les rôles";
  rafraichir();
  JsonVariantConst demande = script_["roles_demande"];
  if (!demande.isNull()) ajouterInterne(Action::de(Action::DIRE, dossierVoix_, demande.as<int>()));
}

void Moteur::commencerJeu() {
  phase_ = Phase::JEU;
  chronoActif_ = true;
  debutJeu_ = maintenant();
  finJeu_ = 0;
  cumulPause_ = 0;
  derniereSauvegarde_ = maintenant();
  vue_.ecran = Vue::PRINCIPAL;
  rafraichir();
  empiler(script_["debut"]);
}

void Moteur::terminer(bool victoire, const std::string& cause) {
  if (phase_ == Phase::FIN) return;
  phase_ = Phase::FIN;
  victoire_ = victoire;
  chronoActif_ = false;
  finJeu_ = maintenant();
  if (ep_.active) {
    ep_.active = false;
    p_.arreter(true);
  }
  accusationOuverte_ = false;
  file_.clear();
  enAttente_.clear();
  attente_ = Attente::RIEN;
  ambiance_ = 0;
  p_.ecrireFichier(CHEMIN_SAUVEGARDE, "{}");
  JsonVariantConst fins = script_["fins"];
  if (victoire) {
    effet_ = "victoire";
    finEffet_ = maintenant() + 600000;
    ajouterInterne(Action::de(Action::AUCUNE));
    file_.back().json = fins["victoire"];
    for (JsonVariantConst rang : fins["rangs"].as<JsonArrayConst>()) {
      if (condition(rang["si"])) {
        compteurs_["__rang"] = 1;
        vue_.info = texte(rang["titre"]);
        ajouterInterne(Action::de(Action::DIRE, dossierVoix_, rang["dire"].as<int>()));
        break;
      }
    }
  } else {
    effet_ = "defaite";
    finEffet_ = maintenant() + 600000;
    ajouterInterne(Action::de(Action::AUCUNE));
    file_.back().json = fins[cause];
    ajouterInterne(Action::de(Action::AUCUNE));
    file_.back().json = fins["solution"];
  }
  ajouterInterne(Action::de(Action::SCORE));
}

// ======================================================================
// Entrées
// ======================================================================

void Moteur::carte(Zone zone, const std::string& uid) {
  if (phase_ == Phase::ENROLEMENT) {
    if (!uid.empty()) enrolement(uid);
    return;
  }
  if (uid.empty()) {
    zones_[zone].clear();
    majZones();
    // Retirer la carte Lieu abandonne une épreuve « quitter » (on pourra revenir).
    if (ep_.active && zone == OU && ep_.def["quitter"].as<bool>()) {
      ep_.active = false;
      drapeaux_.erase("__ecoute");
      drapeaux_.erase("__stethoscope");
      p_.arreter(true);
      vue_.ecran = Vue::PRINCIPAL;
      vue_.info.clear();
      vue_.bandeau.clear();
      rafraichir();
    }
    return;
  }
  if (phase_ == Phase::ACCUEIL || phase_ == Phase::CHOIX_MONDE) {
    auto it = mondeParUid_.find(uid);
    if (it != mondeParUid_.end() && chargerMonde(it->second)) {
      allerMenu();
    } else {
      p_.jouer(DOSSIER_SONS_PUPITRE, 3, false);
      ajouterInterne(Action::de(Action::DIRE, DOSSIER_VOIX_PUPITRE, 10));
    }
    return;
  }
  std::string id = idCarte(uid);
  if (id.empty()) {
    flashZone_[zone] = maintenant() + 500;
    couleurZone_[zone] = 0xff0000;
    // Carte d'un autre monde ou carte inconnue.
    bool autreMonde = mondeParUid_.count(uid) > 0;
    p_.jouer(DOSSIER_SONS_PUPITRE, 3, false);
    ajouterInterne(Action::de(Action::DIRE, DOSSIER_VOIX_PUPITRE, autreMonde ? 4 : 10));
    return;
  }
  if (phase_ == Phase::ROLES || phase_ == Phase::MENU || phase_ == Phase::FIN) {
    carteHorsJeu(zone, id);
    return;
  }
  carteEnJeu(zone, id);
}

void Moteur::carteHorsJeu(Zone zone, const std::string& id) {
  if (phase_ != Phase::ROLES || typeCarte(id) != "role") return;
  if (rolesPresents_.insert(id).second) {
    flashZone_[zone] = maintenant() + 500;
    couleurZone_[zone] = 0x00ff00;
    p_.jouer(DOSSIER_SONS_PUPITRE, 2, false);
    JsonVariantConst r = script_["roles"][id];
    ajouterInterne(Action::de(Action::DIRE, dossierVoix_, r["dire"].as<int>()));
    rafraichir();
  }
  if (rolesPresents_.size() == script_["roles"].as<JsonObjectConst>().size()) {
    phase_ = Phase::JEU;  // l'introduction se joue avant que le chrono ne parte
    vue_.ecran = Vue::PRINCIPAL;
    rafraichir();
    // L'introduction passe après l'annonce du dernier rôle arrivé.
    ajouterInterne(Action::de(Action::EVENEMENT, 0, 0, "__intro"));
    ajouterInterne(Action::de(Action::EVENEMENT, 0, 0, "__debut_jeu"));
  }
}

void Moteur::carteEnJeu(Zone zone, const std::string& id) {
  std::string type = typeCarte(id);
  zones_[zone] = id;

  // Une carte posée sur la mauvaise zone est refusée (elle reste affichée, sans effet).
  int zoneAttendue = type == "lieu" ? OU : type == "perso" ? QUI : type == "objet" ? QUOI : -1;
  if (zoneAttendue >= 0 && zoneAttendue != zone) {
    flashZone_[zone] = maintenant() + 500;
    couleurZone_[zone] = 0xff0000;
    p_.jouer(DOSSIER_SONS_PUPITRE, 3, false);
    ajouterInterne(Action::de(Action::DIRE, DOSSIER_VOIX_PUPITRE, 7 + zoneAttendue));
    majZones();
    return;
  }
  flashZone_[zone] = maintenant() + 400;
  couleurZone_[zone] = 0x00ff00;
  p_.jouer(DOSSIER_SONS_PUPITRE, 2, false);
  majZones();

  if (type == "monde") return;
  if (type == "aide") {
    if (!ep_.active && !accusationOuverte_) demanderAide();
    return;
  }

  // Un pouvoir peut annuler une pénalité qui vient de tomber.
  if (type == "role" && fenetre_.active && fenetre_.role == id && maintenant() < fenetre_.fin && pouvoirs_[fenetre_.cle] > 0) {
    fenetre_.active = false;
    pouvoirs_[fenetre_.cle]--;
    if (fenetre_.soupconAnnulable > 0) {
      compteurs_["soupcon"] -= fenetre_.soupconAnnulable;
      JsonVariantConst a = script_["soupcon"]["annulation"];
      ajouterInterne(Action::de(Action::DIRE, dossierVoix_, a["dire"].as<int>()));
    } else {
      empiler(fenetre_.alors);
    }
    return;
  }

  if (ep_.active) {
    epreuveCarte(id);
    return;
  }

  Evenement ev;
  if (type == "role") {
    ev.type = "role";
    ev.role = id;
    if (zone != QUI) ev.qui = zones_[QUI];
    if (zone != OU) ev.lieu = zones_[OU];
    if (zone != QUOI) ev.quoi = zones_[QUOI];
  } else if (type == "lieu") {
    ev.type = "lieu";
    ev.lieu = id;
  } else if (type == "perso" && typeCarte(zones_[QUOI]) == "role") {
    // Une carte Rôle attend déjà sur QUOI : le pouvoir s'applique à ce personnage.
    ev.type = "role";
    ev.role = zones_[QUOI];
    ev.qui = id;
    ev.lieu = zones_[OU];
  } else if (type == "objet" && typeCarte(zones_[QUI]) == "role") {
    // Une carte Rôle attend déjà sur QUI : le pouvoir s'applique à cet objet.
    ev.type = "role";
    ev.role = zones_[QUI];
    ev.quoi = id;
    ev.lieu = zones_[OU];
  } else if (type == "perso") {
    ev.qui = id;
    std::string q = zones_[QUOI];
    if (!q.empty() && typeCarte(q) == "objet") {
      ev.type = "paire";
      ev.quoi = q;
    } else {
      ev.type = "qui";
    }
  } else if (type == "objet") {
    ev.quoi = id;
    std::string q = zones_[QUI];
    if (!q.empty() && typeCarte(q) == "perso") {
      ev.type = "paire";
      ev.qui = q;
    } else {
      ev.type = "quoi";
    }
  } else {
    return;
  }
  enAttente_.push_back(ev);
}

void Moteur::bouton(Bouton b) {
  switch (phase_) {
    case Phase::ACCUEIL:
      return;
    case Phase::CHOIX_MONDE:
      if (b == ROUGE) allerAccueil();
      return;
    case Phase::MENU:
      if (b == ROUGE) allerAccueil();
      if (b == VERT) appuiMolette();
      return;
    case Phase::ROLES:
      if (b == VERT && !rolesPresents_.empty()) {
        // On commence avec les rôles présents : les autres pouvoirs sont perdus.
        for (JsonPairConst kv : script_["roles"].as<JsonObjectConst>())
          if (!rolesPresents_.count(kv.key().c_str())) pouvoirs_[kv.key().c_str()] = 0;
        phase_ = Phase::JEU;
        vue_.ecran = Vue::PRINCIPAL;
        rafraichir();
        // L'introduction passe après l'annonce du dernier rôle arrivé.
        ajouterInterne(Action::de(Action::EVENEMENT, 0, 0, "__intro"));
        ajouterInterne(Action::de(Action::EVENEMENT, 0, 0, "__debut_jeu"));
      }
      return;
    case Phase::ENROLEMENT:
      if (b == ROUGE) {  // passer cette carte
        enroleIndex_++;
        enrolement("");
      } else if (b == VERT) {  // terminer
        enroleIndex_ = aEnroler_.size();
        enrolement("");
      }
      return;
    case Phase::FIN:
      if ((b == VERT || b == GROS) && file_.empty() && attente_ == Attente::RIEN) allerAccueil();
      return;
    case Phase::JEU:
      break;
  }
  if (accusationOuverte_) {
    if (b == VERT) reponseAccusation(true);
    if (b == ROUGE) reponseAccusation(false);
    return;
  }
  if (ep_.active) {
    epreuveBouton(b);
    return;
  }
  if (b == GROS && chronoActif_ && file_.empty() && attente_ == Attente::RIEN) accuser();
}

void Moteur::boutonsEnfonces(uint8_t masque) { masqueBoutons_ = masque; }

void Moteur::molette(int pas) {
  if (phase_ == Phase::CHOIX_MONDE || phase_ == Phase::MENU) {
    int n = static_cast<int>(phase_ == Phase::MENU ? optionsMenu_.size() : mondes_.size());
    if (n == 0) return;
    choixMenu_ = ((choixMenu_ + pas) % n + n) % n;
    vue_.texte.clear();
    for (int i = 0; i < n; i++) {
      std::string nom = phase_ == Phase::MENU ? optionsMenu_[i] : texte(index_["mondes"][i]["nom"], mondes_[i].c_str());
      vue_.texte += std::string(i == choixMenu_ ? "▶ " : "   ") + nom + "\n";
    }
    rafraichir();
    return;
  }
  if (ep_.active) epreuveMolette(pas);
}

void Moteur::appuiMolette() {
  if (attente_ == Attente::MOLETTE) {
    attente_ = Attente::RIEN;
    return;
  }
  switch (phase_) {
    case Phase::ACCUEIL:
      if (mondes_.empty()) return;
      phase_ = Phase::CHOIX_MONDE;
      choixMenu_ = 0;
      vue_.ecran = Vue::MENU;
      vue_.titre = "PRÉPARER UN MONDE";
      vue_.info = "Molette : choisir · Appui : enregistrer ses cartes · Rouge : retour";
      molette(0);
      return;
    case Phase::CHOIX_MONDE: {
      std::string id = mondes_[choixMenu_];
      if (!chargerMonde(id)) return;
      aEnroler_.clear();
      aEnroler_.push_back(texte(monde_["carte"]));
      if (!texte(monde_["aide"]).empty()) aEnroler_.push_back(texte(monde_["aide"]));
      for (JsonVariantConst m : monde_["missions"].as<JsonArrayConst>()) {
        JsonDocument s;
        if (!chargerJson("/mondes/" + mondeId_ + "/" + texte(m["script"]), s)) continue;
        for (JsonPairConst kv : s["cartes"].as<JsonObjectConst>()) {
          std::string cid = kv.key().c_str();
          if (std::find(aEnroler_.begin(), aEnroler_.end(), cid) == aEnroler_.end()) aEnroler_.push_back(cid);
        }
        if (aEnroler_.size() > 2) {
          script_.clear();
          script_.set(s);  // pour connaître les noms des cartes
        }
      }
      enroleIndex_ = 0;
      phase_ = Phase::ENROLEMENT;
      enrolement("");
      return;
    }
    case Phase::MENU: {
      std::string choix = optionsMenu_[choixMenu_];
      if (choix.rfind("Reprendre", 0) == 0) {
        JsonDocument sauv;
        if (chargerJson(CHEMIN_SAUVEGARDE, sauv) && chargerMission(sauv["mission"].as<int>())) lancerMission(true);
      } else if (choix.rfind("Enregistrer", 0) == 0) {
        phase_ = Phase::CHOIX_MONDE;
        choixMenu_ = static_cast<int>(std::find(mondes_.begin(), mondes_.end(), mondeId_) - mondes_.begin());
        appuiMolette();
      } else {
        int numero = atoi(choix.substr(choix.find("Mission ") + 8).c_str());
        if (chargerMission(numero)) lancerMission(false);
      }
      return;
    }
    case Phase::FIN:
      bouton(VERT);
      return;
    default:
      break;
  }
  if (ep_.active) epreuveAppui();
}

void Moteur::commande(const std::string& c) {
  if (c == "pause" && chronoActif_ && !pause_) {
    pause_ = true;
    debutPause_ = maintenant();
    ajouterInterne(Action::de(Action::DIRE, DOSSIER_VOIX_PUPITRE, 11));
  } else if (c == "reprise" && pause_) {
    pause_ = false;
    cumulPause_ += maintenant() - debutPause_;
    ajouterInterne(Action::de(Action::DIRE, DOSSIER_VOIX_PUPITRE, 12));
  } else if (c.rfind("temps:", 0) == 0) {
    bonusSecondes_ += atoi(c.c_str() + 6) * 60;
  } else if (c == "indice" && phase_ == Phase::JEU && !ep_.active) {
    demanderAide();
  } else if (c == "abandon" && phase_ == Phase::JEU) {
    terminer(false, "temps");
  } else if (c == "accueil") {
    allerAccueil();
  }
}

// ======================================================================
// Enrôlement des cartes (associer chaque puce NFC à une carte du jeu)
// ======================================================================

void Moteur::enrolement(const std::string& uid) {
  if (!uid.empty() && enroleIndex_ < aEnroler_.size()) {
    std::string deja;
    for (JsonPairConst kv : cartesDoc_.as<JsonObjectConst>())
      if (texte(kv.value()) == uid && aEnroler_[enroleIndex_] != kv.key().c_str()) deja = kv.key().c_str();
    if (!deja.empty()) {
      vue_.info = "Cette puce est déjà la carte " + deja + " !";
      p_.jouer(DOSSIER_SONS_PUPITRE, 3, false);
      rafraichir();
      return;
    }
    cartesDoc_[aEnroler_[enroleIndex_]] = uid;
    p_.jouer(DOSSIER_SONS_PUPITRE, 2, false);
    enroleIndex_++;
    vue_.info = "Enregistrée.";
  }
  if (enroleIndex_ >= aEnroler_.size()) {
    std::string json;
    serializeJsonPretty(cartesDoc_, json);
    p_.ecrireFichier("/mondes/" + mondeId_ + "/cartes.json", json);
    chargerIndex();
    allerAccueil();
    return;
  }
  const std::string& id = aEnroler_[enroleIndex_];
  vue_.ecran = Vue::ENROLEMENT;
  vue_.titre = "ENREGISTRER LES CARTES · " + vue_.monde;
  vue_.texte = "Posez la carte " + id + "\n" + nomCarte(id);
  vue_.bandeau = std::to_string(enroleIndex_ + 1) + " / " + std::to_string(aEnroler_.size());
  if (uid.empty()) vue_.info = "Rouge : passer cette carte · Vert : terminer et enregistrer";
  rafraichir();
}

// ======================================================================
// Règles
// ======================================================================

bool Moteur::motif(JsonVariantConst attendu, const std::string& valeur) const {
  if (attendu.is<JsonArrayConst>()) {
    for (JsonVariantConst a : attendu.as<JsonArrayConst>())
      if (texte(a) == valeur) return true;
    return false;
  }
  std::string a = texte(attendu);
  if (a == "*") return !valeur.empty();
  if (a == "-") return valeur.empty();
  return a == valeur;
}

bool Moteur::correspond(JsonVariantConst q, const Evenement& ev) const {
  if (!q["evenement"].isNull()) return ev.type == "evenement" && motif(q["evenement"], ev.nom);
  if (!q["role"].isNull()) {
    if (ev.type != "role" || !motif(q["role"], ev.role)) return false;
    if (!q["qui"].isNull() && !motif(q["qui"], ev.qui)) return false;
    if (!q["lieu"].isNull() && !motif(q["lieu"], ev.lieu)) return false;
    if (!q["quoi"].isNull() && !motif(q["quoi"], ev.quoi)) return false;
    return true;
  }
  if (!q["lieu"].isNull()) return ev.type == "lieu" && motif(q["lieu"], ev.lieu);
  bool aQui = !q["qui"].isNull(), aQuoi = !q["quoi"].isNull();
  if (aQui && aQuoi) return ev.type == "paire" && motif(q["qui"], ev.qui) && motif(q["quoi"], ev.quoi);
  if (aQui) return ev.type == "qui" && motif(q["qui"], ev.qui);
  if (aQuoi) return ev.type == "quoi" && motif(q["quoi"], ev.quoi);
  return false;
}

int Moteur::valeur(const std::string& nom) {
  if (nom == "restant") return secondesRestantes() / 60;
  if (nom == "ecoule") return secondesEcoulees() / 60;
  if (nom == "erreurs") return erreurs_;
  if (nom == "indices_payants") return indicesPayants_;
  if (nom == "indices") return indicesDonnes_;
  if (nom.rfind("pouvoir_", 0) == 0) return pouvoirs_[nom.substr(8)];
  if (nom.rfind("qui_", 0) == 0) return zones_[QUI] == nom.substr(4);
  if (nom.rfind("ou_", 0) == 0) return zones_[OU] == nom.substr(3);
  if (nom.rfind("quoi_", 0) == 0) return zones_[QUOI] == nom.substr(5);
  if (nom.rfind("role_", 0) == 0) return rolesPresents_.count(nom.substr(5)) ? 1 : 0;
  auto c = compteurs_.find(nom);
  if (c != compteurs_.end()) return c->second;
  return drapeaux_.count(nom) ? 1 : 0;
}

bool Moteur::condition(const char* expression) {
  if (!expression) return true;
  std::string erreur;
  int v = evaluer(expression, [this](const std::string& n) { return valeur(n); }, &erreur);
  if (!erreur.empty()) p_.journal(std::string("Condition invalide « ") + expression + " » : " + erreur);
  return v != 0;
}

// Même lecture que dans les conditions (compteurs, pouvoir_X, indices, erreurs…).
int Moteur::compteur(const std::string& nom) const { return const_cast<Moteur*>(this)->valeur(nom); }

void Moteur::declencher(const Evenement& ev) {
  if (ev.type == "evenement" && ev.nom == "__debut_jeu") {
    commencerJeu();
    return;
  }
  if (ev.type == "evenement" && ev.nom == "__intro") {
    empiler(script_["intro"]);
    return;
  }
  if (ev.type == "lieu" || ev.type == "qui" || ev.type == "quoi" || ev.type == "paire") {
    vue_.bandeau.clear();
    vue_.ecran = Vue::PRINCIPAL;
    if (ev.type == "lieu") vue_.titre = nomCarte(ev.lieu);
    else if (ev.type == "qui") vue_.titre = nomCarte(ev.qui);
    else if (ev.type == "quoi") vue_.titre = nomCarte(ev.quoi);
    else vue_.titre = nomCarte(ev.qui) + "  ·  " + nomCarte(ev.quoi);
    rafraichir();
  }
  for (JsonVariantConst r : script_["regles"].as<JsonArrayConst>()) {
    if (!correspond(r["quand"], ev)) continue;
    if (!condition(r["si"])) continue;
    empiler(r["alors"]);
    return;
  }
  JsonVariantConst defaut = script_["defaut"][ev.type];
  if (!defaut.isNull()) empiler(defaut);
  else if (ev.type != "evenement") ajouterInterne(Action::de(Action::DIRE, dossierVoix_, 11));
}

// ======================================================================
// File d'actions
// ======================================================================

void Moteur::empiler(JsonVariantConst actions) {
  if (actions.isNull()) return;
  if (actions.is<JsonArrayConst>()) {
    std::vector<JsonVariantConst> liste;
    for (JsonVariantConst x : actions.as<JsonArrayConst>()) liste.push_back(x);
    for (auto it = liste.rbegin(); it != liste.rend(); ++it) {
      Action act;
      act.json = *it;
      file_.push_front(act);
    }
  } else {
    Action act;
    act.json = actions;
    file_.push_front(act);
  }
}

void Moteur::empilerInterne(const Action& action) { file_.push_front(action); }
void Moteur::ajouterInterne(const Action& action) { file_.push_back(action); }

void Moteur::traiterFile() {
  for (int garde = 0; garde < 64; garde++) {
    if (attente_ != Attente::RIEN) {
      uint32_t t = maintenant();
      bool fini = false;
      switch (attente_) {
        case Attente::AUDIO:
        case Attente::CASQUE: {
          bool casque = attente_ == Attente::CASQUE;
          fini = (t >= finAttente_ && !p_.joue(casque)) || t - debutAttente_ > 45000;
          break;
        }
        case Attente::DELAI:
          fini = t >= finAttente_;
          break;
        case Attente::MOLETTE:
          if (finAttente_ && t >= finAttente_) {  // personne n'a pris le casque à temps
            casqueRate_ = true;
            fini = true;
          }
          break;
        default:
          fini = true;
      }
      if (!fini) return;
      attente_ = Attente::RIEN;
    }
    if (ep_.active) {
      majEpreuve();
      if (ep_.active) return;
      continue;
    }
    if (accusationOuverte_) return;
    if (file_.empty()) {
      if (!enAttente_.empty() && phase_ == Phase::JEU) {
        Evenement ev = enAttente_.front();
        enAttente_.pop_front();
        declencher(ev);
        continue;
      }
      return;
    }
    Action a = file_.front();
    file_.pop_front();
    if (a.interne != Action::AUCUNE) executerInterne(a);
    else executer(a.json);
  }
}

void Moteur::afficherTexte(const std::string& titre, const std::string& t, const std::string& auteur) {
  if (phase_ == Phase::JEU && vue_.ecran != Vue::PRINCIPAL) vue_.ecran = Vue::PRINCIPAL;
  if (!titre.empty()) vue_.titre = titre;
  vue_.texte = t;
  vue_.auteur = auteur;
  rafraichir();
}

void Moteur::dire(int dossier, int numero, bool casqueForce, bool sansTexte, bool attendre) {
  const std::map<int, Replique>& source = dossier == DOSSIER_VOIX_PUPITRE ? repliquesPupitre_ : repliques_;
  auto it = source.find(numero);
  Replique r = it == source.end() ? Replique() : it->second;
  if (it == source.end()) p_.journal("Réplique absente : " + std::to_string(dossier) + "/" + std::to_string(numero));
  bool casque = r.casque || casqueForce;
  if (casque) {
    // Annonce au haut-parleur, attente d'un joueur au casque, message, puis fin.
    empilerInterne(Action::de(Action::CASQUE_FIN));
    empilerInterne(Action::de(Action::CASQUE_JOUER, dossier, numero));
    empilerInterne(Action::de(Action::CASQUE_ANNONCE));
    return;
  }
  if (!sansTexte && !r.texte.empty()) {
    std::string auteur = texte(script_["voix"][r.voix]);
    if (dossier == DOSSIER_VOIX_PUPITRE) auteur.clear();
    afficherTexte("", r.texte, auteur);
  }
  p_.jouer(dossier, numero, false);
  ambianceEnCours_ = false;
  silenceDepuis_ = 0;
  if (!attendre) return;
  attente_ = Attente::AUDIO;
  debutAttente_ = maintenant();
  // Sans lecteur audio, on laisse le temps de lire le sous-titre.
  uint32_t minimum = p_.audioPresent() ? 600 : 900 + 45 * static_cast<uint32_t>(r.texte.size());
  finAttente_ = debutAttente_ + minimum;
}

void Moteur::executerInterne(const Action& a) {
  switch (a.interne) {
    case Action::DIRE:
      dire(a.a, a.b, false, false, true);
      break;
    case Action::SON:
      p_.jouer(a.a, a.b, false);
      ambianceEnCours_ = false;
      attente_ = Attente::AUDIO;
      debutAttente_ = maintenant();
      finAttente_ = debutAttente_ + 300;
      break;
    case Action::CASQUE_ANNONCE:
      casqueRate_ = false;
      titreAvantCasque_ = vue_.titre;
      vue_.ecran = Vue::CASQUE;
      vue_.titre = "MESSAGE PRIVÉ";
      vue_.texte = "Un seul joueur met le casque, puis appuie sur la molette.";
      rafraichir();
      p_.jouer(DOSSIER_SONS_PUPITRE, 6, false);
      empilerInterne(Action::de(Action::ATTENDRE, 0, 0, "molette"));
      empilerInterne(Action::de(Action::DIRE, DOSSIER_VOIX_PUPITRE, 5));
      break;
    case Action::ATTENDRE:
      if (a.s == "molette") {
        attente_ = Attente::MOLETTE;
        finAttente_ = delaiCasque_ ? maintenant() + delaiCasque_ * 1000 : 0;
        vue_.ecran = Vue::CASQUE;
        rafraichir();
      } else {
        attente_ = Attente::DELAI;
        finAttente_ = maintenant() + a.a;
      }
      break;
    case Action::CASQUE_JOUER:
      if (casqueRate_) {
        empiler(rateCasque_);
        break;
      }
      vue_.texte = "🎧 Écoute en cours…";
      rafraichir();
      p_.jouer(a.a, a.b, true);
      attente_ = Attente::CASQUE;
      debutAttente_ = maintenant();
      finAttente_ = debutAttente_ + (p_.audioPresent() ? 600 : 4000);
      break;
    case Action::CASQUE_FIN:
      delaiCasque_ = 0;
      vue_.bandeau.clear();
      vue_.ecran = Vue::PRINCIPAL;
      vue_.titre = titreAvantCasque_;
      rafraichir();
      if (!casqueRate_) dire(DOSSIER_VOIX_PUPITRE, 6, false, false, true);
      casqueRate_ = false;
      rateCasque_ = JsonVariantConst();
      break;
    case Action::EVENEMENT: {
      Evenement ev;
      ev.type = "evenement";
      ev.nom = a.s;
      declencher(ev);
      break;
    }
    case Action::ACCUSER:
      accuser();
      break;
    case Action::SCORE:
      enregistrerScore();
      break;
    case Action::AUCUNE:
      executer(a.json);
      break;
  }
}

void Moteur::ajouterCompteur(const std::string& nom, int delta) {
  int avant = compteurs_[nom];
  compteurs_[nom] += delta;
  if (nom != "soupcon") return;
  JsonVariantConst s = script_["soupcon"];
  if (compteurs_[nom] < 0) compteurs_[nom] = 0;
  // Un avertissement quand la jauge franchit le seuil d'alerte.
  JsonVariantConst alerte = s["alerte"];
  int seuil = alerte["seuil"].as<int>();
  if (seuil > 0 && avant < seuil && compteurs_[nom] >= seuil && compteurs_[nom] < s["max"].as<int>())
    ajouterInterne(Action::de(Action::DIRE, dossierVoix_, alerte["dire"].as<int>()));
  if (delta > 0) {
    effet_ = "echec";
    finEffet_ = maintenant() + 600;
    JsonVariantConst a = s["annulation"];
    std::string role = texte(a["role"]);
    if (!role.empty() && pouvoirs_[role] > 0) {
      fenetre_ = Fenetre();
      fenetre_.active = true;
      fenetre_.role = role;
      fenetre_.cle = role;
      fenetre_.fin = maintenant() + a["secondes"].as<int>() * 1000;
      fenetre_.soupconAnnulable = delta;
    }
  }
  int max = s["max"].as<int>();
  if (max > 0 && compteurs_[nom] >= max) terminer(false, "soupcon");
}

void Moteur::executer(JsonVariantConst act) {
  if (act.isNull()) return;
  if (act.is<JsonArrayConst>()) {
    empiler(act);
    return;
  }
  if (!act["si"].isNull()) {
    empiler(condition(act["si"]) ? act["alors"] : act["sinon"]);
    return;
  }
  if (!act["dire"].isNull()) {
    int dossier = act["pupitre"].as<bool>() ? DOSSIER_VOIX_PUPITRE : dossierVoix_;
    bool casque = act["casque"].as<bool>();
    if (act["dire"].is<JsonArrayConst>()) {
      JsonArrayConst l = act["dire"].as<JsonArrayConst>();
      std::vector<int> n;
      for (JsonVariantConst x : l) n.push_back(x.as<int>());
      for (auto it = n.rbegin(); it != n.rend(); ++it) empilerInterne(Action::de(Action::DIRE, dossier, *it));
      return;
    }
    if (act["unique"].as<bool>()) vue_.bandeau = "⚠ Ce message ne passera qu'une fois.";
    if (!act["delai"].isNull()) delaiCasque_ = act["delai"].as<int>();
    if (!act["rate"].isNull()) rateCasque_ = act["rate"];
    dire(dossier, act["dire"].as<int>(), casque, act["sans_texte"].as<bool>(), true);
    return;
  }
  if (!act["son"].isNull()) {
    int dossier = act["pupitre"].as<bool>() ? DOSSIER_SONS_PUPITRE : dossierSons_;
    if (!act["dossier"].isNull()) dossier = act["dossier"].as<int>();
    bool casque = act["casque"].as<bool>();
    p_.jouer(dossier, act["son"].as<int>(), casque);
    if (!casque) ambianceEnCours_ = false;
    if (!act["fond"].as<bool>()) {
      attente_ = casque ? Attente::CASQUE : Attente::AUDIO;
      debutAttente_ = maintenant();
      finAttente_ = debutAttente_ + 300;
    }
    return;
  }
  if (!act["ambiance"].isNull()) {
    ambiance_ = act["ambiance"].as<int>();
    if (ambiance_ == 0 && ambianceEnCours_) p_.arreter(false);
    ambianceEnCours_ = false;
    return;
  }
  if (!act["titre"].isNull() || !act["texte"].isNull()) {
    afficherTexte(texte(act["titre"]), texte(act["texte"]));
    return;
  }
  if (!act["enveloppe"].isNull()) {
    vue_.bandeau = "✉  OUVREZ L'ENVELOPPE " + texte(act["enveloppe"]);
    drapeaux_.insert("enveloppe_" + texte(act["enveloppe"]));
    rafraichir();
    return;
  }
  if (!act["drapeau"].isNull() || !act["retirer"].isNull()) {
    auto appliquer = [&](JsonVariantConst v, bool mettre) {
      if (v.isNull()) return;
      if (v.is<JsonArrayConst>()) {
        for (JsonVariantConst x : v.as<JsonArrayConst>()) mettre ? (void)drapeaux_.insert(texte(x)) : (void)drapeaux_.erase(texte(x));
      } else {
        mettre ? (void)drapeaux_.insert(texte(v)) : (void)drapeaux_.erase(texte(v));
      }
    };
    appliquer(act["drapeau"], true);
    appliquer(act["retirer"], false);
    vue_.progression.clear();
    rafraichir();
    return;
  }
  if (!act["ajouter"].isNull()) {
    for (JsonPairConst kv : act["ajouter"].as<JsonObjectConst>()) ajouterCompteur(kv.key().c_str(), kv.value().as<int>());
    return;
  }
  if (!act["fixer"].isNull()) {
    for (JsonPairConst kv : act["fixer"].as<JsonObjectConst>()) compteurs_[kv.key().c_str()] = kv.value().as<int>();
    return;
  }
  if (!act["temps"].isNull()) {
    int minutes = act["temps"].as<int>();
    bonusSecondes_ += minutes * 60;
    effet_ = minutes < 0 ? "echec" : "succes";
    finEffet_ = maintenant() + 600;
    return;
  }
  if (!act["pouvoir"].isNull()) {
    std::string r = texte(act["pouvoir"]);
    if (pouvoirs_[r] > 0) pouvoirs_[r]--;
    return;
  }
  if (!act["epreuve"].isNull()) {
    lancerEpreuve(act["epreuve"]);
    return;
  }
  if (!act["evenement"].isNull()) {
    Evenement ev;
    ev.type = "evenement";
    ev.nom = texte(act["evenement"]);
    declencher(ev);
    return;
  }
  if (!act["victoire"].isNull()) {
    terminer(true, "");
    return;
  }
  if (!act["defaite"].isNull()) {
    terminer(false, texte(act["defaite"]));
    return;
  }
  if (!act["attendre"].isNull()) {
    attente_ = Attente::DELAI;
    finAttente_ = maintenant() + act["attendre"].as<int>();
    return;
  }
  if (!act["annulable"].isNull()) {
    JsonVariantConst a = act["annulable"];
    std::string role = texte(a["role"]);
    std::string cle = texte(a["pouvoir"], role.c_str());
    if (pouvoirs_[cle] > 0) {
      fenetre_ = Fenetre();
      fenetre_.active = true;
      fenetre_.role = role;
      fenetre_.cle = cle;
      fenetre_.fin = maintenant() + a["secondes"].as<int>() * 1000;
      fenetre_.alors = a["alors"];
    }
    return;
  }
  if (!act["hasard"].isNull()) {
    JsonArrayConst choix = act["hasard"].as<JsonArrayConst>();
    if (choix.size()) empiler(choix[p_.aleatoire(static_cast<uint32_t>(choix.size()))]);
    return;
  }
  if (!act["effet"].isNull()) {
    effet_ = texte(act["effet"]);
    finEffet_ = maintenant() + (act["duree"].isNull() ? 2000 : act["duree"].as<int>());
    return;
  }
  if (!act["vue"].isNull()) {
    vue_.ecran = Vue::PRINCIPAL;
    rafraichir();
    return;
  }
  p_.journal("Action inconnue dans le script.");
}

// Action lancée pendant une épreuve : jamais bloquante.
void Moteur::executerSimple(JsonVariantConst act) {
  if (act.isNull()) return;
  if (act.is<JsonArrayConst>()) {
    for (JsonVariantConst x : act.as<JsonArrayConst>()) executerSimple(x);
    return;
  }
  if (!act["dire"].isNull()) {
    int dossier = act["pupitre"].as<bool>() ? DOSSIER_VOIX_PUPITRE : dossierVoix_;
    dire(dossier, act["dire"].as<int>(), false, true, false);
    const std::map<int, Replique>& source = dossier == DOSSIER_VOIX_PUPITRE ? repliquesPupitre_ : repliques_;
    auto it = source.find(act["dire"].as<int>());
    if (it != source.end()) vue_.info = it->second.texte;
    rafraichir();
  } else if (!act["son"].isNull()) {
    int dossier = act["pupitre"].as<bool>() ? DOSSIER_SONS_PUPITRE : dossierSons_;
    p_.jouer(dossier, act["son"].as<int>(), act["casque"].as<bool>());
  } else if (!act["ajouter"].isNull()) {
    for (JsonPairConst kv : act["ajouter"].as<JsonObjectConst>()) ajouterCompteur(kv.key().c_str(), kv.value().as<int>());
  } else if (!act["temps"].isNull()) {
    bonusSecondes_ += act["temps"].as<int>() * 60;
  } else if (!act["drapeau"].isNull()) {
    drapeaux_.insert(texte(act["drapeau"]));
  } else if (!act["pouvoir"].isNull()) {
    std::string r = texte(act["pouvoir"]);
    if (pouvoirs_[r] > 0) pouvoirs_[r]--;
  } else if (!act["effet"].isNull()) {
    effet_ = texte(act["effet"]);
    finEffet_ = maintenant() + 800;
  }
}

// ======================================================================
// Épreuves
// ======================================================================

void Moteur::lancerEpreuve(JsonVariantConst def) {
  ep_ = Epreuve();
  ep_.type = texte(def["type"]);
  ep_.def = def;
  ep_.active = true;
  ep_.debut = maintenant();
  debutEnsemble_ = 0;
  if (ep_.type == "code" || ep_.type == "lettres") {
    std::string sol = texte(def["solution"]);
    if (def["champs"].is<JsonArrayConst>()) {
      for (JsonVariantConst w : def["champs"].as<JsonArrayConst>()) ep_.largeurs.push_back(w.as<int>());
    } else {
      ep_.largeurs.assign(sol.size(), 1);
    }
    size_t pos = 0;
    for (int w : ep_.largeurs) {
      std::string morceau = sol.substr(pos, w);
      pos += w;
      ep_.solution.push_back(ep_.type == "lettres" ? morceau[0] - 'A' : atoi(morceau.c_str()));
    }
    ep_.valeurs.assign(ep_.largeurs.size(), -1);
  } else if (ep_.type == "energie") {
    ep_.valeurs.assign(def["modules"].as<JsonArrayConst>().size(), 0);
  } else if (ep_.type == "molette_zero") {
    ep_.valeurs = {def["depart"].isNull() ? 40 : def["depart"].as<int>()};
  } else if (ep_.type == "rondes") {
    ep_.vert = false;
    ep_.prochain = maintenant() + 1500;
  } else if (ep_.type == "sequence") {
    int n = def["longueur"].isNull() ? 5 : def["longueur"].as<int>();
    for (int i = 0; i < n; i++) ep_.sequence.push_back(static_cast<int>(p_.aleatoire(4)));
    ep_.etape = 0;  // 0 : démonstration, 1 : saisie
    ep_.pas = -1;
    ep_.prochain = maintenant() + 800;
  } else if (ep_.type == "rythme") {
    ep_.etape = 0;
    ep_.pas = 0;
    ep_.prochain = maintenant() + 600;
  } else if (ep_.type == "traitre") {
    ep_.valeurs = {4};
    ep_.etape = 0;
  }
  executerSimple(def["intro"]);
  JsonVariantConst boucle = def["boucle_casque"];
  if (!boucle.isNull()) p_.jouer(boucle["dossier"].as<int>(), boucle["n"].as<int>(), true);
  vueEpreuve();
}

void Moteur::finEpreuve(bool reussi) {
  if (!ep_.active) return;
  ep_.active = false;
  ep_.fini = true;
  ep_.reussi = reussi;
  if (!ep_.def["boucle_casque"].isNull() || drapeaux_.count("__ecoute")) p_.arreter(true);
  drapeaux_.erase("__ecoute");
  drapeaux_.erase("__stethoscope");
  effet_ = reussi ? "succes" : "echec";
  finEffet_ = maintenant() + 700;
  vue_.ecran = Vue::PRINCIPAL;
  vue_.info.clear();
  rafraichir();
  empiler(ep_.def[reussi ? "reussite" : "echec"]);
}

bool Moteur::energieValide(std::string* regleFausse) {
  int somme = 0;
  for (int v : ep_.valeurs) somme += v;
  auto lire = [&](const std::string& n) {
    if (n == "somme") return somme;
    if (n.size() >= 2 && n[0] == 'm') {
      size_t i = atoi(n.c_str() + 1);
      if (i < ep_.valeurs.size()) return ep_.valeurs[i];
    }
    return 0;
  };
  for (JsonVariantConst r : ep_.def["regles"].as<JsonArrayConst>()) {
    if (!evaluer(texte(r["expr"]), lire)) {
      if (regleFausse) *regleFausse = texte(r["texte"]);
      return false;
    }
  }
  return true;
}

void Moteur::majEpreuve() {
  uint32_t t = maintenant();
  JsonVariantConst d = ep_.def;
  int secondes = d["secondes"].as<int>();
  bool expire = secondes > 0 && t - ep_.debut > static_cast<uint32_t>(secondes) * 1000;

  if (ep_.type == "immobile") {
    if (expire) finEpreuve(true);
  } else if (ep_.type == "ensemble") {
    if ((masqueBoutons_ & 0x0f) == 0x0f) {
      if (!debutEnsemble_) debutEnsemble_ = t;
      if (t - debutEnsemble_ >= static_cast<uint32_t>(d["tenir"].as<int>()) * 1000) {
        finEpreuve(true);
        return;
      }
    } else {
      debutEnsemble_ = 0;
    }
    if (expire) finEpreuve(false);
  } else if (ep_.type == "molette_zero" || ep_.type == "choix") {
    if (expire) finEpreuve(false);
  } else if (ep_.type == "rondes") {
    if (t >= ep_.prochain) {
      ep_.vert = !ep_.vert;
      ep_.prochain = t + (ep_.vert ? 1200 + p_.aleatoire(1500) : 900 + p_.aleatoire(1600));
    }
  } else if (ep_.type == "sequence") {
    if (ep_.etape == 0 && t >= ep_.prochain) {
      // Démonstration : chaque couleur s'allume 600 ms, puis 250 ms d'obscurité.
      if (ep_.pas >= 0 && ep_.vert) {
        ep_.vert = false;
        ep_.prochain = t + 250;
      } else {
        ep_.pas++;
        if (ep_.pas >= static_cast<int>(ep_.sequence.size())) {
          ep_.etape = 1;
          ep_.pas = 0;
          ep_.debut = t;
        } else {
          ep_.vert = true;
          ep_.prochain = t + 600;
        }
      }
      vueEpreuve();
    }
    if (ep_.etape == 1 && t - ep_.debut > 20000) finEpreuve(false);
  } else if (ep_.type == "rythme") {
    // Démonstration : le motif joué deux fois, puis les joueurs le reproduisent.
    JsonArrayConst motif = d["motif"].as<JsonArrayConst>();
    int n = static_cast<int>(motif.size());
    if (ep_.etape == 0 && t >= ep_.prochain) {
      if (ep_.pas >= 2 * n) {
        ep_.etape = 1;
        ep_.debut = t;
        vueEpreuve();
      } else {
        p_.jouer(dossierSons_, d["son"].as<int>(), false);
        int i = ep_.pas % n;
        int suivant = (ep_.pas + 1) % n;
        int ecart = suivant == 0 ? 900 : motif[suivant].as<int>() - motif[i].as<int>();
        ep_.pas++;
        ep_.prochain = t + ecart;
      }
    }
    if (ep_.etape == 1 && t - ep_.debut > 30000) finEpreuve(false);
  } else if (ep_.type == "code" || ep_.type == "lettres") {
    if (expire) {
      finEpreuve(false);
      return;
    }
    JsonVariantConst boucle = d["boucle_casque"];
    if ((!boucle.isNull() || drapeaux_.count("__ecoute")) && !p_.joue(true)) {
      JsonVariantConst src = boucle.isNull() ? d["aides"][texte(d["ecoute_role"])] : boucle;
      p_.jouer(src["dossier"].as<int>(), src["n"].as<int>(), true);
    }
  } else if (ep_.type == "traitre") {
    if (ep_.etape == 2 && !p_.joue(true) && t - ep_.prochain > 600) {
      ep_.joueur++;
      if (ep_.joueur >= ep_.nbJoueurs) {
        finEpreuve(true);
        return;
      }
      dire(dossierVoix_, d["suivant"].as<int>(), false, true, false);
      ep_.etape = 1;
      vueEpreuve();
    }
  }
  if (ep_.active && secondes > 0) {
    int reste = secondes - static_cast<int>((t - ep_.debut) / 1000);
    std::string info = "⏱ " + std::to_string(std::max(0, reste)) + " s";
    if (info != vue_.info && ep_.type != "energie") {
      vue_.info = info;
      rafraichir();
    }
  }
}

void Moteur::epreuveBouton(Bouton b) {
  JsonVariantConst d = ep_.def;
  if (ep_.type == "immobile") {
    finEpreuve(false);
  } else if (ep_.type == "code" || ep_.type == "lettres") {
    if (b == ROUGE) {
      if (ep_.valeurs[ep_.curseur] < 0 && ep_.curseur > 0) ep_.curseur--;
      ep_.valeurs[ep_.curseur] = -1;
      vueEpreuve();
    } else if (b == VERT) {
      epreuveAppui();
    }
  } else if (ep_.type == "choix") {
    if (b > VERT || b == ep_.barre) return;
    JsonVariantConst parOption = d["actions"];
    if (!parOption.isNull()) {
      ep_.active = false;
      vue_.ecran = Vue::PRINCIPAL;
      rafraichir();
      empiler(parOption[static_cast<int>(b)]);
      return;
    }
    if (!d["neutre"].isNull() && d["neutre"].as<int>() == b) {
      ep_.active = false;
      vue_.ecran = Vue::PRINCIPAL;
      rafraichir();
      empiler(d["si_neutre"]);
      return;
    }
    finEpreuve(b == d["bonne"].as<int>());
  } else if (ep_.type == "sequence") {
    if (ep_.etape != 1 || b > VERT) return;
    if (ep_.sequence[ep_.pas] != b) {
      finEpreuve(false);
      return;
    }
    ep_.pas++;
    if (ep_.pas >= static_cast<int>(ep_.sequence.size())) finEpreuve(true);
    else vueEpreuve();
  } else if (ep_.type == "rythme") {
    if (ep_.etape != 1 || b != GROS) return;
    uint32_t t = maintenant();
    JsonArrayConst motif = d["motif"].as<JsonArrayConst>();
    size_t n = motif.size();
    ep_.appuis.push_back(t);
    size_t k = ep_.appuis.size() % n;
    if (k == 0) {  // un motif complet vient d'être frappé : on compare les écarts
      int tolerance = d["tolerance"].isNull() ? 160 : d["tolerance"].as<int>();
      size_t base = ep_.appuis.size() - n;
      for (size_t i = 1; i < n; i++) {
        int ecart = static_cast<int>(ep_.appuis[base + i] - ep_.appuis[base]);
        int attendu = motif[i].as<int>() - motif[0].as<int>();
        if (std::abs(ecart - attendu) > tolerance) {
          finEpreuve(false);
          return;
        }
      }
      if (static_cast<int>(ep_.appuis.size() / n) >= d["repetitions"].as<int>()) finEpreuve(true);
      else vueEpreuve();
    }
  } else if (ep_.type == "rondes") {
    if (b != GROS) return;
    if (ep_.vert) {
      ep_.pas++;
      p_.jouer(DOSSIER_SONS_PUPITRE, 2, false);
      if (ep_.pas >= d["pas"].as<int>()) {
        finEpreuve(true);
        return;
      }
    } else {
      executerSimple(d["pris"]);
      if (phase_ != Phase::JEU) return;
    }
    vueEpreuve();
  } else if (ep_.type == "energie") {
    if (b == VERT) {
      std::string fausse;
      if (energieValide(&fausse)) {
        finEpreuve(true);
      } else {
        executerSimple(d["erreur"]);
        vueEpreuve();
      }
    } else if (b == ROUGE) {
      int n = static_cast<int>(ep_.valeurs.size());
      ep_.curseur = (ep_.curseur + n - 1) % n;
      vueEpreuve();
    }
  } else if (ep_.type == "traitre") {
    if (b == VERT) epreuveAppui();
  }
}

void Moteur::epreuveMolette(int pas) {
  JsonVariantConst d = ep_.def;
  if (ep_.type == "immobile") {
    finEpreuve(false);
  } else if (ep_.type == "code" || ep_.type == "lettres") {
    int i = ep_.curseur;
    int modulo = ep_.type == "lettres" ? 26 : 1;
    if (ep_.type == "code")
      for (int k = 0; k < ep_.largeurs[i]; k++) modulo *= 10;
    int v = ep_.valeurs[i] < 0 ? (pas > 0 ? -1 : 0) : ep_.valeurs[i];
    ep_.valeurs[i] = ((v + pas) % modulo + modulo) % modulo;
    // Stéthoscope : un « tic » à chaque cran, un « CLAC » au bon chiffre (disques audibles).
    JsonVariantConst ecoute = d["ecoute"];
    if (!ecoute.isNull() && drapeaux_.count("__stethoscope")) {
      JsonArrayConst audibles = ecoute["audibles"].as<JsonArrayConst>();
      bool audible = i < static_cast<int>(audibles.size()) && audibles[i].as<bool>();
      bool bon = audible && ep_.valeurs[i] == ep_.solution[i];
      p_.jouer(ecoute["dossier"].as<int>(), bon ? ecoute["clac"].as<int>() : ecoute["tic"].as<int>(), true);
    }
    vueEpreuve();
  } else if (ep_.type == "molette_zero") {
    ep_.valeurs[0] = std::max(0, ep_.valeurs[0] + pas);
    if (ep_.valeurs[0] == 0) finEpreuve(true);
    else vueEpreuve();
  } else if (ep_.type == "energie") {
    int max = d["max"].isNull() ? 4 : d["max"].as<int>();
    int& v = ep_.valeurs[ep_.curseur];
    v = std::max(0, std::min(max, v + pas));
    vueEpreuve();
  } else if (ep_.type == "traitre" && ep_.etape == 0) {
    ep_.valeurs[0] = std::max(2, std::min(8, ep_.valeurs[0] + pas));
    vueEpreuve();
  }
}

void Moteur::epreuveAppui() {
  JsonVariantConst d = ep_.def;
  if (ep_.type == "immobile") {
    finEpreuve(false);
  } else if (ep_.type == "code" || ep_.type == "lettres") {
    if (ep_.valeurs[ep_.curseur] < 0) return;
    if (ep_.curseur + 1 < static_cast<int>(ep_.valeurs.size())) {
      ep_.curseur++;
      vueEpreuve();
      return;
    }
    finEpreuve(ep_.valeurs == ep_.solution);
  } else if (ep_.type == "energie") {
    ep_.curseur = (ep_.curseur + 1) % static_cast<int>(ep_.valeurs.size());
    vueEpreuve();
  } else if (ep_.type == "traitre") {
    if (ep_.etape == 0) {
      ep_.nbJoueurs = ep_.valeurs[0];
      if (ep_.nbJoueurs < d["minimum"].as<int>()) {  // pas assez de joueurs : pas de traître
        finEpreuve(false);
        return;
      }
      ep_.traitre = static_cast<int>(p_.aleatoire(ep_.nbJoueurs));
      compteurs_["traitre"] = ep_.traitre + 1;
      ep_.joueur = 0;
      ep_.etape = 1;
      vueEpreuve();
    } else if (ep_.etape == 1) {
      p_.jouer(dossierVoix_, ep_.joueur == ep_.traitre ? d["traitre"].as<int>() : d["loyal"].as<int>(), true);
      ep_.etape = 2;
      ep_.prochain = maintenant();
      vueEpreuve();
    }
  }
}

void Moteur::epreuveCarte(const std::string& id) {
  if (ep_.type == "immobile") {
    finEpreuve(false);
    return;
  }
  JsonVariantConst aide = ep_.def["aides"][id];
  if (aide.isNull()) return;
  std::string type = texte(aide["type"]);
  bool illimite = type == "ecoute" || type == "stethoscope";
  if (pouvoirs_.count(id) && pouvoirs_[id] <= 0 && !illimite) {
    JsonVariantConst epuise = script_["pouvoir_epuise"];
    if (!epuise.isNull()) dire(dossierVoix_, epuise.as<int>(), false, false, false);
    return;
  }
  if (type == "reussir") {  // le pouvoir fait sauter l'épreuve (ex. : le monte-plats)
    pouvoirs_[id]--;
    if (!aide["dire"].isNull()) dire(dossierVoix_, aide["dire"].as<int>(), false, false, false);
    finEpreuve(true);
    return;
  }
  if (type == "barrer" && ep_.type == "choix" && ep_.barre < 0) {
    std::vector<int> faux;
    for (int i = 0; i < 4; i++)
      if (i != ep_.def["bonne"].as<int>() && (ep_.def["neutre"].isNull() || i != ep_.def["neutre"].as<int>())) faux.push_back(i);
    ep_.barre = faux[p_.aleatoire(static_cast<uint32_t>(faux.size()))];
    pouvoirs_[id]--;
  } else if (type == "diagnostic" && ep_.type == "energie") {
    std::string fausse;
    pouvoirs_[id]--;
    vue_.info = energieValide(&fausse) ? "Diagnostic : toutes les règles sont respectées." : "Diagnostic : " + fausse;
    rafraichir();
    if (!aide["dire"].isNull()) dire(dossierVoix_, aide["dire"].as<int>(), false, true, false);
    return;
  } else if (type == "ecoute") {
    drapeaux_.insert("__ecoute");
    p_.jouer(aide["dossier"].as<int>(), aide["n"].as<int>(), true);
  } else if (type == "stethoscope") {
    drapeaux_.insert("__stethoscope");
  } else {
    return;
  }
  if (!aide["dire"].isNull()) dire(dossierVoix_, aide["dire"].as<int>(), false, false, false);
  vueEpreuve();
}

void Moteur::vueEpreuve() {
  JsonVariantConst d = ep_.def;
  vue_.titre = texte(d["titre"]);
  vue_.texte = texte(d["texte"]);
  vue_.champs.clear();
  vue_.modules.clear();
  vue_.valeurs.clear();
  vue_.barre = ep_.barre;
  if (ep_.type == "code" || ep_.type == "lettres") {
    vue_.ecran = Vue::CODE;
    for (size_t i = 0; i < ep_.valeurs.size(); i++) {
      int v = ep_.valeurs[i], w = ep_.largeurs[i];
      if (v < 0) vue_.champs.push_back(std::string(w, '_'));
      else if (ep_.type == "lettres") vue_.champs.push_back(std::string(1, static_cast<char>('A' + v)));
      else vue_.champs.push_back(w == 2 ? deuxChiffres(v) : std::to_string(v));
    }
    vue_.curseur = ep_.curseur;
    if (vue_.info.empty() || vue_.info[0] != 'D') vue_.info = "Tourner : changer · Appuyer : valider · Rouge : effacer";
  } else if (ep_.type == "choix") {
    vue_.ecran = Vue::CHOIX;
    JsonArrayConst o = d["options"].as<JsonArrayConst>();
    for (int i = 0; i < 4; i++) vue_.options[i] = i < static_cast<int>(o.size()) ? texte(o[i]) : "";
  } else if (ep_.type == "energie") {
    vue_.ecran = Vue::ENERGIE;
    for (JsonVariantConst m : d["modules"].as<JsonArrayConst>()) vue_.modules.push_back(texte(m));
    vue_.valeurs = ep_.valeurs;
    vue_.curseur = ep_.curseur;
    int somme = 0;
    for (int v : ep_.valeurs) somme += v;
    vue_.bandeau = std::to_string(somme) + " / " + std::to_string(d["total"].as<int>()) + " unités";
  } else {
    vue_.ecran = Vue::EPREUVE;
    if (ep_.type == "sequence") {
      vue_.texte = ep_.etape == 0 ? (ep_.vert ? std::string(NOMS_COULEURS[ep_.sequence[ep_.pas]]) : "…")
                                  : "À vous ! " + std::to_string(ep_.pas) + " / " + std::to_string(ep_.sequence.size());
    } else if (ep_.type == "rythme") {
      vue_.texte = ep_.etape == 0 ? "Écoutez le rythme…" : "À vous, sur le gros bouton ! (" +
                   std::to_string(ep_.appuis.size() / std::max<size_t>(1, d["motif"].as<JsonArrayConst>().size())) + " / " +
                   std::to_string(d["repetitions"].as<int>()) + ")";
    } else if (ep_.type == "rondes") {
      std::string p;
      for (int i = 0; i < d["pas"].as<int>(); i++) p += i < ep_.pas ? "● " : "○ ";
      vue_.texte = (vue_.texte.empty() ? "" : vue_.texte + "\n") + "Progression : " + p;
    } else if (ep_.type == "molette_zero") {
      vue_.texte = "Pression : " + std::to_string(ep_.valeurs[0]);
    } else if (ep_.type == "traitre") {
      if (ep_.etape == 0) vue_.texte = "Nombre de joueurs : " + std::to_string(ep_.valeurs[0]) + "\n(molette, puis appui)";
      else if (ep_.etape == 1) vue_.texte = "Joueur " + std::to_string(ep_.joueur + 1) + " : mettez le casque, puis appuyez sur la molette.";
      else vue_.texte = "🎧 Écoute en cours…";
    }
  }
  rafraichir();
}

// ======================================================================
// Aide, accusation, score
// ======================================================================

void Moteur::demanderAide() {
  JsonVariantConst a = script_["aide"];
  if (a.isNull()) return;
  vue_.aideClignote = false;
  vue_.titre = texte(a["titre"], "UN COUP DE POUCE");
  rafraichir();
  ajouterInterne(Action::de(Action::DIRE, dossierVoix_, a["appel"].as<int>()));
  int i = 0;
  for (JsonVariantConst b : a["blocages"].as<JsonArrayConst>()) {
    if (condition(b["si"])) {
      std::string cle = "aide" + std::to_string(i);
      JsonArrayConst niveaux = b["niveaux"].as<JsonArrayConst>();
      int n = niveauxAide_[cle];
      if (n >= static_cast<int>(niveaux.size())) break;
      if (indicesDonnes_ < a["gratuits"].as<int>()) {
        ajouterInterne(Action::de(Action::DIRE, dossierVoix_, a["gratuit"].as<int>()));
      } else {
        ajouterInterne(Action::de(Action::DIRE, dossierVoix_, a["payant"].as<int>()));
        Action cout;
        cout.json = a["cout"];
        ajouterInterne(cout);
        indicesPayants_++;
      }
      ajouterInterne(Action::de(Action::DIRE, dossierVoix_, niveaux[n].as<int>()));
      niveauxAide_[cle] = n + 1;
      indicesDonnes_++;
      return;
    }
    i++;
  }
  ajouterInterne(Action::de(Action::DIRE, dossierVoix_, a["fin"].as<int>()));
}

void Moteur::accuser() {
  JsonVariantConst a = script_["accusation"];
  JsonVariantConst voix = a["voix"];
  bool complet = typeCarte(zones_[QUI]) == "perso" && typeCarte(zones_[OU]) == "lieu" && typeCarte(zones_[QUOI]) == "objet";
  if (!complet) {
    ajouterInterne(Action::de(Action::DIRE, dossierVoix_, voix["manque"].as<int>()));
    return;
  }
  accusationOuverte_ = true;
  vue_.ecran = Vue::ACCUSATION;
  vue_.titre = texte(a["titre"], "ACCUSATION");
  vue_.texte = "Vert : confirmer · Rouge : annuler";
  majZones();
  p_.jouer(dossierVoix_, voix["demande"].as<int>(), false);
}

void Moteur::reponseAccusation(bool confirmer) {
  accusationOuverte_ = false;
  if (!confirmer) {
    vue_.ecran = Vue::PRINCIPAL;
    rafraichir();
    return;
  }
  JsonVariantConst a = script_["accusation"];
  JsonVariantConst voix = a["voix"];
  bool juste[3] = {zones_[QUI] == texte(a["qui"]), zones_[OU] == texte(a["ou"]), zones_[QUOI] == texte(a["quoi"])};
  int nbJustes = juste[0] + juste[1] + juste[2];

  // Suspense, puis verdict.
  p_.jouer(DOSSIER_SONS_PUPITRE, 7, false);
  ajouterInterne(Action::de(Action::ATTENDRE, 2500));
  ajouterInterne(Action::de(Action::DIRE, dossierVoix_, voix["suspense"].as<int>()));

  if (nbJustes == 3) {
    for (int z = 0; z < 3; z++) {
      flashZone_[z] = maintenant() + 6000 + 700 * z;
      couleurZone_[z] = 0x00ff00;
    }
    ajouterInterne(Action::de(Action::DIRE, dossierVoix_, voix["juste"].as<int>()));
    terminerApres_ = true;  // la victoire est déclarée une fois la réplique dite
    return;
  }

  // Cas particuliers prévus par le script (ex. : une carte qui « explique » sans prouver).
  bool gratuit = false;
  for (JsonVariantConst s : a["speciaux"].as<JsonArrayConst>()) {
    bool ok = (s["qui"].isNull() || texte(s["qui"]) == zones_[QUI]) && (s["ou"].isNull() || texte(s["ou"]) == zones_[OU]) &&
              (s["quoi"].isNull() || texte(s["quoi"]) == zones_[QUOI]);
    if (!ok) continue;
    ajouterInterne(Action::de(Action::DIRE, dossierVoix_, s["dire"].as<int>()));
    if (s["gratuit"].as<bool>()) gratuit = true;
  }
  std::string poker = texte(a["poker"]);
  if (!poker.empty() && drapeaux_.count(poker)) {
    drapeaux_.erase(poker);
    for (int z = 0; z < 3; z++) {
      flashZone_[z] = maintenant() + 8000;
      couleurZone_[z] = juste[z] ? 0x00ff00 : 0xff0000;
    }
    ajouterInterne(Action::de(Action::DIRE, dossierVoix_, voix["poker"].as<int>()));
  } else if (!gratuit) {
    erreurs_++;
    ajouterInterne(Action::de(Action::DIRE, dossierVoix_, voix["resultats"][nbJustes].as<int>()));
    Action penalite;
    penalite.json = a["penalite"];
    ajouterInterne(penalite);
    if (erreurs_ >= a["erreurs_max"].as<int>()) {
      ajouterInterne(Action::de(Action::DIRE, dossierVoix_, voix["troisieme"].as<int>()));
      defaiteApres_ = true;
    }
  }
  vue_.ecran = Vue::PRINCIPAL;
  rafraichir();
}

void Moteur::enregistrerScore() {
  int restant = std::max(0, secondesRestantes());
  vue_.ecran = Vue::RESULTAT;
  vue_.titre = victoire_ ? "★ MISSION RÉUSSIE ★" : "MISSION ÉCHOUÉE";
  char ligne[160];
  snprintf(ligne, sizeof ligne, "Temps restant : %d min %02d s\nIndices payants : %d\nErreurs : %d", restant / 60, restant % 60,
           indicesPayants_, erreurs_);
  vue_.texte = ligne;
  if (!script_["soupcon"].isNull()) vue_.texte += "\nSoupçon : " + std::to_string(compteur("soupcon"));
  vue_.bandeau = "Vert : retour à l'accueil";
  rafraichir();
  if (!victoire_) return;
  JsonDocument scores;
  std::string chemin = "/mondes/" + mondeId_ + "/scores.json";
  std::string contenu;
  if (p_.lireFichier(chemin, contenu)) deserializeJson(scores, contenu);
  JsonObject s = scores["scores"].add<JsonObject>();
  s["mission"] = missionNumero_;
  s["restant"] = restant;
  s["erreurs"] = erreurs_;
  s["indices"] = indicesPayants_;
  s["rang"] = vue_.info;
  std::string json;
  serializeJson(scores, json);
  p_.ecrireFichier(chemin, json);
}

// ======================================================================
// Temps, imprévus, vue, lumières
// ======================================================================

int Moteur::secondesEcoulees() const {
  if (!debutJeu_) return 0;
  uint32_t t = finJeu_ ? finJeu_ : p_.maintenant() + decalage_;  // figé en fin de partie
  uint32_t pauses = cumulPause_ + (pause_ ? t - debutPause_ : 0);
  return static_cast<int>((t - debutJeu_ - pauses) / 1000);
}

int Moteur::secondesRestantes() const {
  return script_["chrono"]["duree"].as<int>() * 60 + bonusSecondes_ - secondesEcoulees();
}

void Moteur::majChrono() {
  if (!chronoActif_ || phase_ != Phase::JEU || pause_) return;
  if (secondesRestantes() <= 0) terminer(false, "temps");
}

void Moteur::majImprevus() {
  if (!chronoActif_ || phase_ != Phase::JEU || pause_) return;
  int ecoule = secondesEcoulees();
  bool occupe = ep_.active || accusationOuverte_ || !file_.empty() || attente_ != Attente::RIEN;
  int i = 0;
  for (JsonVariantConst im : script_["imprevus"].as<JsonArrayConst>()) {
    std::string cle = "imprevu" + std::to_string(i++);
    if (imprevusFaits_.count(cle)) continue;
    bool echu = !im["minute"].isNull() && ecoule >= im["minute"].as<int>() * 60;
    if (!im["restant"].isNull()) echu = secondesRestantes() <= im["restant"].as<int>() * 60;
    if (!echu || occupe) continue;
    imprevusFaits_.insert(cle);
    if (!condition(im["si"])) continue;
    empiler(im["actions"]);
    return;
  }
  bool clignote = false;
  for (JsonVariantConst r : script_["relances"].as<JsonArrayConst>())
    if (ecoule >= r["minute"].as<int>() * 60 && condition(r["si"])) clignote = true;
  if (clignote != vue_.aideClignote) {
    vue_.aideClignote = clignote;
    rafraichir();
  }
}

void Moteur::majEntete() {
  if (phase_ != Phase::JEU && phase_ != Phase::FIN) return;
  JsonVariantConst c = script_["chrono"];
  std::string e;
  if (texte(c["mode"]) == "horloge") {
    int m = minutesDe(texte(c["depart"])) + (chronoActif_ ? secondesEcoulees() / 60 : 0);
    e = std::to_string((m / 60) % 24) + ":" + deuxChiffres(m % 60);
  } else {
    int r = std::max(0, chronoActif_ ? secondesRestantes() : c["duree"].as<int>() * 60);
    e = texte(c["prefixe"]) + deuxChiffres(r / 60) + ":" + deuxChiffres(r % 60);
  }
  if (pause_) e += "  ⏸";
  std::string compteurs;
  if (!script_["soupcon"].isNull())
    compteurs += "Soupçon " + std::to_string(compteur("soupcon")) + "/" + std::to_string(script_["soupcon"]["max"].as<int>()) + " · ";
  compteurs += "✗ " + std::to_string(erreurs_) + "/" + std::to_string(script_["accusation"]["erreurs_max"].as<int>());
  int gratuits = std::max(0, script_["aide"]["gratuits"].as<int>() - indicesDonnes_);
  compteurs += " · Aide " + std::to_string(gratuits);
  std::string progression;
  for (JsonVariantConst p : script_["progression"].as<JsonArrayConst>())
    progression += texte(p["nom"]) + (condition(p["si"]) ? " ✓   " : " ·   ");
  if (e != vue_.entete || compteurs != vue_.compteurs) {
    vue_.entete = e;
    vue_.compteurs = compteurs;
    vue_.versionEntete++;
  }
  if (progression != vue_.progression) {
    vue_.progression = progression;
    rafraichir();
  }
}

void Moteur::majZones() {
  for (int z = 0; z < 3; z++) vue_.zones[z] = nomCarte(zones_[z]);
  rafraichir();
}

void Moteur::majLumieres() {
  uint32_t t = maintenant();
  Lumieres l;
  float pulse = 0.5f + 0.5f * static_cast<float>((t / 8) % 250) / 250.0f;

  // L'anneau : la jauge du monde.
  std::string jauge = texte(script_["jauge"], "chrono");
  int allumees = 0;
  uint32_t c = 0x30c040;
  if (phase_ == Phase::JEU && chronoActif_) {
    int total = script_["chrono"]["duree"].as<int>() * 60;
    int reste = std::max(0, secondesRestantes());
    if (jauge == "soupcon") {
      allumees = std::min(12, compteur("soupcon"));
      c = allumees >= 8 ? 0xd02010 : 0xd0a020;
    } else {
      allumees = std::min(12, (reste * 12 + total - 1) / std::max(1, total));
      if (jauge == "oxygene") c = reste < 15 * 60 ? 0xd02010 : 0x2080ff;
      else c = reste * 2 > total ? 0x30c040 : (reste * 4 > total ? 0xe08010 : 0xd02010);
    }
    vue_.jauge = allumees;
  }
  for (int i = 0; i < 12; i++) l.couleurs[i] = i < allumees ? attenuer(c, 0.6f) : 0;
  if (phase_ == Phase::ACCUEIL || phase_ == Phase::MENU || phase_ == Phase::CHOIX_MONDE)
    for (int i = 0; i < 12; i++) l.couleurs[i] = attenuer(0xffffff, 0.15f + 0.25f * pulse);

  // Épreuves visuelles.
  if (ep_.active && ep_.type == "rondes")
    for (int i = 0; i < 12; i++) l.couleurs[i] = ep_.vert ? 0x00c000 : 0xd00000;
  if (ep_.active && ep_.type == "sequence" && ep_.etape == 0)
    for (int i = 0; i < 12; i++) l.couleurs[i] = ep_.vert ? COULEURS_BOUTONS[ep_.sequence[ep_.pas]] : 0;
  if (vue_.ecran == Vue::CASQUE)
    for (int i = 0; i < 12; i++) l.couleurs[i] = attenuer(0x8020d0, pulse);

  // Effets temporaires.
  if (t < finEffet_) {
    if (effet_ == "succes" || effet_ == "echec") {
      uint32_t e = effet_ == "succes" ? 0x00d000 : 0xd00000;
      for (int i = 0; i < 12; i++) l.couleurs[i] = e;
    } else if (effet_ == "alerte") {
      int tete = static_cast<int>((t / 80) % 12);
      for (int i = 0; i < 12; i++) l.couleurs[i] = ((i - tete + 12) % 12) < 3 ? 0xff0000 : 0x200000;
    } else if (effet_ == "victoire") {
      for (int i = 0; i < 12; i++) {
        int h = static_cast<int>((t / 10 + i * 21) % 256);
        l.couleurs[i] = h < 85 ? melange(0xff0000, 0x00ff00, h / 85.0f)
                      : h < 170 ? melange(0x00ff00, 0x0000ff, (h - 85) / 85.0f)
                                : melange(0x0000ff, 0xff0000, (h - 170) / 85.0f);
      }
    } else if (effet_ == "defaite") {
      for (int i = 0; i < 12; i++) l.couleurs[i] = attenuer(0x600000, pulse * 0.5f);
    }
  }

  // Les trois LED de zone.
  for (int z = 0; z < 3; z++) {
    uint32_t zc = zones_[z].empty() ? 0 : 0x303030;
    if (accusationOuverte_) zc = attenuer(0xffffff, pulse);
    if (t < flashZone_[z]) zc = couleurZone_[z];
    l.couleurs[12 + z] = zc;
  }
  p_.eclairer(l);
}

// ======================================================================
// Sauvegarde (reprise après une coupure de courant)
// ======================================================================

void Moteur::sauvegarder() {
  JsonDocument d;
  d["monde"] = mondeId_;
  d["mission"] = missionNumero_;
  d["ecoule"] = secondesEcoulees();
  d["bonus"] = bonusSecondes_;
  d["erreurs"] = erreurs_;
  d["indices"] = indicesDonnes_;
  d["payants"] = indicesPayants_;
  for (const auto& f : drapeaux_) d["drapeaux"].add(f);
  for (const auto& f : imprevusFaits_) d["imprevus"].add(f);
  for (const auto& kv : compteurs_) d["compteurs"][kv.first] = kv.second;
  for (const auto& kv : pouvoirs_) d["pouvoirs"][kv.first] = kv.second;
  for (const auto& kv : niveauxAide_) d["aide"][kv.first] = kv.second;
  std::string json;
  serializeJson(d, json);
  p_.ecrireFichier(CHEMIN_SAUVEGARDE, json);
}

bool Moteur::restaurer() {
  JsonDocument d;
  if (!chargerJson(CHEMIN_SAUVEGARDE, d) || texte(d["monde"]) != mondeId_) return false;
  erreurs_ = d["erreurs"].as<int>();
  indicesDonnes_ = d["indices"].as<int>();
  indicesPayants_ = d["payants"].as<int>();
  bonusSecondes_ = d["bonus"].as<int>();
  for (JsonVariantConst f : d["drapeaux"].as<JsonArrayConst>()) drapeaux_.insert(texte(f));
  for (JsonVariantConst f : d["imprevus"].as<JsonArrayConst>()) imprevusFaits_.insert(texte(f));
  for (JsonPairConst kv : d["compteurs"].as<JsonObjectConst>()) compteurs_[kv.key().c_str()] = kv.value().as<int>();
  for (JsonPairConst kv : d["pouvoirs"].as<JsonObjectConst>()) pouvoirs_[kv.key().c_str()] = kv.value().as<int>();
  for (JsonPairConst kv : d["aide"].as<JsonObjectConst>()) niveauxAide_[kv.key().c_str()] = kv.value().as<int>();
  debutJeu_ = maintenant() - d["ecoule"].as<uint32_t>() * 1000;
  finJeu_ = 0;
  cumulPause_ = 0;
  for (JsonPairConst kv : script_["roles"].as<JsonObjectConst>()) rolesPresents_.insert(kv.key().c_str());
  return true;
}

// ======================================================================
// Boucle principale
// ======================================================================

void Moteur::miseAJour() {
  uint32_t t = maintenant();

  // Ambiance : relancée dès que le haut-parleur se tait un moment.
  if (ambiance_ && phase_ == Phase::JEU && attente_ == Attente::RIEN && !p_.joue(false)) {
    if (!silenceDepuis_) silenceDepuis_ = t;
    if (t - silenceDepuis_ > 800) {
      p_.jouer(dossierSons_, ambiance_, false);
      ambianceEnCours_ = true;
      silenceDepuis_ = 0;
    }
  } else if (p_.joue(false)) {
    silenceDepuis_ = 0;
  }

  traiterFile();

  // Fins déclenchées par l'accusation, une fois les répliques dites.
  if (file_.empty() && attente_ == Attente::RIEN) {
    if (terminerApres_) {
      terminerApres_ = false;
      terminer(true, "");
    } else if (defaiteApres_) {
      defaiteApres_ = false;
      terminer(false, "erreurs");
    }
  }

  majChrono();
  majImprevus();
  if (fenetre_.active && t > fenetre_.fin) fenetre_.active = false;

  if (t - derniereSeconde_ >= 250) {
    derniereSeconde_ = t;
    majEntete();
  }
  if (chronoActif_ && phase_ == Phase::JEU && t - derniereSauvegarde_ > 15000) {
    derniereSauvegarde_ = t;
    sauvegarder();
  }
  majLumieres();
  p_.afficher(vue_);
}

std::string Moteur::phaseTexte() const {
  switch (phase_) {
    case Phase::ACCUEIL: return "accueil";
    case Phase::CHOIX_MONDE: return "choix_monde";
    case Phase::MENU: return "menu";
    case Phase::ROLES: return "roles";
    case Phase::JEU: return chronoActif_ ? "jeu" : "introduction";
    case Phase::FIN: return victoire_ ? "victoire" : "defaite";
    case Phase::ENROLEMENT: return "enrolement";
  }
  return "?";
}

std::string Moteur::etatJson() {
  JsonDocument d;
  d["phase"] = phaseTexte();
  d["monde"] = vue_.monde;
  d["mission"] = missionNumero_;
  d["restant"] = chronoActif_ ? secondesRestantes() : 0;
  d["entete"] = vue_.entete;
  d["pause"] = pause_;
  d["erreurs"] = erreurs_;
  d["indices"] = indicesDonnes_;
  d["texte"] = vue_.texte;
  for (int z = 0; z < 3; z++) d["zones"].add(vue_.zones[z]);
  for (const auto& f : drapeaux_)
    if (f.rfind("__", 0) != 0) d["etapes"].add(f);
  for (const auto& kv : compteurs_) d["compteurs"][kv.first] = kv.second;
  for (const auto& kv : pouvoirs_) d["pouvoirs"][kv.first] = kv.second;
  std::string json;
  serializeJson(d, json);
  return json;
}

}  // namespace pupitre
