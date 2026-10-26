// Simulateur du Pupitre des Énigmes sur PC.
//
// Il fait tourner le vrai moteur de jeu avec une horloge virtuelle, un faux lecteur
// audio et des cartes « UID-<id> », et joue une partie écrite dans un fichier texte :
//
//   ./sim <dossier SD> <partie.txt> [--bavard] [--captures <dossier>]
//
// Commandes d'une partie (une par ligne, # pour les commentaires) :
//   pose QUI|OU|QUOI <id>     retire QUI|OU|QUOI      bouton ROUGE|BLEU|JAUNE|VERT|GROS
//   molette <pas>             appui                   attendre <secondes>
//   calme                     (laisse le moteur finir ce qu'il fait)
//   code 3,7,1,5              lettres SILENCE         energie 3,2,2,2,1,0
//   ensemble <ms>             rythme <ms,ms,...> <n>  sequence      rondes      molette_zero
//   commande <texte>          avance <minutes>        (fait passer le temps de jeu ;
//                                                      s'arrête si une épreuve commence)
//   capture <nom>             (image de l'écran, avec test/ecrans.sh)
//   dynamo auto | manuel      (un joueur actionne la dynamo toutes les 6 s, ou plus personne)
//   redemarre                 (coupure de courant : le pupitre redémarre, la carte SD reste)
//   restant <minutes>         (fait passer le temps jusqu'à ce qu'il reste <minutes> au chrono)
//   verifie drapeau <nom> | sans <nom> | compteur <nom> <v> | phase <p> | epreuve <type> | ecran <titre>
//            reglage ecran|hp|casque|led <v> | lumiere noir|allumee|<minimum>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <fstream>
#include <iostream>
#include <map>
#include <memory>
#include <sstream>
#include <string>

#include "moteur.h"

#ifdef AVEC_ECRAN  // compilé par test/ecrans.sh : l'écran est dessiné comme sur le pupitre
#include "dessin.h"
#endif

using namespace pupitre;

namespace {

class PlateformeSimulee : public Plateforme {
 public:
  explicit PlateformeSimulee(std::string racine, bool bavard) : racine_(std::move(racine)), bavard_(bavard) {
#ifdef AVEC_ECRAN
    image_.setColorDepth(24);
    image_.createSprite(480, 320);
#endif
  }

  // Enregistre l'écran tel qu'il apparaît sur le pupitre (image PPM).
  bool capturer(const std::string& chemin) {
#ifdef AVEC_ECRAN
    FILE* f = fopen(chemin.c_str(), "wb");
    if (!f) return false;
    fprintf(f, "P6 480 320 255\n");
    for (int y = 0; y < 320; y++)
      for (int x = 0; x < 480; x++) {
        auto c = image_.readPixelRGB(x, y);
        fputc(c.R8(), f);
        fputc(c.G8(), f);
        fputc(c.B8(), f);
      }
    fclose(f);
    return true;
#else
    (void)chemin;
    return false;
#endif
  }

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
#ifdef AVEC_ECRAN
    dessin_.afficher(v, horloge);
#endif
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
  void appliquer(const Reglages& r) override {
    if (bavard_) printf("        ⚙ écran %d %% · haut-parleur %d · casque %d · LED %d %%\n", r.ecran, r.hp, r.casque, r.led);
  }

 private:
  static const char* nomEcran(Vue::Ecran e) {
    static const char* noms[] = {"accueil", "menu", "rôles", "principal", "code", "choix", "énergie", "épreuve",
                                 "casque", "accusation", "résultat", "enrôlement", "réglages", "tableau"};
    return noms[e];
  }
  std::string racine_;
  bool bavard_;
#ifdef AVEC_ECRAN
  lgfx::LGFX_Sprite image_;
  Dessin dessin_{image_};
#endif
  std::map<std::string, std::string> ecrits_;
  uint32_t finAudio_[2] = {0, 0};
  uint32_t version_ = 0;
  std::string dernier_;
};

PlateformeSimulee* plateforme = nullptr;
Moteur* moteur = nullptr;
int echecs = 0;
bool dynamoAuto = false;  // « dynamo auto » : un joueur actionne la dynamo toutes les 6 secondes

void tourner(uint32_t ms) {
  for (uint32_t t = 0; t < ms; t += 10) {
    plateforme->horloge += 10;
    if (dynamoAuto && plateforme->horloge % 6000 == 0 && moteur->phaseTexte() == "jeu" && moteur->epreuveType() != "immobile")
      moteur->bouton(GROS);
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
  bool bavard = false;
  std::string captures;  // --captures <dossier> : où ranger les images de l'écran
  for (int i = 3; i < argc; i++) {
    std::string opt = argv[i];
    if (opt == "--bavard") bavard = true;
    if (opt == "--captures" && i + 1 < argc) captures = argv[++i];
  }
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
    } else if (cmd == "fente" || cmd == "plateau") {
      // Le pupitre à deux lecteurs : « fente L1 », « plateau O2 », « fente - » pour retirer.
      m.lecteur(cmd == "fente" ? 0 : 1, a == "-" ? "" : "UID-" + a);
      if (a == "-") tourner(50);
      else calme();
    } else if (cmd == "couleur") {
      // Le capteur de couleur : « couleur rouge », « couleur - » quand l'objet est retiré.
      m.couleurPosee(a == "-" ? "" : a);
      calme();
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
    } else if (cmd == "capture") {
      // Sans écran compilé, la commande ne fait rien.
      if (!captures.empty() && p.capturer(captures + "/" + a + ".ppm")) printf("  📷 %s\n", a.c_str());
    } else if (cmd == "dynamo") {
      dynamoAuto = a == "auto";
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
    } else if (cmd == "touches") {
      // Le clavier 4×4 : « touches 379# », « _ » = une pause de 1,5 s.
      for (char k : a) {
        if (k == '_') tourner(1500);
        else m.touche(k);
        tourner(80);
      }
      calme();
    } else if (cmd == "t9") {
      // Un mot tapé au clavier comme sur un téléphone, puis # : « t9 ABYSSE ».
      static const char* const T9[10] = {"", "", "ABC", "DEF", "GHI", "JKL", "MNO", "PQRS", "TUV", "WXYZ"};
      char precedente = 0;
      for (char lettre : a) {
        for (int k = 2; k <= 9; k++) {
          const char* p = strchr(T9[k], lettre);
          if (!p) continue;
          char touche = static_cast<char>('0' + k);
          if (touche == precedente) tourner(1500);  // même touche : attendre la lettre suivante
          for (long n = 0; n <= p - T9[k]; n++) {
            m.touche(touche);
            tourner(120);
          }
          precedente = touche;
        }
      }
      m.touche('#');
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
          m.bouton(c.empty() ? GROS : bouton(c));
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
      else if (a == "reglage") {
        const Reglages& r = m.reglages();
        int v = b == "ecran" ? r.ecran : b == "hp" ? r.hp : b == "casque" ? r.casque : r.led;
        verifier(v == atoi(c.c_str()), "réglage " + b + " = " + c + " (vaut " + std::to_string(v) + ")", numero);
      } else if (a == "lumiere") {
        int l = m.lumiere();
        bool ok = b == "noir" ? l == 0 : b == "allumee" ? l > 0 : l >= atoi(b.c_str());
        verifier(ok, "lumière " + b + " (vaut " + std::to_string(l) + ")", numero);
      } else if (a == "voyant") {
        int n = b == "socle" ? NB_VOYANTS : atoi(b.c_str()) - 1;
        uint32_t attendu = Moteur::couleurNommee(c);
        verifier(m.couleurVoyant(n) == attendu, "voyant " + b + " " + c, numero);
      } else if (a == "zone") {
        verifier(m.zone(zone(b)) == (c == "-" ? "" : c), "zone " + b + " = " + c + " (porte « " + m.zone(zone(b)) + " »)", numero);
      } else if (a == "ecran") verifier(m.vue().titre.find(b) != std::string::npos, "écran « " + b + " »", numero);
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
