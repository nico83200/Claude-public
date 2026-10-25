// Simulateur du Pupitre des Énigmes sur PC.
//
// Il fait tourner le vrai moteur de jeu avec une horloge virtuelle, un faux lecteur
// audio et des cartes « UID-<id> », et joue une partie écrite dans un fichier texte :
//
//   ./sim <dossier SD> <partie.txt> [--bavard]
//
// Commandes d'une partie (une par ligne, # pour les commentaires) :
//   pose QUI|OU|QUOI <id>     retire QUI|OU|QUOI      bouton ROUGE|BLEU|JAUNE|VERT|GROS
//   molette <pas>             appui                   attendre <secondes>
//   calme                     (laisse le moteur finir ce qu'il fait)
//   code 3,7,1,5              lettres SILENCE         energie 3,2,2,2,1,0
//   ensemble <ms>             rythme <ms,ms,...> <n>  sequence      rondes      molette_zero
//   commande <texte>          avance <minutes>        (fait passer le temps de jeu ;
//                                                      s'arrête si une épreuve commence)
//   redemarre                 (coupure de courant : le pupitre redémarre, la carte SD reste)
//   restant <minutes>         (fait passer le temps jusqu'à ce qu'il reste <minutes> au chrono)
//   verifie drapeau <nom> | sans <nom> | compteur <nom> <v> | phase <p> | epreuve <type>
#include <cstdio>
#include <cstdlib>
#include <fstream>
#include <iostream>
#include <map>
#include <memory>
#include <sstream>
#include <string>

#include "moteur.h"

using namespace pupitre;

namespace {

class PlateformeSimulee : public Plateforme {
 public:
  explicit PlateformeSimulee(std::string racine, bool bavard) : racine_(std::move(racine)), bavard_(bavard) {}

  uint32_t horloge = 0;
  int repliques = 0;

  uint32_t maintenant() override { return horloge; }

  bool lireFichier(const std::string& chemin, std::string& contenu) override {
    auto it = ecrits_.find(chemin);
    if (it != ecrits_.end()) {
      contenu = it->second;
      return true;
    }
    std::ifstream f(racine_ + chemin);
    if (!f) return false;
    std::stringstream s;
    s << f.rdbuf();
    contenu = s.str();
    return true;
  }

  // Les écritures restent en mémoire : la carte SD de test n'est pas modifiée.
  bool ecrireFichier(const std::string& chemin, const std::string& contenu) override {
    ecrits_[chemin] = contenu;
    return true;
  }

  void jouer(int dossier, int numero, bool casque) override {
    bool voix = (dossier % 10 == 0 && dossier != 90) || dossier == 91;  // 90 et x1 : bruitages
    finAudio_[casque] = horloge + (voix ? 1500 : 300);
    if (voix) repliques++;
    if (bavard_ && voix) printf("        %s %02d/%03d\n", casque ? "🎧" : "🔊", dossier, numero);
  }
  bool joue(bool casque) override { return horloge < finAudio_[casque]; }
  void arreter(bool casque) override { finAudio_[casque] = 0; }
  bool audioPresent() override { return true; }

  void afficher(const Vue& v) override {
    if (v.version == version_) return;
    version_ = v.version;
    std::string resume = v.titre;
    if (!v.texte.empty()) resume += " │ " + v.texte.substr(0, 110);
    if (!v.bandeau.empty()) resume += " │ " + v.bandeau;
    for (auto& c : resume)
      if (c == '\n') c = ' ';
    if (resume != dernier_) {
      dernier_ = resume;
      if (bavard_) printf("  [%s] %s\n", nomEcran(v.ecran), resume.c_str());
    }
  }
  void eclairer(const Lumieres&) override {}
  uint32_t aleatoire(uint32_t borne) override { return borne ? static_cast<uint32_t>(rand()) % borne : 0; }
  void journal(const std::string& m) override { printf("  ⚠ %s\n", m.c_str()); }

 private:
  static const char* nomEcran(Vue::Ecran e) {
    static const char* noms[] = {"accueil", "menu", "rôles", "principal", "code", "choix", "énergie", "épreuve",
                                 "casque", "accusation", "résultat", "enrôlement"};
    return noms[e];
  }
  std::string racine_;
  bool bavard_;
  std::map<std::string, std::string> ecrits_;
  uint32_t finAudio_[2] = {0, 0};
  uint32_t version_ = 0;
  std::string dernier_;
};

PlateformeSimulee* plateforme = nullptr;
Moteur* moteur = nullptr;
int echecs = 0;

void tourner(uint32_t ms) {
  for (uint32_t t = 0; t < ms; t += 10) {
    plateforme->horloge += 10;
    moteur->miseAJour();
  }
}

// Un message au casque attend qu'un joueur appuie sur la molette.
void prendreCasque() {
  if (moteur->vue().ecran == Vue::CASQUE && moteur->vue().texte.find("molette") != std::string::npos) moteur->appuiMolette();
}

// Laisse le moteur finir ce qu'il fait, ou s'arrête dès qu'une épreuve attend les joueurs.
void calme(uint32_t maxMs = 180000) {
  uint32_t debut = plateforme->horloge;
  tourner(50);
  while (!moteur->calme() && !moteur->attendJoueurs() && plateforme->horloge - debut < maxMs) {
    prendreCasque();
    tourner(50);
  }
  tourner(50);
}

// Fait passer du temps de jeu ; s'arrête si un imprévu lance une épreuve.
void avancer(uint32_t ms) {
  uint32_t debut = plateforme->horloge;
  while (plateforme->horloge - debut < ms) {
    prendreCasque();
    tourner(100);
    if (moteur->attendJoueurs()) {
      printf("  … épreuve « %s » lancée après %u s\n", moteur->epreuveType().c_str(), (plateforme->horloge - debut) / 1000);
      return;
    }
  }
  calme();
}

Zone zone(const std::string& z) { return z == "QUI" ? QUI : z == "OU" ? OU : QUOI; }

Bouton bouton(const std::string& b) {
  if (b == "ROUGE") return ROUGE;
  if (b == "BLEU") return BLEU;
  if (b == "JAUNE") return JAUNE;
  if (b == "VERT") return VERT;
  return GROS;
}

void saisir(const std::vector<int>& valeurs) {
  for (int v : valeurs) {
    moteur->molette(v + 1);  // le champ part de « vide » : un cran = 0
    tourner(30);
    moteur->appuiMolette();
    tourner(30);
  }
}

std::vector<int> liste(const std::string& s) {
  std::vector<int> v;
  std::stringstream ss(s);
  std::string x;
  while (std::getline(ss, x, ',')) v.push_back(atoi(x.c_str()));
  return v;
}

void verifier(bool ok, const std::string& quoi, int ligne) {
  if (ok) {
    printf("  ✓ %s\n", quoi.c_str());
  } else {
    printf("  ✗ ÉCHEC ligne %d : %s\n", ligne, quoi.c_str());
    echecs++;
  }
}

}  // namespace

int main(int argc, char** argv) {
  if (argc < 3) {
    fprintf(stderr, "usage : sim <dossier SD> <partie.txt> [--bavard]\n");
    return 2;
  }
  srand(7);
  bool bavard = argc > 3 && std::string(argv[3]) == "--bavard";
  PlateformeSimulee p(argv[1], bavard);
  auto instance = std::make_unique<Moteur>(p);
  plateforme = &p;
  moteur = instance.get();
  moteur->demarrer();
  calme();

  std::ifstream partie(argv[2]);
  std::string ligne;
  int numero = 0;
  while (std::getline(partie, ligne)) {
    numero++;
    if (ligne.empty() || ligne[0] == '#') continue;
    std::stringstream ss(ligne);
    std::string cmd, a, b, c;
    ss >> cmd >> a >> b >> c;
    Moteur& m = *moteur;
    int restant = m.secondesRestantes();
    if (bavard) printf("%3d:%02d ▶ %s\n", restant / 60, std::abs(restant % 60), ligne.c_str());

    if (cmd == "pose") {
      m.carte(zone(a), "UID-" + b);
      calme();
    } else if (cmd == "retire") {
      m.carte(zone(a), "");
      tourner(50);
    } else if (cmd == "bouton") {
      m.bouton(bouton(a));
      calme();
    } else if (cmd == "molette") {
      m.molette(atoi(a.c_str()));
      tourner(50);
    } else if (cmd == "appui") {
      m.appuiMolette();
      calme();
    } else if (cmd == "attendre") {
      tourner(static_cast<uint32_t>(atof(a.c_str()) * 1000));
    } else if (cmd == "avance") {
      avancer(static_cast<uint32_t>(atof(a.c_str()) * 60000));
    } else if (cmd == "restant") {
      // Fait passer le temps jusqu'à ce qu'il reste <minutes> au chrono.
      int cible = atoi(a.c_str()) * 60;
      int garde = 0;
      while (m.secondesRestantes() > cible && !m.attendJoueurs() && garde++ < 100000) {
        prendreCasque();
        tourner(100);
      }
      if (m.attendJoueurs()) printf("  … épreuve « %s » lancée\n", m.epreuveType().c_str());
      else calme();
    } else if (cmd == "redemarre") {
      // Coupure de courant : un moteur neuf, la carte SD (et sa sauvegarde) restent.
      instance = std::make_unique<Moteur>(p);
      moteur = instance.get();
      moteur->demarrer();
      calme();
    } else if (cmd == "calme") {
      calme();
    } else if (cmd == "code") {
      saisir(liste(a));
      calme();
    } else if (cmd == "lettres") {
      std::vector<int> v;
      for (char ch : a) v.push_back(ch - 'A');
      saisir(v);
      calme();
    } else if (cmd == "energie") {
      std::vector<int> v = liste(a);
      for (size_t i = 0; i < v.size(); i++) {
        m.molette(v[i] - m.epreuveValeurs()[i]);
        tourner(30);
        m.appuiMolette();
        tourner(30);
      }
      m.bouton(VERT);
      calme();
    } else if (cmd == "ensemble") {
      m.boutonsEnfonces(0x0f);
      tourner(static_cast<uint32_t>(atoi(a.c_str())));
      m.boutonsEnfonces(0);
      calme();
    } else if (cmd == "rythme") {
      std::vector<int> motif = liste(a);
      while (m.epreuveEnCours() && m.epreuveEtape() == 0) tourner(20);
      for (int r = 0; r < atoi(b.c_str()); r++) {
        int precedent = 0;
        for (int t : motif) {
          tourner(static_cast<uint32_t>(t - precedent));
          precedent = t;
          m.bouton(GROS);
        }
        tourner(700);
      }
      calme();
    } else if (cmd == "sequence") {
      while (m.epreuveEnCours() && m.epreuveEtape() == 0) tourner(20);
      for (int couleur : m.epreuveSequence()) {
        m.bouton(static_cast<Bouton>(couleur));
        tourner(200);
      }
      calme();
    } else if (cmd == "rondes") {
      int garde = 0;
      while (m.epreuveEnCours() && m.epreuveType() == "rondes" && garde++ < 4000) {
        if (m.epreuveVert()) {
          m.bouton(GROS);
          tourner(300);
        }
        tourner(20);
      }
      calme();
    } else if (cmd == "molette_zero") {
      m.molette(-m.epreuveValeurs()[0]);
      calme();
    } else if (cmd == "commande") {
      m.commande(a);
      calme();
    } else if (cmd == "verifie") {
      if (a == "drapeau") verifier(m.drapeau(b), "drapeau " + b, numero);
      else if (a == "sans") verifier(!m.drapeau(b), "pas de drapeau " + b, numero);
      else if (a == "compteur") verifier(m.compteur(b) == atoi(c.c_str()), "compteur " + b + " = " + c + " (vaut " + std::to_string(m.compteur(b)) + ")", numero);
      else if (a == "phase") verifier(m.phaseTexte() == b, "phase " + b + " (est " + m.phaseTexte() + ")", numero);
      else if (a == "epreuve") verifier(m.epreuveType() == b, "épreuve " + b + " (est « " + m.epreuveType() + " »)", numero);
      else if (a == "ecran") verifier(m.vue().titre.find(b) != std::string::npos, "écran « " + b + " »", numero);
    } else {
      printf("  ? commande inconnue ligne %d : %s\n", numero, ligne.c_str());
      echecs++;
    }
  }
  int r = moteur->secondesRestantes();
  printf("\nFin : phase %s, %d répliques jouées, temps restant %d:%02d, %d échec(s).\n", moteur->phaseTexte().c_str(), p.repliques,
         r / 60, std::abs(r % 60), echecs);
  return echecs ? 1 : 0;
}
