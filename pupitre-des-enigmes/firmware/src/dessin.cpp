#include "dessin.h"

#include <cstring>

using pupitre::Vue;

namespace {

constexpr int L = 480, H = 320;
constexpr int Y_CORPS = 32, Y_INFO = 266, Y_ZONES = 290;
constexpr uint32_t COULEURS_BOUTONS[4] = {0xc0392b, 0x2e6fd8, 0xe0b020, 0x2e9e4f};
const char* const NOMS_ZONES[3] = {"QUI", "OÙ", "QUOI"};

// Les polices efont n'ont pas quelques symboles : on les remplace par des voisins.
std::string nettoyer(std::string s, bool grande) {
  static const char* const remplacements[][2] = {
      {"🎧", "♪"}, {"✉", "»"}, {"⏱", ""}, {"⏸", "‖"}, {"⚠", "!"}, {"✗", "×"}, {"₂", "2"}};
  for (auto& r : remplacements) {
    size_t p;
    while ((p = s.find(r[0])) != std::string::npos) s.replace(p, strlen(r[0]), r[1]);
  }
  if (grande) {
    size_t p;
    while ((p = s.find("✓")) != std::string::npos) s.replace(p, strlen("✓"), "√");
    while ((p = s.find("★")) != std::string::npos) s.replace(p, strlen("★"), "◆");
  }
  return s;
}

// Coupe un texte trop long (en UTF-8) et ajoute « … ».
std::string couper(lgfx::LovyanGFX& g, std::string t, int largeur) {
  if (g.textWidth(t.c_str()) <= largeur) return t;
  while (!t.empty() && g.textWidth((t + "…").c_str()) > largeur) {
    size_t p = t.size() - 1;
    while (p > 0 && (static_cast<uint8_t>(t[p]) & 0xc0) == 0x80) p--;
    t.erase(p);
  }
  while (!t.empty() && t.back() == ' ') t.pop_back();
  return t + "…";
}

// « Le Bureau du directeur » -> « Bureau du directeur » (barre des zones).
std::string sansArticle(const std::string& nom) {
  for (const char* a : {"Le ", "La ", "Les ", "L'", "L’"})
    if (nom.rfind(a, 0) == 0 && nom.size() > strlen(a) + 2) return nom.substr(strlen(a));
  return nom;
}

uint32_t melange(uint32_t a, uint32_t b, float t) {
  auto canal = [&](int d) {
    int x = (a >> d) & 0xff, y = (b >> d) & 0xff;
    return static_cast<uint32_t>(x + (y - x) * t) << d;
  };
  return canal(16) | canal(8) | canal(0);
}

bool memeCorps(const Vue& a, const Vue& b) {
  if (a.ecran != b.ecran || a.titre != b.titre || a.texte != b.texte || a.auteur != b.auteur || a.bandeau != b.bandeau ||
      a.barre != b.barre || a.champs != b.champs || a.curseur != b.curseur || a.modules != b.modules || a.valeurs != b.valeurs ||
      a.theme.fond != b.theme.fond)
    return false;
  for (int i = 0; i < 4; i++)
    if (a.options[i] != b.options[i]) return false;
  if (a.ecran == Vue::ACCUSATION)
    for (int i = 0; i < 3; i++)
      if (a.zones[i] != b.zones[i]) return false;
  return true;
}

}  // namespace

uint16_t Dessin::c(uint32_t rgb) const { return lgfx::color565((rgb >> 16) & 0xff, (rgb >> 8) & 0xff, rgb & 0xff); }

void Dessin::police(bool grande) {
  grande_ = grande;
  g_.setFont(grande ? &fonts::efontJA_24_b : &fonts::efontJA_16);
  g_.setTextSize(1);
}

void Dessin::message(const char* titre, const char* texte) {
  g_.fillScreen(c(0x101418));
  police(true);
  g_.setTextColor(c(0xf0d070));
  g_.drawString(titre, 16, 110);
  police(false);
  paragraphe(texte, 16, 150, L - 32, H - 10, c(0xe0e0e0));
}

// Texte à la ligne automatiquement (mots entiers), en UTF-8. Renvoie le y suivant.
int Dessin::paragraphe(const std::string& brut, int x, int y, int largeur, int yMax, uint16_t couleur, bool centre) {
  std::string t = nettoyer(brut, grande_);
  int hauteur = g_.fontHeight() + (grande_ ? 4 : 5);
  g_.setTextColor(couleur);
  size_t i = 0;
  while (i <= t.size() && y + hauteur <= yMax) {
    size_t finLigne = t.find('\n', i);
    if (finLigne == std::string::npos) finLigne = t.size();
    std::string ligne;
    size_t j = i;
    while (j < finLigne) {
      size_t espace = t.find(' ', j);
      if (espace == std::string::npos || espace > finLigne) espace = finLigne;
      std::string essai = ligne.empty() ? t.substr(j, espace - j) : ligne + " " + t.substr(j, espace - j);
      if (!ligne.empty() && g_.textWidth(essai.c_str()) > largeur) break;
      ligne = essai;
      j = espace + 1;
    }
    if (j > finLigne) j = finLigne;
    int px = centre ? x + (largeur - g_.textWidth(ligne.c_str())) / 2 : x;
    g_.drawString(ligne.c_str(), px, y);
    y += hauteur;
    if (j >= finLigne) {
      if (finLigne >= t.size()) break;
      i = finLigne + 1;
    } else {
      i = j;
    }
  }
  return y;
}

void Dessin::afficher(const Vue& v, uint32_t t) {
  bool clignoter = v.aideClignote && t - dernierClignotement_ > 600;
  if (clignoter) {
    clignote_ = !clignote_;
    dernierClignotement_ = t;
  }
  if (!premiere_ && v.version == derniere_.version && v.versionEntete == derniere_.versionEntete && !clignoter &&
      v.aideClignote == derniere_.aideClignote)
    return;

  g_.startWrite();
  bool tout = premiere_ || v.theme.fond != derniere_.theme.fond;
  if (tout || v.entete != derniere_.entete || v.compteurs != derniere_.compteurs || v.monde != derniere_.monde || clignoter ||
      v.aideClignote != derniere_.aideClignote)
    entete(v);
  if (tout || !memeCorps(v, derniere_)) corps(v);
  if (tout || v.info != derniere_.info || v.progression != derniere_.progression || v.ecran != derniere_.ecran) ligneInfo(v);
  bool zones = false;
  for (int i = 0; i < 3; i++) zones |= v.zones[i] != derniere_.zones[i];
  if (tout || zones) barreZones(v);
  g_.endWrite();
  derniere_ = v;
  premiere_ = false;
}

void Dessin::entete(const Vue& v) {
  uint32_t fondEntete = melange(v.theme.fond, 0x000000, 0.45f);
  g_.fillRect(0, 0, L, Y_CORPS - 2, c(fondEntete));
  g_.drawFastHLine(0, Y_CORPS - 2, L, c(v.theme.accent));
  police(false);
  g_.setTextColor(c(v.theme.doux));
  std::string gauche = v.compteurs.empty() ? v.monde : v.compteurs;
  g_.drawString(nettoyer(gauche, false).c_str(), 8, 7);
  if (v.aideClignote && clignote_) {
    g_.fillRoundRect(L - 200, 4, 64, 22, 6, c(v.theme.alerte));
    g_.setTextColor(c(0xffffff));
    g_.drawString("AIDE ?", L - 192, 7);
  }
  police(true);
  g_.setTextColor(c(v.theme.accent));
  std::string e = nettoyer(v.entete, true);
  g_.drawString(e.c_str(), L - 8 - g_.textWidth(e.c_str()), 3);
}

void Dessin::corps(const Vue& v) {
  g_.fillRect(0, Y_CORPS, L, Y_INFO - Y_CORPS, c(v.theme.fond));
  switch (v.ecran) {
    case Vue::CODE: corpsCode(v); return;
    case Vue::CHOIX: corpsChoix(v); return;
    case Vue::ENERGIE: corpsEnergie(v); return;
    case Vue::ACCUSATION: corpsAccusation(v); return;
    default: break;
  }
  bool centre = v.ecran == Vue::ACCUEIL || v.ecran == Vue::RESULTAT || v.ecran == Vue::CASQUE || v.ecran == Vue::EPREUVE;
  int y = Y_CORPS + 8;
  police(true);
  if (!v.titre.empty()) y = paragraphe(v.titre, 14, y, L - 28, y + 60, c(v.theme.accent), centre) + 2;
  police(false);
  if (!v.auteur.empty()) {
    g_.setTextColor(c(v.theme.doux));
    g_.drawString(("— " + nettoyer(v.auteur, false)).c_str(), 14, y);
    y += 22;
  }
  int yMax = v.bandeau.empty() ? Y_INFO - 4 : Y_INFO - 38;
  if (v.ecran == Vue::EPREUVE || v.ecran == Vue::CASQUE || v.ecran == Vue::RESULTAT) {
    police(true);  // gros caractères : on le lit de loin
    y += 10;
  }
  paragraphe(v.texte, 14, y, L - 28, yMax, c(v.theme.texte), centre);
  if (!v.bandeau.empty()) {
    police(false);
    g_.fillRoundRect(10, Y_INFO - 34, L - 20, 30, 8, c(v.theme.alerte));
    paragraphe(v.bandeau, 14, Y_INFO - 28, L - 28, Y_INFO, c(0xffffff), true);
  }
}

void Dessin::corpsCode(const Vue& v) {
  police(true);
  paragraphe(v.titre, 14, Y_CORPS + 8, L - 28, Y_CORPS + 40, c(v.theme.accent), true);
  police(false);
  paragraphe(v.texte, 14, Y_CORPS + 42, L - 28, Y_CORPS + 90, c(v.theme.texte), true);
  // Les cases de saisie, centrées ; celle du curseur est encadrée.
  int n = static_cast<int>(v.champs.size());
  int largeurCase = n > 6 ? 50 : 64, ecart = 10;
  int total = 0;
  for (auto& ch : v.champs) total += largeurCase * std::max<int>(1, ch.size() > 2 ? 1 : ch.size()) + ecart;
  int x = (L - (total - ecart)) / 2, y = 140;
  police(true);
  g_.setTextSize(2);
  for (int i = 0; i < n; i++) {
    int w = largeurCase * std::max<int>(1, v.champs[i].size() > 2 ? 1 : v.champs[i].size());
    bool actif = i == v.curseur;
    g_.fillRoundRect(x, y, w, 76, 8, c(melange(v.theme.fond, 0x000000, 0.35f)));
    if (actif) {
      g_.drawRoundRect(x, y, w, 76, 8, c(v.theme.accent));
      g_.drawRoundRect(x + 1, y + 1, w - 2, 74, 7, c(v.theme.accent));
    }
    std::string s = v.champs[i];
    g_.setTextColor(c(actif ? v.theme.accent : v.theme.texte));
    g_.drawString(s.c_str(), x + (w - g_.textWidth(s.c_str())) / 2, y + 14);
    x += w + ecart;
  }
  g_.setTextSize(1);
}

void Dessin::corpsChoix(const Vue& v) {
  police(true);
  paragraphe(v.titre, 14, Y_CORPS + 6, L - 28, Y_CORPS + 38, c(v.theme.accent), true);
  police(false);
  paragraphe(v.texte, 14, Y_CORPS + 40, L - 28, Y_CORPS + 86, c(v.theme.texte), true);
  const int w = 226, h = 62;
  for (int i = 0; i < 4; i++) {
    int x = i % 2 == 0 ? 10 : L - 10 - w;
    int y = 128 + (i / 2) * (h + 8);
    bool barre = i == v.barre || v.options[i].empty();
    uint32_t fond = barre ? melange(v.theme.fond, 0x808080, 0.3f) : COULEURS_BOUTONS[i];
    g_.fillRoundRect(x, y, w, h, 10, c(fond));
    uint16_t texte = c(i == 2 && !barre ? 0x1a1a1a : 0xffffff);
    if (!v.options[i].empty()) paragraphe(v.options[i], x + 8, y + (v.options[i].size() > 26 ? 8 : 20), w - 16, y + h, texte, true);
    if (i == v.barre) {
      g_.drawLine(x + 6, y + 6, x + w - 6, y + h - 6, c(0x202020));
      g_.drawLine(x + 6, y + h - 6, x + w - 6, y + 6, c(0x202020));
    }
  }
}

void Dessin::corpsEnergie(const Vue& v) {
  police(true);
  paragraphe(v.titre, 14, Y_CORPS + 6, L - 28, Y_CORPS + 38, c(v.theme.accent), true);
  police(false);
  int y = Y_CORPS + 40;
  for (size_t i = 0; i < v.modules.size(); i++) {
    bool actif = static_cast<int>(i) == v.curseur;
    if (actif) g_.fillRoundRect(8, y - 2, L - 16, 26, 6, c(melange(v.theme.fond, v.theme.accent, 0.25f)));
    g_.setTextColor(c(actif ? v.theme.accent : v.theme.texte));
    g_.drawString(((actif ? "▶ " : "   ") + nettoyer(v.modules[i], false)).c_str(), 14, y + 3);
    int valeur = i < v.valeurs.size() ? v.valeurs[i] : 0;
    for (int k = 0; k < 5; k++)
      g_.fillRoundRect(220 + k * 36, y + 2, 30, 20, 4, c(k < valeur ? v.theme.accent : melange(v.theme.fond, 0x000000, 0.4f)));
    g_.drawString(std::to_string(valeur).c_str(), 410, y + 3);
    y += 28;
  }
  g_.setTextColor(c(v.theme.doux));
  std::string b = nettoyer(v.bandeau + "   ·   " + v.texte, false);
  g_.drawString(b.c_str(), 14, Y_INFO - 22);
}

void Dessin::corpsAccusation(const Vue& v) {
  police(true);
  paragraphe(v.titre, 14, Y_CORPS + 10, L - 28, Y_CORPS + 44, c(v.theme.alerte), true);
  int y = Y_CORPS + 56;
  for (int z = 0; z < 3; z++) {
    police(false);
    g_.setTextColor(c(v.theme.doux));
    g_.drawString(NOMS_ZONES[z], 30, y + 4);
    police(true);
    g_.setTextColor(c(v.theme.texte));
    g_.drawString(nettoyer(v.zones[z].empty() ? "—" : v.zones[z], true).c_str(), 110, y);
    y += 40;
  }
  police(false);
  paragraphe(v.texte, 14, Y_INFO - 30, L - 28, Y_INFO, c(v.theme.accent), true);
}

void Dessin::ligneInfo(const Vue& v) {
  g_.fillRect(0, Y_INFO, L, Y_ZONES - Y_INFO, c(v.theme.fond));
  police(false);
  std::string t = v.info.empty() && v.ecran == Vue::PRINCIPAL ? v.progression : v.info;
  g_.setTextColor(c(v.theme.doux));
  t = couper(g_, nettoyer(t, false), L - 20);  // une seule ligne
  g_.drawString(t.c_str(), 10, Y_INFO + 3);
}

void Dessin::barreZones(const Vue& v) {
  uint32_t fond = melange(v.theme.fond, 0x000000, 0.45f);
  g_.fillRect(0, Y_ZONES, L, H - Y_ZONES, c(fond));
  police(false);
  for (int z = 0; z < 3; z++) {
    int x = z * (L / 3);
    if (z > 0) g_.drawFastVLine(x, Y_ZONES + 4, H - Y_ZONES - 8, c(v.theme.doux));
    g_.setTextColor(c(v.theme.accent));
    g_.drawString(NOMS_ZONES[z], x + 6, Y_ZONES + 7);
    g_.setTextColor(c(v.theme.texte));
    int decalage = 6 + g_.textWidth(NOMS_ZONES[z]) + 8;
    std::string nom = couper(g_, nettoyer(sansArticle(v.zones[z]), false), L / 3 - decalage - 6);
    g_.drawString(nom.c_str(), x + decalage, Y_ZONES + 7);
  }
}
