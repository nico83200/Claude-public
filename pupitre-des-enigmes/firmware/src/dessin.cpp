#include "dessin.h"

#include <algorithm>
#include <cstring>

using pupitre::Vue;

namespace {

constexpr int L = 480, H = 320;
constexpr int Y_CORPS = 48, Y_INFO = 248, Y_ZONES = 272;
constexpr int HAUT_CORPS = Y_INFO - Y_CORPS;
// Le minuteur des épreuves, en haut à droite du corps.
constexpr int MIN_X = 438, MIN_Y = 84, MIN_R = 26;

// Les quatre boutons de couleur, teintes modernes.
constexpr uint32_t BOUTONS[4] = {0xe5484d, 0x3e7bfa, 0xf5b83d, 0x30a46c};
constexpr uint32_t FEU_VERT = 0x30c46c, FEU_ROUGE = 0xe5484d;

uint32_t melange(uint32_t a, uint32_t b, float t) {
  auto canal = [&](int d) {
    int x = (a >> d) & 0xff, y = (b >> d) & 0xff;
    return static_cast<uint32_t>(x + (y - x) * t) << d;
  };
  return canal(16) | canal(8) | canal(0);
}

// Les polices n'ont pas les emoji : ils sont retirés (l'écran dessine ses propres pictogrammes).
std::string nettoyer(std::string s) {
  static const char* const remplacements[][2] = {
      {"🎧 ", ""}, {"🎧", ""}, {"✉  ", ""}, {"✉ ", ""}, {"✉", ""}, {"⏱ ", ""}, {"⏱", ""}, {"⏸", "II"}, {"\xc2\xad", ""}};
  for (auto& r : remplacements) {
    size_t p;
    while ((p = s.find(r[0])) != std::string::npos) s.replace(p, strlen(r[0]), r[1]);
  }
  while (!s.empty() && s[0] == ' ') s.erase(0, 1);
  return s;
}

// « Le Bureau du directeur » -> « Bureau du directeur » (tuiles des lecteurs).
std::string sansArticle(const std::string& nom) {
  for (const char* a : {"Le ", "La ", "Les ", "L'", "L’"})
    if (nom.rfind(a, 0) == 0 && nom.size() > strlen(a) + 2) return nom.substr(strlen(a));
  return nom;
}

bool commencePar(const std::string& s, const char* debut) { return s.rfind(debut, 0) == 0; }

bool memeCorps(const Vue& a, const Vue& b) {
  if (a.ecran != b.ecran || a.titre != b.titre || a.texte != b.texte || a.auteur != b.auteur || a.bandeau != b.bandeau ||
      a.barre != b.barre || a.champs != b.champs || a.curseur != b.curseur || a.modules != b.modules || a.valeurs != b.valeurs ||
      a.etiquettes != b.etiquettes || a.maximums != b.maximums || a.menu != b.menu || a.voyant != b.voyant ||
      a.pasFaits != b.pasFaits || a.pasTotal != b.pasTotal || a.image != b.image || (a.secondes < 0) != (b.secondes < 0))
    return false;
  for (int i = 0; i < 4; i++)
    if (a.options[i] != b.options[i]) return false;
  if (a.ecran == Vue::ACCUSATION || a.ecran == Vue::TABLEAU) {
    for (int i = 0; i < 3; i++)
      if (a.zones[i] != b.zones[i] || a.nomsZones[i] != b.nomsZones[i]) return false;
  }
  if (a.ecran == Vue::TABLEAU &&
      (a.objectifs != b.objectifs || a.etatsObjectifs != b.etatsObjectifs || a.entete != b.entete || a.compteurs != b.compteurs ||
       a.lumiere != b.lumiere))
    return false;
  return true;
}

float facteurLumiere(const Vue& v) {
  if (!v.dynamo || v.lumiere >= 40) return 1.0f;
  return 0.30f + 0.70f * static_cast<float>(v.lumiere / 5 * 5) / 40.0f;
}

bool dansLeNoir(const Vue& v) {
  return v.dynamo && v.lumiere == 0 && v.ecran != Vue::RESULTAT && v.ecran != Vue::MENU && v.ecran != Vue::ACCUEIL &&
         v.ecran != Vue::REGLAGES;
}

}  // namespace

// ======================================================================
// Outils
// ======================================================================

uint32_t Dessin::c(uint32_t rgb) const {
  if (facteur_ >= 0.999f) return rgb;
  return melange(0x000000, rgb, facteur_);
}

int Dessin::largeur(const std::string& t, const lgfx::IFont* police) {
  g_.setFont(police);
  return g_.textWidth(nettoyer(t).c_str());
}

void Dessin::ecrire(const std::string& brut, int x, int y, const lgfx::IFont* police, uint32_t fg, uint32_t bg, int ancrage) {
  std::string t = nettoyer(brut);
  g_.setFont(police);
  g_.setTextColor(c(fg), c(bg));
  int w = g_.textWidth(t.c_str());
  if (ancrage == 1) x -= w / 2;
  if (ancrage == 2) x -= w;
  g_.drawString(t.c_str(), x, y);
}

std::string Dessin::couper(const std::string& brut, const lgfx::IFont* police, int l) {
  std::string t = nettoyer(brut);
  g_.setFont(police);
  if (g_.textWidth(t.c_str()) <= l) return t;
  while (!t.empty() && g_.textWidth((t + "…").c_str()) > l) {
    size_t p = t.size() - 1;
    while (p > 0 && (static_cast<uint8_t>(t[p]) & 0xc0) == 0x80) p--;
    t.erase(p);
  }
  while (!t.empty() && t.back() == ' ') t.pop_back();
  return t + "…";
}

// Texte à la ligne automatiquement (mots entiers). Renvoie le y sous la dernière ligne.
int Dessin::paragraphe(const std::string& brut, int x, int y, int l, int yMax, const lgfx::IFont* police, uint32_t fg,
                       uint32_t bg, bool centre, int interligne, bool dessiner) {
  std::string t = nettoyer(brut);
  g_.setFont(police);
  if (dessiner) g_.setTextColor(c(fg), c(bg));
  size_t i = 0;
  while (i < t.size() && y + interligne <= yMax + 4) {
    size_t finLigne = t.find('\n', i);
    if (finLigne == std::string::npos) finLigne = t.size();
    std::string ligne;
    size_t j = i;
    while (j < finLigne) {
      size_t espace = t.find(' ', j);
      if (espace == std::string::npos || espace > finLigne) espace = finLigne;
      std::string essai = ligne.empty() ? t.substr(j, espace - j) : ligne + " " + t.substr(j, espace - j);
      if (!ligne.empty() && g_.textWidth(essai.c_str()) > l) break;
      ligne = essai;
      j = espace + 1;
    }
    if (dessiner) {
      int px = centre ? x + (l - g_.textWidth(ligne.c_str())) / 2 : x;
      g_.drawString(ligne.c_str(), px, y);
    }
    y += interligne;
    i = j >= finLigne ? finLigne + 1 : j;
  }
  return y;
}

// La plus grande police dans laquelle le texte tient (18, 15 puis 13 px).
const lgfx::IFont* Dessin::policeQuiTient(const std::string& t, int l, int h, int* interligne) {
  struct Choix {
    const lgfx::IFont* police;
    int interligne;
  };
  const Choix choix[] = {{&polices::texte, 25}, {&polices::compact, 21}, {&polices::petit, 17}};
  for (const auto& ch : choix) {
    if (paragraphe(t, 0, 0, l, 100000, ch.police, 0, 0, false, ch.interligne, false) <= h) {
      *interligne = ch.interligne;
      return ch.police;
    }
  }
  *interligne = 17;
  return &polices::petit;
}

// ======================================================================
// Formes et pictogrammes
// ======================================================================

void Dessin::pastille(int x, int y, int w, int h, uint32_t fond) { g_.fillRoundRect(x, y, w, h, h / 2, c(fond)); }

void Dessin::bandeau(const std::string& t, int x, int y, int w, bool enveloppe) {
  uint32_t fond = enveloppe ? pal_.accent : pal_.alerte;
  uint32_t encre = enveloppe ? pal_.fond : 0xffffff;
  pastille(x, y, w, 32, fond);
  std::string s = nettoyer(t);
  int lt = largeur(s, &polices::texteGras) + (enveloppe ? 30 : 0);
  int x0 = x + (w - lt) / 2;
  if (enveloppe) {
    iconeEnveloppe(x0, y + 9, encre, fond);
    x0 += 30;
  }
  ecrire(s, x0, y + 7, &polices::texteGras, encre, fond);
}

void Dessin::coche(int x, int y, int t, uint32_t couleur) {
  // Une coche épaisse : deux traits doublés.
  for (int e = 0; e < 3; e++) {
    g_.drawLine(x, y + t / 2 + e - 1, x + t / 3, y + t - 1 + e - 1, c(couleur));
    g_.drawLine(x + t / 3, y + t - 1 + e - 1, x + t, y + e - 1, c(couleur));
  }
}

void Dessin::iconeEnveloppe(int x, int y, uint32_t couleur, uint32_t fond) {
  g_.fillRoundRect(x, y, 22, 15, 2, c(couleur));
  g_.fillTriangle(x + 2, y + 2, x + 20, y + 2, x + 11, y + 9, c(fond));
  g_.fillTriangle(x + 4, y + 2, x + 18, y + 2, x + 11, y + 7, c(couleur));
}

void Dessin::iconeCasque(int cx, int cy, int r, uint32_t couleur) {
  g_.fillArc(cx, cy, r - 7, r, 180, 360, c(couleur));
  g_.fillRoundRect(cx - r - 6, cy - 6, 16, r + 4, 6, c(couleur));
  g_.fillRoundRect(cx + r - 10, cy - 6, 16, r + 4, 6, c(couleur));
}

void Dessin::iconeAmpoule(int cx, int cy, uint32_t couleur) {
  g_.fillCircle(cx, cy - 2, 6, c(couleur));
  g_.fillRect(cx - 3, cy + 4, 7, 2, c(couleur));
  g_.fillRect(cx - 2, cy + 7, 5, 2, c(couleur));
}

void Dessin::iconeHorloge(int cx, int cy, uint32_t couleur, uint32_t fond) {
  g_.fillCircle(cx, cy, 8, c(couleur));
  g_.fillCircle(cx, cy, 6, c(fond));
  g_.drawLine(cx, cy, cx, cy - 4, c(couleur));
  g_.drawLine(cx, cy, cx + 3, cy + 1, c(couleur));
}

void Dessin::points(int cx, int y, int faits, int total) {
  if (total <= 0) return;
  int pas = total > 10 ? 18 : 24;
  int x = cx - (total - 1) * pas / 2;
  for (int i = 0; i < total; i++, x += pas) {
    if (i < faits) {
      g_.fillCircle(x, y, 8, c(pal_.accent));
    } else {
      g_.fillCircle(x, y, 8, c(pal_.trait));
      g_.fillCircle(x, y, 6, c(pal_.fond));
    }
  }
}

// ======================================================================
// Affichage
// ======================================================================

void Dessin::message(const char* titre, const char* texte) {
  polices::charger();
  g_.fillScreen(0x101418u);
  ecrire(titre, L / 2, 112, &polices::titre, 0xf0c060, 0x101418, 1);
  paragraphe(texte, 40, 156, L - 80, H - 20, &polices::compact, 0xd8dde3, 0x101418, true, 21);
}

void Dessin::afficher(const Vue& v, uint32_t t) {
  polices::charger();
  bool clignoter = v.aideClignote && t - dernierClignotement_ > 600;
  if (clignoter) {
    clignote_ = !clignote_;
    dernierClignotement_ = t;
  }
  if (!premiere_ && v.version == derniere_.version && v.versionEntete == derniere_.versionEntete && !clignoter &&
      v.aideClignote == derniere_.aideClignote)
    return;

  // La palette du monde, et l'assombrissement quand la dynamo faiblit.
  float facteur = facteurLumiere(v);
  bool noirAvant = !premiere_ && dansLeNoir(derniere_);
  bool tout = premiere_ || v.theme.fond != derniere_.theme.fond || v.theme.accent != derniere_.theme.accent ||
              facteur != facteur_ || dansLeNoir(v) != noirAvant;
  facteur_ = facteur;
  const auto& th = v.theme;
  pal_ = {th.fond,
          melange(th.fond, th.texte, 0.07f),
          melange(th.fond, th.texte, 0.13f),
          melange(th.fond, th.texte, 0.22f),
          th.texte,
          th.doux,
          th.accent,
          melange(th.fond, th.accent, 0.20f),
          th.alerte};

  g_.startWrite();
  if (dansLeNoir(v)) {
    if (tout) noir();
  } else {
    if (tout) g_.fillScreen(c(pal_.fond));
    bool corpsChange = tout || !memeCorps(v, derniere_);
    if (tout || v.entete != derniere_.entete || v.compteurs != derniere_.compteurs || v.monde != derniere_.monde ||
        v.mission != derniere_.mission || v.restantPourMille != derniere_.restantPourMille || v.lumiere != derniere_.lumiere ||
        clignoter || v.aideClignote != derniere_.aideClignote || v.dynamo != derniere_.dynamo)
      entete(v);
    if (corpsChange) corps(v);
    if (corpsChange || v.secondes != derniere_.secondes) minuteur(v);
    if (tout || corpsChange || v.info != derniere_.info || v.progression != derniere_.progression ||
        v.objectifs != derniere_.objectifs || v.etatsObjectifs != derniere_.etatsObjectifs)
      ligneInfo(v);
    bool zones = tout;
    for (int i = 0; i < 3; i++) zones |= v.zones[i] != derniere_.zones[i] || v.nomsZones[i] != derniere_.nomsZones[i];
    if (zones) barreZones(v);
  }
  g_.endWrite();
  derniere_ = v;
  premiere_ = false;
}

void Dessin::noir() {
  float f = facteur_;
  facteur_ = 1.0f;  // ces deux lignes, à peine visibles, ne s'assombrissent pas davantage
  g_.fillScreen(0x000000u);
  ecrire("Noir complet", L / 2, 128, &polices::titre, 0x5a5f66, 0x000000, 1);
  ecrire("Actionnez la dynamo !", L / 2, 166, &polices::compact, 0x9a7428, 0x000000, 1);
  facteur_ = f;
}

// ---------------------------------------------------------------------
// L'en-tête : monde et compteurs à gauche, lumière et chrono à droite,
// et la barre du temps restant.
// ---------------------------------------------------------------------
void Dessin::entete(const Vue& v) {
  g_.fillRect(0, 0, L, Y_CORPS - 2, c(pal_.fond));
  int droite = L - 10;

  if (!v.entete.empty()) {
    std::string e = nettoyer(v.entete);
    int lt = largeur(e, &polices::titre);
    int w = lt + 44;
    bool urgent = v.restantPourMille >= 0 && v.restantPourMille < 200;
    uint32_t couleur = urgent ? pal_.alerte : pal_.accent;
    pastille(droite - w, 6, w, 32, pal_.surface2);
    iconeHorloge(droite - w + 18, 22, couleur, pal_.surface2);
    ecrire(e, droite - 14, 9, &polices::titre, couleur, pal_.surface2, 2);
    droite -= w + 8;
  }
  if (v.dynamo) {
    // Le témoin de lumière : une ampoule et une jauge.
    int w = 78;
    bool faible = v.lumiere < 30;
    uint32_t couleur = faible ? pal_.alerte : pal_.accent;
    pastille(droite - w, 6, w, 32, pal_.surface2);
    iconeAmpoule(droite - w + 18, 21, couleur);
    g_.fillRoundRect(droite - w + 32, 18, 36, 8, 4, c(pal_.trait));
    g_.fillRoundRect(droite - w + 32, 18, std::max(4, 36 * v.lumiere / 100), 8, 4, c(couleur));
    droite -= w + 8;
  }
  if (v.aideClignote && clignote_) {
    pastille(droite - 76, 9, 76, 26, pal_.alerte);
    ecrire("AIDE ?", droite - 38, 13, &polices::texteGras, 0xffffff, pal_.alerte, 1);
    droite -= 84;
  }

  int place = droite - 14;
  std::string haut = v.mission.empty() ? v.monde : v.monde + "  ·  " + v.mission;
  if (!v.mission.empty() && largeur(haut, &polices::petit) > place) haut = v.mission;  // trop long : la mission suffit
  ecrire(couper(haut, &polices::petit, place), 12, 6, &polices::petit, pal_.doux, pal_.fond);
  if (!v.compteurs.empty()) ecrire(couper(v.compteurs, &polices::compact, place), 12, 23, &polices::compact, pal_.texte, pal_.fond);

  // La barre du temps restant.
  if (v.restantPourMille >= 0) {
    g_.fillRect(0, Y_CORPS - 5, L, 3, c(pal_.surface2));
    uint32_t couleur = v.restantPourMille < 200 ? pal_.alerte : pal_.accent;
    g_.fillRect(0, Y_CORPS - 5, L * v.restantPourMille / 1000, 3, c(couleur));
  }
}

// ---------------------------------------------------------------------
// Le corps
// ---------------------------------------------------------------------
void Dessin::corps(const Vue& v) {
  g_.fillRect(0, Y_CORPS, L, HAUT_CORPS, c(pal_.fond));
  switch (v.ecran) {
    case Vue::ACCUEIL: corpsAccueil(v); return;
    case Vue::MENU: corpsMenu(v); return;
    case Vue::CODE: corpsCode(v); return;
    case Vue::CHOIX: corpsChoix(v); return;
    case Vue::ENERGIE: corpsEnergie(v); return;
    case Vue::EPREUVE: corpsEpreuve(v); return;
    case Vue::CASQUE: corpsCasque(v); return;
    case Vue::RESULTAT: corpsResultat(v); return;
    case Vue::ACCUSATION: corpsAccusation(v); return;
    case Vue::TABLEAU: corpsTableau(v); return;
    case Vue::REGLAGES: corpsReglages(v); return;
    default: corpsTexte(v); return;
  }
}

// Le récit : une carte avec titre, voix, texte et bandeau (enveloppe ou avertissement),
// et une illustration à gauche quand la carte SD en a une.
void Dessin::corpsTexte(const Vue& v) {
  if (v.ecran == Vue::ENROLEMENT) {
    corpsEnrolement(v);
    return;
  }
  const int x = 10, y = Y_CORPS + 2, w = L - 20, h = HAUT_CORPS - 4;
  g_.fillRoundRect(x, y, w, h, 14, c(pal_.surface));
  int gauche = x + 16, l = w - 32;
  if (!v.imageDonnees.empty()) {
    // L'illustration : 150 × 150 au maximum, collée au bord gauche de la carte.
    const uint8_t* d = reinterpret_cast<const uint8_t*>(v.imageDonnees.data());
    bool png = v.imageDonnees.size() > 4 && v.imageDonnees[1] == 'P' && v.imageDonnees[2] == 'N';
    if (png) g_.drawPng(d, v.imageDonnees.size(), x + 8, y + 8, 150, h - 16);
    else g_.drawJpg(d, v.imageDonnees.size(), x + 8, y + 8, 150, h - 16);
    gauche = x + 8 + 150 + 14;
    l = x + w - 16 - gauche;
  }
  int yy = y + 12;
  if (!v.titre.empty()) {
    int lt = largeur(v.titre, &polices::titre);
    if (lt <= l) {
      ecrire(v.titre, gauche, yy, &polices::titre, pal_.accent, pal_.surface);
      yy += 32;
    } else {
      yy = paragraphe(v.titre, gauche, yy, l, yy + 60, &polices::texteGras, pal_.accent, pal_.surface, false, 23) + 4;
    }
  }
  if (!v.auteur.empty() && v.titre.rfind(v.auteur, 0) != 0) {  // pas de doublon avec le titre
    std::string a = couper(v.auteur, &polices::petit, l - 20);
    int wa = largeur(a, &polices::petit) + 20;
    pastille(gauche, yy, wa, 22, pal_.accentDoux);
    ecrire(a, gauche + 10, yy + 3, &polices::petit, pal_.accent, pal_.accentDoux);
    yy += 30;
  }
  int bas = y + h - (v.bandeau.empty() ? 10 : 46);
  int interligne;
  const lgfx::IFont* police = policeQuiTient(v.texte, l, bas - yy, &interligne);
  paragraphe(v.texte, gauche, yy, l, bas, police, pal_.texte, pal_.surface, false, interligne);
  if (!v.bandeau.empty())
    bandeau(v.bandeau, gauche, y + h - 42, x + w - 16 - gauche, commencePar(v.bandeau, "✉"));
}

// L'enregistrement des cartes : « Glissez la carte » + identifiant en très grand + nom.
void Dessin::corpsEnrolement(const Vue& v) {
  const int x = 10, y = Y_CORPS + 2, w = L - 20, h = HAUT_CORPS - 4;
  g_.fillRoundRect(x, y, w, h, 14, c(pal_.surface));
  ecrire(couper(v.titre, &polices::petit, w - 32), x + 16, y + 12, &polices::petit, pal_.doux, pal_.surface);
  std::string t = v.texte, ligne1 = t, nom;
  size_t f = t.find('\n');
  if (f != std::string::npos) {
    ligne1 = t.substr(0, f);
    nom = t.substr(f + 1);
  }
  std::string id = ligne1.substr(ligne1.rfind(' ') + 1);
  ecrire("Glissez la carte", L / 2, y + 36, &polices::texte, pal_.texte, pal_.surface, 1);
  ecrire(id, L / 2, y + 62, &polices::chiffres, pal_.accent, pal_.surface, 1);
  ecrire(couper(nom, &polices::titre, w - 40), L / 2, y + 128, &polices::titre, pal_.texte, pal_.surface, 1);
  if (!v.bandeau.empty()) {
    std::string b = nettoyer(v.bandeau);
    int wb = largeur(b, &polices::texteGras) + 30;
    pastille(L / 2 - wb / 2, y + h - 40, wb, 30, pal_.surface2);
    ecrire(b, L / 2, y + h - 35, &polices::texteGras, pal_.texte, pal_.surface2, 1);
  }
}

void Dessin::corpsAccueil(const Vue& v) {
  // Trois lecteurs stylisés au-dessus du titre.
  for (int i = 0; i < 3; i++) {
    int cx = L / 2 + (i - 1) * 56;
    g_.fillCircle(cx, Y_CORPS + 36, 18, c(i == 1 ? pal_.accent : pal_.surface2));
    g_.fillCircle(cx, Y_CORPS + 36, 11, c(pal_.fond));
    g_.fillCircle(cx, Y_CORPS + 36, 5, c(i == 1 ? pal_.accent : pal_.trait));
  }
  int y = paragraphe(v.titre, 30, Y_CORPS + 66, L - 60, Y_CORPS + 150, &polices::grand, pal_.texte, pal_.fond, true, 40);
  g_.fillRoundRect(L / 2 - 30, y + 4, 60, 4, 2, c(pal_.accent));
  paragraphe(v.texte, 40, y + 20, L - 80, Y_INFO - 4, &polices::texte, pal_.doux, pal_.fond, true, 24);
}

void Dessin::corpsMenu(const Vue& v) {
  ecrire(couper(v.titre, &polices::titre, L - 40), 20, Y_CORPS + 6, &polices::titre, pal_.texte, pal_.fond);
  const auto& lignes = v.menu;
  const int hauteur = 38, y0 = Y_CORPS + 46, visibles = 4;
  int debut = std::max(0, std::min(v.curseur - 1, static_cast<int>(lignes.size()) - visibles));
  for (int i = debut; i < static_cast<int>(lignes.size()) && i < debut + visibles; i++) {
    int y = y0 + (i - debut) * (hauteur + 6);
    bool actif = i == v.curseur;
    uint32_t fond = actif ? pal_.accentDoux : pal_.surface;
    g_.fillRoundRect(10, y, L - 20, hauteur, 10, c(fond));
    if (actif) g_.fillRoundRect(10, y + 6, 5, hauteur - 12, 2, c(pal_.accent));
    // Le numéro de la ligne : la touche du clavier qui la choisit.
    if (i < 9) {
      uint32_t pastilleFond = actif ? pal_.accent : pal_.surface2;
      g_.fillCircle(36, y + hauteur / 2, 11, c(pastilleFond));
      ecrire(std::to_string(i + 1), 36, y + 11, &polices::petit, actif ? pal_.fond : pal_.doux, pastilleFond, 1);
    }
    ecrire(couper(lignes[i], &polices::texteGras, L - 100), 56, y + 9, actif ? &polices::texteGras : &polices::texte,
           actif ? pal_.texte : pal_.doux, fond);
    if (actif) ecrire("›", L - 34, y + 7, &polices::titre, pal_.accent, fond);
  }
  if (lignes.size() > static_cast<size_t>(visibles)) {
    int total = static_cast<int>(lignes.size());
    int hBarre = Y_INFO - 8 - y0;
    g_.fillRoundRect(L - 6, y0, 3, hBarre, 1, c(pal_.surface2));
    g_.fillRoundRect(L - 6, y0 + hBarre * debut / total, 3, hBarre * visibles / total, 1, c(pal_.accent));
  }
}

void Dessin::corpsCode(const Vue& v) {
  int lt = v.secondes >= 0 ? L - 110 : L - 30;
  ecrire(couper(v.titre, &polices::titre, lt), L / 2, Y_CORPS + 8, &polices::titre, pal_.accent, pal_.fond, 1);
  paragraphe(v.texte, 60, Y_CORPS + 44, L - 120, Y_CORPS + 84, &polices::compact, pal_.doux, pal_.fond, true, 20);
  // Les cases : une par champ ; chaque caractère dans la grande police.
  int n = static_cast<int>(v.champs.size());
  std::vector<int> largeurs;
  int total = 0;
  for (const auto& ch : v.champs) {
    int nb = std::max<int>(1, ch.size());
    int w = 30 + 34 * nb;
    largeurs.push_back(w);
    total += w;
  }
  int ecart = n > 6 ? 6 : 10;
  float echelle = 1.0f;
  if (total + ecart * (n - 1) > L - 30) echelle = static_cast<float>(L - 30 - ecart * (n - 1)) / total;
  int x = (L - static_cast<int>(total * echelle) - ecart * (n - 1)) / 2, y = Y_CORPS + 96, h = 86;
  for (int i = 0; i < n; i++) {
    int w = static_cast<int>(largeurs[i] * echelle);
    bool actif = i == v.curseur;
    uint32_t fond = actif ? pal_.accentDoux : pal_.surface2;
    g_.fillRoundRect(x, y, w, h, 12, c(actif ? pal_.accent : pal_.surface2));
    g_.fillRoundRect(x + 3, y + 3, w - 6, h - 6, 10, c(fond));
    std::string s = v.champs[i];
    bool vide = s.find_first_not_of('_') == std::string::npos;
    if (vide) {
      g_.fillRoundRect(x + w / 2 - 14, y + h - 24, 28, 4, 2, c(actif ? pal_.accent : pal_.trait));
    } else {
      ecrire(s, x + w / 2, y + 12, &polices::chiffres, actif ? pal_.accent : pal_.texte, fond, 1);
    }
    x += w + ecart;
  }
}

void Dessin::corpsChoix(const Vue& v) {
  int lt = v.secondes >= 0 ? L - 110 : L - 30;
  const lgfx::IFont* pt = largeur(v.titre, &polices::titre) <= lt ? &polices::titre : &polices::texteGras;
  ecrire(couper(v.titre, pt, lt), L / 2, Y_CORPS + 6, pt, pal_.accent, pal_.fond, 1);
  paragraphe(v.texte, 20, Y_CORPS + 40, L - 40, Y_CORPS + 80, &polices::compact, pal_.texte, pal_.fond, true, 20);
  const int w = 226, h = 52;
  for (int i = 0; i < 4; i++) {
    int x = i % 2 == 0 ? 10 : L - 10 - w;
    int y = Y_CORPS + 84 + (i / 2) * (h + 6);
    bool barre = i == v.barre || v.options[i].empty();
    uint32_t fond = barre ? pal_.surface : BOUTONS[i];
    uint32_t encre = barre ? pal_.trait : (i == 2 ? 0x1c1a14u : 0xffffffu);
    g_.fillRoundRect(x, y, w, h, 12, c(fond));
    // La pastille du bouton à presser.
    g_.fillCircle(x + 22, y + h / 2, 10, c(barre ? pal_.surface2 : 0xffffff));
    g_.fillCircle(x + 22, y + h / 2, 6, c(barre ? pal_.surface : BOUTONS[i]));
    if (!v.options[i].empty()) {
      int interligne;
      const lgfx::IFont* police = policeQuiTient(v.options[i], w - 52, h - 10, &interligne);
      if (police == &polices::texte) police = &polices::texteGras;
      int hauteurTexte = paragraphe(v.options[i], 0, 0, w - 52, 1000, police, 0, 0, false, interligne, false);
      paragraphe(v.options[i], x + 42, y + (h - hauteurTexte) / 2 + 2, w - 52, y + h, police, encre, fond, false, interligne);
    }
    if (i == v.barre) g_.drawLine(x + 12, y + h / 2, x + w - 12, y + h / 2, c(pal_.trait));
  }
}

void Dessin::corpsEnergie(const Vue& v) {
  // La somme répartie, en pastille.
  std::string somme = nettoyer(v.bandeau);
  int ws = largeur(somme, &polices::texteGras) + 24;
  int lt = L - ws - 40;
  const lgfx::IFont* pt = largeur(v.titre, &polices::titre) <= lt ? &polices::titre : &polices::texteGras;
  ecrire(couper(v.titre, pt, lt), 16, Y_CORPS + (pt == &polices::titre ? 6 : 12), pt, pal_.accent, pal_.fond);
  pastille(L - 10 - ws, Y_CORPS + 8, ws, 28, pal_.surface2);
  ecrire(somme, L - 10 - ws / 2, Y_CORPS + 12, &polices::texteGras, pal_.texte, pal_.surface2, 1);
  int n = static_cast<int>(v.modules.size());
  int pas = n > 5 ? 25 : 30;
  int y = Y_CORPS + 46;
  for (int i = 0; i < n; i++, y += pas) {
    bool actif = i == v.curseur;
    uint32_t fond = actif ? pal_.accentDoux : pal_.fond;
    if (actif) g_.fillRoundRect(8, y - 2, L - 16, pas - 1, 8, c(fond));
    ecrire(couper(v.modules[i], &polices::compact, 170), 22, y + 1, actif ? &polices::texteGras : &polices::compact,
           actif ? pal_.texte : pal_.doux, fond);
    int valeur = i < static_cast<int>(v.valeurs.size()) ? v.valeurs[i] : 0;
    for (int k = 0; k < 5; k++)
      g_.fillRoundRect(206 + k * 42, y + 4, 36, pas - 12, 5, c(k < valeur ? (actif ? pal_.accent : pal_.texte) : pal_.surface2));
    ecrire(std::to_string(valeur), L - 26, y, &polices::texteGras, actif ? pal_.accent : pal_.texte, fond, 2);
  }
}

// Les épreuves d'adresse et de mémoire : une consigne en très gros, un voyant, des pas.
void Dessin::corpsEpreuve(const Vue& v) {
  int lt = v.secondes >= 0 ? L - 130 : L - 30;
  const lgfx::IFont* pt = largeur(v.titre, &polices::titre) <= lt ? &polices::titre : &polices::texteGras;
  ecrire(couper(v.titre, pt, lt), L / 2, Y_CORPS + 8, pt, pal_.accent, pal_.fond, 1);
  int haut = Y_CORPS + 50, bas = Y_INFO - (v.pasTotal > 0 ? 36 : 8);
  if (v.voyant >= 0) bas -= 84;
  std::string t = v.texte;
  // La consigne : la plus grande police qui tient.
  const lgfx::IFont* police = &polices::grand;
  int interligne = 40;
  if (paragraphe(t, 0, 0, L - 80, 10000, police, 0, 0, false, interligne, false) > bas - haut) {
    police = &polices::titre;
    interligne = 31;
    if (paragraphe(t, 0, 0, L - 80, 10000, police, 0, 0, false, interligne, false) > bas - haut) {
      police = &polices::texte;
      interligne = 24;
    }
  }
  int hauteur = paragraphe(t, 0, 0, L - 80, 10000, police, 0, 0, false, interligne, false);
  paragraphe(t, 40, haut + std::max(0, (bas - haut - hauteur) / 2), L - 80, bas, police, pal_.texte, pal_.fond, true, interligne);
  if (v.voyant >= 0) {
    uint32_t couleur = v.voyant < 4 ? BOUTONS[v.voyant] : v.voyant == 4 ? FEU_VERT : FEU_ROUGE;
    int cy = bas + 42;
    g_.fillCircle(L / 2, cy, 38, c(melange(pal_.fond, couleur, 0.35f)));
    g_.fillCircle(L / 2, cy, 30, c(couleur));
  }
  if (v.pasTotal > 0) points(L / 2, Y_INFO - 20, v.pasFaits, v.pasTotal);
}

void Dessin::corpsCasque(const Vue& v) {
  iconeCasque(L / 2, Y_CORPS + 62, 34, pal_.accent);
  ecrire(couper(v.titre, &polices::titre, L - 40), L / 2, Y_CORPS + 98, &polices::titre, pal_.texte, pal_.fond, 1);
  int bas = Y_INFO - (v.bandeau.empty() ? 8 : 48);
  paragraphe(v.texte, 40, Y_CORPS + 134, L - 80, bas, &polices::texte, pal_.doux, pal_.fond, true, 24);
  if (!v.bandeau.empty()) bandeau(v.bandeau, 40, Y_INFO - 42, L - 80, false);
}

void Dessin::corpsResultat(const Vue& v) {
  bool victoire = v.titre.find("RÉUSSIE") != std::string::npos;
  std::string titre = victoire ? "Mission réussie" : "Mission échouée";
  ecrire(titre, L / 2, Y_CORPS + 4, &polices::grand, victoire ? pal_.accent : pal_.alerte, pal_.fond, 1);
  // Les lignes « Libellé : valeur » deviennent des tuiles ; une ligne seule, c'est le rang.
  std::vector<std::pair<std::string, std::string>> tuiles;
  std::string rang, t = v.texte;
  size_t i = 0;
  while (i < t.size()) {
    size_t f = t.find('\n', i);
    if (f == std::string::npos) f = t.size();
    std::string ligne = t.substr(i, f - i);
    size_t sep = ligne.find(" : ");
    if (sep == std::string::npos) rang = ligne;
    else tuiles.emplace_back(ligne.substr(0, sep), ligne.substr(sep + 3));
    i = f + 1;
  }
  int y = Y_CORPS + 52;
  if (!rang.empty()) {
    std::string r = "★  " + rang;
    int w = largeur(r, &polices::texteGras) + 36;
    pastille(L / 2 - w / 2, y, w, 34, pal_.accent);
    ecrire(r, L / 2, y + 7, &polices::texteGras, pal_.fond, pal_.accent, 1);
  }
  y += 48;
  int n = std::max<int>(1, tuiles.size());
  int ecart = 8, w = (L - 20 - ecart * (n - 1)) / n;
  for (int k = 0; k < static_cast<int>(tuiles.size()); k++) {
    int x = 10 + k * (w + ecart);
    g_.fillRoundRect(x, y, w, 70, 12, c(pal_.surface));
    ecrire(couper(tuiles[k].first, &polices::petit, w - 16), x + w / 2, y + 10, &polices::petit, pal_.doux, pal_.surface, 1);
    std::string valeur = tuiles[k].second;
    const lgfx::IFont* police = largeur(valeur, &polices::titre) <= w - 16 ? &polices::titre : &polices::texteGras;
    ecrire(valeur, x + w / 2, y + 32, police, pal_.texte, pal_.surface, 1);
  }
  if (!v.bandeau.empty()) {
    std::string b = nettoyer(v.bandeau);
    ecrire(b, L / 2, Y_INFO - 22, &polices::compact, pal_.doux, pal_.fond, 1);
  }
}

void Dessin::corpsAccusation(const Vue& v) {
  ecrire(couper(v.titre, &polices::titre, L - 40), L / 2, Y_CORPS + 6, &polices::titre, pal_.alerte, pal_.fond, 1);
  int y = Y_CORPS + 46;
  for (int z = 0; z < 3; z++, y += 44) {
    g_.fillRoundRect(10, y, L - 20, 38, 10, c(pal_.surface));
    ecrire(v.nomsZones[z], 26, y + 11, &polices::petit, pal_.doux, pal_.surface);
    ecrire(couper(v.zones[z].empty() ? "—" : v.zones[z], &polices::texteGras, L - 160), 130, y + 8, &polices::texteGras,
           pal_.texte, pal_.surface);
  }
  ecrire(nettoyer(v.texte), L / 2, Y_INFO - 26, &polices::compact, pal_.accent, pal_.fond, 1);
}

// Le tableau de bord : les objectifs, le temps, la lumière.
void Dessin::corpsTableau(const Vue& v) {
  const int xg = 10, wg = 290;
  g_.fillRoundRect(xg, Y_CORPS + 2, wg, HAUT_CORPS - 6, 14, c(pal_.surface));
  ecrire("OBJECTIFS", xg + 16, Y_CORPS + 12, &polices::petit, pal_.doux, pal_.surface);
  int faits = 0;
  for (int e : v.etatsObjectifs) faits += e == 2;
  std::string compte = std::to_string(faits) + " / " + std::to_string(v.objectifs.size());
  ecrire(compte, xg + wg - 16, Y_CORPS + 12, &polices::petit, pal_.accent, pal_.surface, 2);
  int n = static_cast<int>(v.objectifs.size());
  int pas = n > 7 ? 21 : 24;
  const lgfx::IFont* police = n > 7 ? &polices::petit : &polices::compact;
  int y = Y_CORPS + 36;
  for (int i = 0; i < n && y + pas <= Y_INFO - 4; i++, y += pas) {
    bool fait = v.etatsObjectifs[i] == 2;
    if (fait) {
      g_.fillCircle(xg + 24, y + 9, 8, c(pal_.accent));
      coche(xg + 19, y + 5, 10, pal_.surface);
    } else {
      g_.fillCircle(xg + 24, y + 9, 8, c(pal_.trait));
      g_.fillCircle(xg + 24, y + 9, 6, c(pal_.surface));
    }
    ecrire(couper(v.objectifs[i], police, wg - 56), xg + 40, y + (police == &polices::petit ? 2 : 0), police,
           fait ? pal_.doux : pal_.texte, pal_.surface);
  }
  // À droite : le temps, la lumière, l'aide.
  const int xd = xg + wg + 10, wd = L - 10 - xd;
  g_.fillRoundRect(xd, Y_CORPS + 2, wd, 92, 14, c(pal_.surface));
  ecrire("TEMPS", xd + 14, Y_CORPS + 12, &polices::petit, pal_.doux, pal_.surface);
  bool urgent = v.restantPourMille >= 0 && v.restantPourMille < 200;
  ecrire(couper(v.entete, &polices::grand, wd - 20), xd + wd / 2, Y_CORPS + 36, &polices::grand, urgent ? pal_.alerte : pal_.accent,
         pal_.surface, 1);
  int y2 = Y_CORPS + 102;
  if (v.dynamo) {
    g_.fillRoundRect(xd, y2, wd, 56, 14, c(pal_.surface));
    ecrire("LUMIÈRE", xd + 14, y2 + 10, &polices::petit, pal_.doux, pal_.surface);
    ecrire(std::to_string(v.lumiere) + " %", xd + wd - 14, y2 + 8, &polices::texteGras, pal_.texte, pal_.surface, 2);
    g_.fillRoundRect(xd + 14, y2 + 36, wd - 28, 8, 4, c(pal_.trait));
    g_.fillRoundRect(xd + 14, y2 + 36, std::max(4, (wd - 28) * v.lumiere / 100), 8, 4,
                     c(v.lumiere < 30 ? pal_.alerte : pal_.accent));
    y2 += 64;
  }
  if (!v.compteurs.empty())
    paragraphe(v.compteurs, xd + 4, y2 + 4, wd - 8, Y_INFO - 4, &polices::petit, pal_.doux, pal_.fond, false, 17);
}

void Dessin::corpsReglages(const Vue& v) {
  ecrire(v.titre, 20, Y_CORPS + 4, &polices::titre, pal_.accent, pal_.fond);
  int y = Y_CORPS + 40;
  for (size_t i = 0; i < v.modules.size(); i++, y += 39) {
    bool actif = static_cast<int>(i) == v.curseur;
    uint32_t fond = actif ? pal_.accentDoux : pal_.surface;
    g_.fillRoundRect(10, y, L - 20, 35, 10, c(fond));
    if (actif) g_.fillRoundRect(10, y + 7, 5, 21, 2, c(pal_.accent));
    ecrire(v.modules[i], 26, y + 3, actif ? &polices::texteGras : &polices::compact, actif ? pal_.texte : pal_.doux, fond);
    std::string e = i < v.etiquettes.size() ? v.etiquettes[i] : "";
    ecrire(e, L - 26, y + 3, &polices::texteGras, actif ? pal_.accent : pal_.texte, fond, 2);
    int max = i < v.maximums.size() && v.maximums[i] > 0 ? v.maximums[i] : 1;
    int valeur = i < v.valeurs.size() ? v.valeurs[i] : 0;
    g_.fillRoundRect(26, y + 26, L - 52, 5, 2, c(pal_.trait));
    g_.fillRoundRect(26, y + 26, std::max(6, (L - 52) * valeur / max), 5, 2, c(actif ? pal_.accent : pal_.doux));
  }
}

// Le compte à rebours d'une épreuve : un anneau qui se vide.
void Dessin::minuteur(const Vue& v) {
  bool plein = v.ecran == Vue::CODE || v.ecran == Vue::CHOIX || v.ecran == Vue::EPREUVE;
  if (!plein || v.secondes < 0 || v.secondesTotal <= 0) return;
  g_.fillCircle(MIN_X, MIN_Y, MIN_R + 1, c(pal_.fond));
  g_.fillArc(MIN_X, MIN_Y, MIN_R - 5, MIN_R, 0, 360, c(pal_.surface2));
  float part = static_cast<float>(v.secondes) / v.secondesTotal;
  uint32_t couleur = part < 0.34f ? pal_.alerte : pal_.accent;
  if (part > 0) g_.fillArc(MIN_X, MIN_Y, MIN_R - 5, MIN_R, 270, 270 + 360 * part, c(couleur));
  ecrire(std::to_string(v.secondes), MIN_X, MIN_Y - 11, &polices::texteGras, pal_.texte, pal_.fond, 1);
}

// ---------------------------------------------------------------------
// La ligne d'information : une consigne, ou les objectifs en pastilles.
// ---------------------------------------------------------------------
void Dessin::ligneInfo(const Vue& v) {
  g_.fillRect(0, Y_INFO, L, Y_ZONES - Y_INFO, c(pal_.fond));
  std::string info = v.info;
  if (v.ecran == Vue::TABLEAU) info = "Molette : revenir au jeu";
  if (v.ecran == Vue::ENERGIE && info.empty()) info = v.texte;
  if (!info.empty() || v.ecran != Vue::PRINCIPAL) {
    ecrire(couper(info, &polices::compact, L - 24), L / 2, Y_INFO + 2, &polices::compact, pal_.doux, pal_.fond, 1);
    return;
  }
  // Les objectifs, en pastilles, tant qu'elles tiennent sur la ligne.
  int x = 10;
  int faits = 0;
  for (int e : v.etatsObjectifs) faits += e == 2;
  for (size_t i = 0; i < v.objectifs.size(); i++) {
    bool fait = v.etatsObjectifs[i] == 2;
    std::string t = (fait ? "✓ " : "") + v.objectifs[i];
    int w = largeur(t, &polices::petit) + 18;
    if (x + w > L - 10) {
      std::string r = std::to_string(faits) + "/" + std::to_string(v.objectifs.size()) + " · molette";
      int wr = largeur(r, &polices::petit) + 18;
      if (x + wr <= L - 10) {
        pastille(x, Y_INFO + 1, wr, 20, pal_.surface);
        ecrire(r, x + 9, Y_INFO + 4, &polices::petit, pal_.doux, pal_.surface);
      }
      break;
    }
    uint32_t fond = fait ? pal_.accentDoux : pal_.surface;
    pastille(x, Y_INFO + 1, w, 20, fond);
    ecrire(t, x + 9, Y_INFO + 4, &polices::petit, fait ? pal_.accent : pal_.doux, fond);
    x += w + 6;
  }
}

// ---------------------------------------------------------------------
// Les trois lecteurs, en tuiles.
// ---------------------------------------------------------------------
void Dessin::barreZones(const Vue& v) {
  g_.fillRect(0, Y_ZONES, L, H - Y_ZONES, c(pal_.fond));
  const int w = 148, h = 42, y = Y_ZONES + 2;
  for (int z = 0; z < 3; z++) {
    int x = 10 + z * (w + 8);
    bool pleine = !v.zones[z].empty();
    uint32_t fond = pleine ? pal_.surface2 : pal_.surface;
    g_.fillRoundRect(x, y, w, h, 10, c(fond));
    if (pleine) g_.fillRoundRect(x, y + 9, 4, h - 18, 2, c(pal_.accent));
    ecrire(couper(v.nomsZones[z], &polices::petit, w - 24), x + 12, y + 4, &polices::petit, pleine ? pal_.accent : pal_.doux, fond);
    std::string nom = pleine ? sansArticle(v.zones[z]) : "—";
    const lgfx::IFont* police = largeur(nom, &polices::texteGras) <= w - 24 ? &polices::texteGras : &polices::compact;
    ecrire(couper(nom, police, w - 24), x + 12, y + 19, police, pleine ? pal_.texte : pal_.trait, fond);
  }
}
