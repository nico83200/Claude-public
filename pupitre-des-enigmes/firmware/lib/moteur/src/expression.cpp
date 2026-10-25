#include "expression.h"

#include <cctype>
#include <stdexcept>

namespace pupitre {

namespace {

// Analyse descendante récursive :
//   ou      := et ('||' et)*
//   et      := comparaison ('&&' comparaison)*
//   comparaison := somme (op somme)?        op ∈ == != < <= > >=
//   somme   := produit (('+'|'-') produit)*
//   produit := unaire ('*' unaire)*
//   unaire  := ('!'|'-') unaire | atome
//   atome   := nombre | nom | '(' ou ')'
class Analyseur {
 public:
  Analyseur(const std::string& texte, const Resolveur& valeur) : t_(texte), valeur_(valeur) {}

  int lancer() {
    int v = ou();
    espaces();
    if (i_ != t_.size()) throw std::runtime_error("caractère inattendu : " + t_.substr(i_));
    return v;
  }

 private:
  const std::string& t_;
  const Resolveur& valeur_;
  size_t i_ = 0;

  void espaces() {
    while (i_ < t_.size() && std::isspace(static_cast<unsigned char>(t_[i_]))) i_++;
  }

  bool lit(const char* motif) {
    espaces();
    size_t n = std::char_traits<char>::length(motif);
    if (t_.compare(i_, n, motif) == 0) {
      i_ += n;
      return true;
    }
    return false;
  }

  int ou() {
    int v = et();
    while (lit("||")) {
      int d = et();
      v = (v || d) ? 1 : 0;
    }
    return v;
  }

  int et() {
    int v = comparaison();
    while (lit("&&")) {
      int d = comparaison();
      v = (v && d) ? 1 : 0;
    }
    return v;
  }

  int comparaison() {
    int g = somme();
    if (lit("==")) return g == somme();
    if (lit("!=")) return g != somme();
    if (lit("<=")) return g <= somme();
    if (lit(">=")) return g >= somme();
    if (lit("<")) return g < somme();
    if (lit(">")) return g > somme();
    return g;
  }

  int somme() {
    int v = produit();
    for (;;) {
      if (lit("+")) v += produit();
      else if (lit("-")) v -= produit();
      else return v;
    }
  }

  int produit() {
    int v = unaire();
    while (lit("*")) v *= unaire();
    return v;
  }

  int unaire() {
    espaces();
    // « != » est un opérateur de comparaison, pas une négation.
    if (i_ < t_.size() && t_[i_] == '!' && !(i_ + 1 < t_.size() && t_[i_ + 1] == '=')) {
      i_++;
      return unaire() ? 0 : 1;
    }
    if (lit("-")) return -unaire();
    return atome();
  }

  int atome() {
    espaces();
    if (i_ >= t_.size()) throw std::runtime_error("expression incomplète");
    char c = t_[i_];
    if (c == '(') {
      i_++;
      int v = ou();
      if (!lit(")")) throw std::runtime_error("parenthèse fermante manquante");
      return v;
    }
    if (std::isdigit(static_cast<unsigned char>(c))) {
      int v = 0;
      while (i_ < t_.size() && std::isdigit(static_cast<unsigned char>(t_[i_]))) v = v * 10 + (t_[i_++] - '0');
      return v;
    }
    if (std::isalpha(static_cast<unsigned char>(c)) || c == '_') {
      size_t debut = i_;
      while (i_ < t_.size() && (std::isalnum(static_cast<unsigned char>(t_[i_])) || t_[i_] == '_' || t_[i_] == ':')) i_++;
      return valeur_(t_.substr(debut, i_ - debut));
    }
    throw std::runtime_error(std::string("caractère inattendu : ") + c);
  }
};

}  // namespace

int evaluer(const std::string& expression, const Resolveur& valeur, std::string* erreur) {
  if (expression.find_first_not_of(" \t") == std::string::npos) return 1;
  try {
    return Analyseur(expression, valeur).lancer();
  } catch (const std::exception& e) {
    if (erreur) *erreur = e.what();
    return 0;
  }
}

}  // namespace pupitre
