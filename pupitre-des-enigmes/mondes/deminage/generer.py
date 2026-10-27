#!/usr/bin/env python3
"""Fabrique la mission 6, « Désamorçage », à partir de ses règles.

    python3 mondes/deminage/generer.py

Le jeu : un démineur au pupitre décrit la bombe, des experts la désamorcent avec le manuel.
Le pupitre tire au sort une bombe parmi BOMBES (numéro de série, 5 modules sur 8, réglages).
Les règles du manuel sont écrites ici UNE fois, en Python : ce script en déduit les solutions
de chaque bombe, et produit d'un même jet, toujours d'accord entre eux :

    pupitre/mission-6.json        le script du pupitre (les 16 bombes)
    pupitre/monde.json            le monde
    audio/mission-6.json          les répliques ; audio/sons.json, audio/voix.json
    impression/manuel.html        le manuel de désamorçage des experts
    mission-6-deroule.md          le déroulé du maître du jeu (les solutions des 16 bombes)
    firmware/test/parties/mission-6-*.txt   des parties de test pour le simulateur

On change une règle ? On modifie la fonction et son texte ci-dessous, puis on relance.
"""
import json
import random
from pathlib import Path

ICI = Path(__file__).resolve().parent
JEU = ICI.parents[1]
NB_BOMBES = 12
GRAINE = 1962  # le tirage des 16 bombes est figé : le déroulé imprimé reste juste

# ----------------------------------------------------------------------------------------
# Les modules : carte, nom, et la règle du manuel (texte + calcul)
# ----------------------------------------------------------------------------------------
MODULES = [  # (identifiant, carte Lieu, nom affiché)
    ("fils", "L1", "Fils"),
    ("detonateur", "L2", "Détonateur"),
    ("clavier", "L3", "Clavier"),
    ("surchauffe", "L4", "Surchauffe"),
    ("viseur", "L5", "Viseur"),
    ("nuancier", "L6", "Nuancier"),
    ("radio", "L7", "Radio"),
    ("stabilisateur", "L8", "Stabilisateur"),
]
VOYELLES = set("AEIOUY")
BOUTONS = ["ROUGE", "BLEU", "JAUNE", "VERT"]  # l'ordre des options d'un « choix »


def impair(serie):
    return int(serie[-1]) % 2 == 1


def voyelle(serie):
    return any(c in VOYELLES for c in serie)


# Fils : cinq fils, un seul à couper. Les règles se lisent dans l'ordre.
COULEURS_FILS = ["rouge", "bleu", "jaune", "blanc", "vert"]
REGLES_FILS = [
    "S'il n'y a <b>aucun fil rouge</b> : coupez le <b>2<sup>e</sup></b> fil.",
    "Sinon, si le <b>dernier fil est blanc</b> et que le <b>dernier chiffre</b> du numéro de série est <b>impair</b> : coupez le <b>4<sup>e</sup></b> fil.",
    "Sinon, s'il y a <b>exactement un fil rouge</b> et <b>plus d'un fil jaune</b> : coupez le <b>1<sup>er</sup></b> fil.",
    "Sinon, s'il n'y a <b>aucun fil bleu</b> : coupez le <b>dernier</b> fil (le 5<sup>e</sup>).",
    "Sinon : coupez le <b>dernier fil bleu</b>.",
]


def fil_a_couper(fils, serie):
    if "rouge" not in fils:
        return 2
    if fils[-1] == "blanc" and impair(serie):
        return 4
    if fils.count("rouge") == 1 and fils.count("jaune") > 1:
        return 1
    if "bleu" not in fils:
        return 5
    return max(i for i, c in enumerate(fils, 1) if c == "bleu")


# Détonateur : l'étiquette, et la présence d'une voyelle dans le numéro de série.
DETONATEUR = {"ANNULER": ("BLEU", "JAUNE"), "EXPLOSER": ("VERT", "ROUGE"),
              "MAINTENIR": ("ROUGE", "BLEU"), "ARMER": ("JAUNE", "VERT")}


def bouton_detonateur(etiquette, serie):
    return DETONATEUR[etiquette][0 if voyelle(serie) else 1]


# Clavier : chaque mot vaut un chiffre ; à l'envers s'il y a une voyelle dans le numéro.
MOTS = {"ANCRE": 7, "BALLON": 3, "CACTUS": 9, "DRAGON": 1, "ÉCLAIR": 4, "FUSÉE": 8, "GUITARE": 2, "HIBOU": 6,
        "IGLOO": 0, "JOKER": 5, "KAYAK": 3, "LAMPE": 8, "MOULIN": 1, "NUAGE": 6, "OURS": 4, "PIRATE": 2}


def code_clavier(mots, serie):
    chiffres = [str(MOTS[m]) for m in mots]
    return "".join(reversed(chiffres) if voyelle(serie) else chiffres)


# Surchauffe : refroidir au-dessus du seuil (60 °C si le dernier chiffre est impair, sinon 80 °C).
def seuil(serie):
    return 60 if impair(serie) else 80


def refroidir(temperature, serie):
    return temperature > seuil(serie)


# Viseur : deux gestes par cible ; dans l'ordre inverse si le dernier chiffre est impair.
CIBLES = {"ALPHA": ("haut", "droite"), "BRAVO": ("bas", "gauche"), "CHARLIE": ("gauche", "haut"),
          "DELTA": ("droite", "bas"), "ÉCHO": ("haut", "bas"), "FOXTROT": ("gauche", "droite"),
          "GOLF": ("bas", "haut"), "HOTEL": ("droite", "gauche")}
GESTES = {"haut": "vers le haut", "bas": "vers le bas", "gauche": "vers la gauche", "droite": "vers la droite"}
OPTIONS_GESTES = ["haut", "droite", "gauche", "bas"]


def gestes_viseur(cible, serie):
    g = CIBLES[cible]
    return tuple(reversed(g)) if impair(serie) else g


# Nuancier : le niveau de contamination, et la présence d'une voyelle.
NUANCIER = {1: ("rouge", "bleu"), 2: ("vert", "jaune"), 3: ("bleu", "vert"), 4: ("jaune", "rouge")}


def pastille(niveau, serie):
    return NUANCIER[niveau][0 if voyelle(serie) else 1]


# Radio : un mot de code au casque, une fréquence à 3 chiffres ; +1 au dernier chiffre si impair.
RADIO = {"OPALE": "315", "SAPHIR": "472", "RUBIS": "208", "ÉMERAUDE": "561",
         "TOPAZE": "639", "AMBRE": "184", "JADE": "720", "ONYX": "947"}


def frequence(mot, serie):
    f = RADIO[mot]
    return f[:2] + str((int(f[2]) + 1) % 10) if impair(serie) else f


# Stabilisateur : pas de règle, de l'adresse (un rythme à reproduire).
MOTIFS = [[0, 300, 600], [0, 250, 750], [0, 500, 750, 1000], [0, 400, 600, 1000]]


# ----------------------------------------------------------------------------------------
# Les 16 bombes
# ----------------------------------------------------------------------------------------
def tirer_bombes():
    r = random.Random(GRAINE)
    bombes = []
    lettres = "ABCDEFGHIJKLMNOPQRSTUVWXZ"
    consonnes = [c for c in lettres if c not in VOYELLES]
    for k in range(NB_BOMBES):
        # Numéros de série équilibrés : impair ou pair, avec ou sans voyelle.
        avec_voyelle, est_impair = k % 2 == 0, (k // 2) % 2 == 0
        a = r.choice("AEIOU") if avec_voyelle else r.choice(consonnes)
        b = r.choice(consonnes)
        chiffres = [r.randint(0, 9) for _ in range(3)]
        dernier = r.choice([1, 3, 5, 7, 9] if est_impair else [0, 2, 4, 6, 8])
        serie = f"{a}{b}-{''.join(map(str, chiffres))}{dernier}"
        modules = sorted(r.sample(range(len(MODULES)), 5))
        b_ = {"n": k + 1, "serie": serie, "modules": [MODULES[i][0] for i in modules]}
        b_["fils"] = [r.choice(COULEURS_FILS) for _ in range(5)]
        b_["etiquette"] = r.choice(list(DETONATEUR))
        b_["mots"] = r.sample(list(MOTS), 4)
        t = seuil(serie)
        b_["temperature"] = r.choice([r.randint(t - 25, t - 3), r.randint(t + 3, min(t + 25, 99))])
        b_["cible"] = r.choice(list(CIBLES))
        b_["niveau"] = r.randint(1, 4)
        b_["mot_radio"] = r.choice(list(RADIO))
        b_["motif"] = r.choice(MOTIFS)
        bombes.append(b_)
    return bombes


def solutions(b):
    s = b["serie"]
    return {"fils": str(fil_a_couper(b["fils"], s)), "detonateur": bouton_detonateur(b["etiquette"], s),
            "clavier": code_clavier(b["mots"], s), "surchauffe": "VERT" if refroidir(b["temperature"], s) else "ROUGE",
            "viseur": gestes_viseur(b["cible"], s), "nuancier": pastille(b["niveau"], s),
            "radio": frequence(b["mot_radio"], s), "stabilisateur": b["motif"]}


# ----------------------------------------------------------------------------------------
# Les répliques (dossier 60) et les bruitages (dossier 61)
# ----------------------------------------------------------------------------------------
MODULE_INTRO = {"fils": 20, "detonateur": 21, "clavier": 22, "surchauffe": 23, "viseur": 25,
                "nuancier": 26, "radio": 27, "stabilisateur": 28}
RADIO_REPLIQUE = {mot: 40 + i for i, mot in enumerate(RADIO)}


def repliques():
    L = lambda n, v, u, t, **k: dict(n=n, voix=v, usage=u, texte=t, **k)
    lignes = [
        L(1, "qg", "Écran titre", "Brigade de déminage. Mission six : désamorçage."),
        L(9, "qg", "Pitch", "Ici le QG de la brigade de déminage. Une bombe vient d'être découverte, et vous êtes les seuls sur place. "
          "Le démineur reste au pupitre : il voit la bombe, mais n'a pas le droit de lire le manuel. Les experts ont le manuel, "
          "mais ne regardent pas l'écran. Le démineur décrit, les experts trouvent la règle, le démineur agit. "
          "Chaque erreur coûte une minute. À la troisième, tout saute. Bonne chance."),
        L(10, "bombe", "Départ du chrono", "Bombe armée. Compte à rebours : douze minutes."),
        L(11, "qg", "Carte sans effet", "Ce n'est pas un module, ça."),
        L(12, "bombe", "Module déjà désamorcé", "Module déjà désamorcé."),
        L(13, "qg", "Monde chargé", "Brigade de déminage. Installez la façade si vous en avez une, puis appuyez sur la molette."),
        L(14, "qg", "Comment jouer", "Démineur : le numéro de série et les modules de cette bombe sont à l'écran ; un appui sur la molette les "
          "réaffiche. Glissez la carte d'un module dans la fente pour l'ouvrir, et laissez-la en place."),
        L(15, "bombe", "Module absent", "Ce module n'est pas sur cette bombe."),
        L(20, "bombe", "Fils", "Module fils. Cinq fils, un seul à couper. Tapez son numéro au clavier."),
        L(21, "bombe", "Détonateur", "Module détonateur. Lisez l'étiquette, puis appuyez sur un seul bouton de couleur."),
        L(22, "bombe", "Clavier", "Module clavier. Quatre mots, un code à quatre chiffres."),
        L(23, "bombe", "Surchauffe", "Module surchauffe. Bouton vert pour refroidir, bouton rouge pour laisser chauffer."),
        L(24, "bombe", "Surchauffe : refroidir", "Refroidissement. Soufflez sur la grille FORGE, bouche grande ouverte, pendant deux secondes."),
        L(25, "bombe", "Viseur", "Module viseur. Deux gestes de la main au-dessus de l'œil, dans le bon ordre."),
        L(26, "bombe", "Nuancier", "Module nuancier. Posez la bonne pastille sur le capteur, puis appuyez sur le gros bouton."),
        L(27, "bombe", "Radio", "Module radio. Le démineur met le casque, et écoute le mot de code."),
        L(28, "bombe", "Stabilisateur", "Module stabilisateur. Écoutez le rythme, puis reproduisez-le deux fois sur le gros bouton."),
        L(30, "bombe", "Module désamorcé (1)", "Module désamorcé."),
        L(31, "bombe", "Module désamorcé (2)", "Module neutralisé."),
        L(32, "bombe", "Module désamorcé (3)", "Module hors service."),
        L(33, "bombe", "Première erreur", "Erreur. Compte à rebours accéléré : une minute de moins."),
        L(34, "bombe", "Deuxième erreur", "Deuxième erreur. Une de plus, et c'est fini."),
        L(35, "bombe", "Sécurité finale", "Tous les modules sont désamorcés. Sécurité finale : tournez la clé. Maintenant !"),
        L(37, "bombe", "Capteur de vibrations", "Capteur de vibrations activé. Ne touchez plus à rien pendant huit secondes."),
        L(38, "bombe", "Vibrations : tenu", "Capteur de vibrations désactivé."),
        L(39, "bombe", "Deux minutes", "Plus que deux minutes."),
        L(48, "qg", "Radio : personne au casque", "Personne au casque ? Glissez de nouveau la carte de la radio pour réécouter."),
        L(60, "qg", "Victoire", "Bombe désamorcée ! Ici le QG. Beau travail : la brigade est fière de vous."),
        L(61, "qg", "Rang : démineurs d'élite", "Démineurs d'élite. Plus de six minutes d'avance, et pas une seule erreur."),
        L(62, "qg", "Rang : démineurs confirmés", "Démineurs confirmés. La bombe est neutralisée, avec un peu d'avance."),
        L(63, "qg", "Rang : démineurs chanceux", "Démineurs chanceux. C'était juste. Très juste."),
        L(64, "bombe", "Temps écoulé", "Zéro."),
        L(65, "qg", "Explosion", "Ici le QG… Nous avons perdu le contact. Recommencez l'entraînement : une autre bombe vous attend."),
        L(67, "qg", "La solution", "Pour comprendre, relisez dans le manuel les modules ratés, avec le numéro de série de cette bombe."),
        L(70, "qg", "QG : appel", "QG, j'écoute."),
        L(71, "qg", "Conseil gratuit", "Un conseil."),
        L(72, "qg", "Conseil payant", "Un conseil, qui vous coûte une minute."),
        L(73, "qg", "Conseil 1", "Démineur, décrivez exactement ce que vous voyez, mot pour mot. Experts, commencez par le numéro de série."),
        L(74, "qg", "Conseil 2", "Le dernier chiffre du numéro de série, et la présence d'une voyelle, décident de beaucoup de règles. Notez-les tout de suite."),
        L(75, "qg", "Conseil 3", "Les règles des fils se lisent dans l'ordre : la première qui s'applique est la bonne."),
        L(76, "qg", "Conseil 4", "Un module vous bloque ? Passez au suivant, vous y reviendrez."),
        L(79, "qg", "Plus de conseil", "Le QG n'a plus rien à vous apprendre. À vous."),
    ]
    for mot, n in RADIO_REPLIQUE.items():
        lignes.append(L(n, "qg", f"Radio : {mot}", f"Ici le QG. Mot de code : {mot.capitalize()}. Je répète : {mot.capitalize()}.",
                        sortie="casque"))
    lignes.sort(key=lambda l: l["n"])
    return {"monde": "Brigade de déminage", "mission": "Désamorçage", "dossier": 60, "sortie_par_defaut": "haut-parleur",
            "plages": {"001-019": "Introduction", "020-039": "La bombe et ses modules", "040-048": "La radio (casque)",
                       "060-067": "Fins", "070-079": "Le QG (conseils)"},
            "lignes": lignes}


def sons():
    S = lambda n, nom, d, p: dict(n=n, nom=nom, duree=d, prompt=p)
    return {"dossier": 61, "description": "Bruitages du monde Brigade de déminage (dossier /61/ des cartes SD).", "sons": [
        S(1, "Jingle du monde", 4, "Tense military briefing sting, low brass hit and a ticking clock, bomb squad intro"),
        S(2, "Ambiance : la bombe", 20, "Tense bomb defusal ambience, loud steady mechanical clock ticking, low electronic hum, seamless loop"),
        S(3, "Module désamorcé", 1.5, "Electronic disarm sound, relay click followed by a descending double beep"),
        S(4, "Erreur", 1.5, "Harsh electronic error buzzer with a short electrical crackle"),
        S(5, "Tic du stabilisateur", 0.5, "Single short sharp electronic beep, metronome"),
        S(6, "Explosion", 4, "Big cinematic explosion, deep boom and debris, then ringing silence"),
        S(7, "Bombe désamorcée", 4, "Bomb timer powering down with a falling tone, then a relieved soft orchestral chord"),
        S(8, "Alarme", 2, "Urgent electronic alarm, fast rising beeps, bomb warning"),
    ]}


VOIX = {"modele_par_defaut": "eleven_multilingual_v2", "voix": {
    "qg": {"nom": "Le QG de la brigade (radio)", "voice_id": "",
           "description": "Homme, la quarantaine, voix calme et ferme, ton militaire, légèrement grésillante comme à la radio.",
           "portrait": "Le chef de la brigade de déminage, qui guide à distance. Calme, précis, il ne s'énerve jamais : il sait que "
                       "la panique tue. Phrases courtes, ton d'instructeur bienveillant.",
           "prompt_voice_design": "A calm, firm French male bomb squad commander in his forties, native speaker from France, "
                                  "military instructor tone, slightly crackly radio quality.",
           "reglages": {"stability": 0.6, "similarity_boost": 0.75, "style": 0.2}},
    "bombe": {"nom": "Le système de la bombe", "voice_id": "",
              "description": "Voix synthétique féminine, froide, détachée, légèrement métallique.",
              "portrait": "La voix de la bombe elle-même : un automate sans émotion qui annonce les modules, les erreurs et le compte "
                          "à rebours. Sa politesse glaciale fait monter la tension.",
              "prompt_voice_design": "A cold, detached synthetic French female voice, automated device announcement, slightly metallic, perfectly even.",
              "reglages": {"stability": 0.85, "similarity_boost": 0.75, "style": 0.0}}}}


# ----------------------------------------------------------------------------------------
# Le script du pupitre
# ----------------------------------------------------------------------------------------
def desamorce(mod):
    return [{"son": 3}, {"hasard": [[{"dire": 30}], [{"dire": 31}], [{"dire": 32}]]}, {"drapeau": f"d_{mod}"},
            {"ajouter": {"desamorces": 1}}, {"evenement": "bilan"}]


ERREUR = [{"evenement": "erreur"}]


def module(mod, b, sol):
    """Les actions qui ouvrent le module « mod » de la bombe b."""
    ok = [{"evenement": f"ok_{mod}"}]
    titre = f"MODULE · {dict((m, n) for m, _, n in MODULES)[mod].upper()}"
    if mod == "fils":
        allumer = [{"voyant": {"n": i, "couleur": c}} for i, c in enumerate(b["fils"], 1)]
        eteindre = [{"voyant": {"n": "tous"}}]
        return allumer + [{"epreuve": {
            "type": "code", "titre": titre, "texte": "Fils : " + " · ".join(b["fils"]) + "\nTapez le numéro du fil à couper (1 à 5)",
            "solution": sol, "intro": {"dire": 20}, "reussite": eteindre + ok, "echec": eteindre + ERREUR}}]
    if mod == "detonateur":
        return [{"epreuve": {"type": "choix", "titre": titre, "texte": f"Étiquette du détonateur : « {b['etiquette']} »",
                             "options": ["ROUGE", "BLEU", "JAUNE", "VERT"], "bonne": BOUTONS.index(sol), "intro": {"dire": 21},
                             "reussite": ok, "echec": ERREUR}}]
    if mod == "clavier":
        return [{"epreuve": {"type": "code", "titre": titre, "texte": "Les mots : " + " · ".join(b["mots"]) + "\nCode à 4 chiffres",
                             "solution": sol, "afficheur": True, "intro": {"dire": 22}, "reussite": ok, "echec": ERREUR}}]
    if mod == "surchauffe":
        souffler = {"epreuve": {"type": "capteur", "capteur": "souffle", "titre": titre, "texte": "Refroidissement en cours…",
                                "consigne": "Grille FORGE · à 2 ou 3 cm · bouche ouverte · 2 secondes",
                                "pendant": "Le noyau refroidit… continuez !", "tenir": 2, "secondes": 25,
                                "intro": {"dire": 24}, "reussite": ok, "echec": ERREUR}}
        vert = [souffler] if sol == "VERT" else ERREUR
        rouge = ok if sol == "ROUGE" else ERREUR
        return [{"epreuve": {"type": "choix", "titre": titre,
                             "texte": f"Température du noyau : {b['temperature']} °C\nVERT : refroidir · ROUGE : laisser chauffer",
                             "options": ["Laisser chauffer", "", "", "Refroidir"], "intro": {"dire": 23},
                             "actions": [rouge, [], [], vert]}}]
    if mod == "viseur":
        g1, g2 = sol
        def geste(i, g, suite):
            return {"epreuve": {"type": "choix", "titre": f"{titre} · GESTE {i} / 2", "texte": f"Cible : {b['cible']}",
                                "options": ["Vers le haut", "Vers la droite", "Vers la gauche", "Vers le bas"],
                                "gestes": OPTIONS_GESTES, "bonne": OPTIONS_GESTES.index(g),
                                **({"intro": {"dire": 25}} if i == 1 else {}), "reussite": suite, "echec": ERREUR}}
        return [geste(1, g1, [{"evenement": f"viseur_b{b['n']}"}])]
    if mod == "nuancier":
        return [{"epreuve": {"type": "appui", "titre": titre,
                             "texte": f"Niveau de contamination : {b['niveau']}\nPosez la bonne pastille sur le NUANCIER, puis appuyez sur le gros bouton",
                             "secondes": 60, "intro": {"dire": 26},
                             "reussite": [{"si": f"couleur_{sol}", "alors": ok, "sinon": ERREUR}], "echec": ERREUR}}]
    if mod == "radio":
        return [{"dire": 27}, {"dire": RADIO_REPLIQUE[b["mot_radio"]], "delai": 25, "rate": [{"dire": 48}]},
                {"epreuve": {"type": "code", "titre": titre, "texte": "La fréquence (3 chiffres)", "solution": sol,
                             "afficheur": True, "reussite": ok, "echec": ERREUR}}]
    if mod == "stabilisateur":
        return [{"epreuve": {"type": "rythme", "titre": titre, "texte": "Écoutez, puis reproduisez le rythme deux fois",
                             "motif": b["motif"], "repetitions": 2, "son": 5, "tolerance": 200, "intro": {"dire": 28},
                             "reussite": ok, "echec": ERREUR}}]
    raise ValueError(mod)


def script(bombes):
    noms = dict((m, n) for m, _, n in MODULES)
    regles = []
    for mod, carte, nom in MODULES:
        regles.append({"quand": {"lieu": carte}, "si": f"!m_{mod}", "alors": [{"dire": 15}]})
        regles.append({"quand": {"lieu": carte}, "si": f"d_{mod}", "alors": [{"dire": 12}]})
        for b in bombes:
            if mod in b["modules"]:
                regles.append({"quand": {"lieu": carte}, "si": f"b{b['n']}",
                               "alors": module(mod, b, solutions(b)[mod])})
        regles.append({"quand": {"evenement": f"ok_{mod}"}, "alors": desamorce(mod)})
    # Le second geste du viseur, bombe par bombe
    for b in bombes:
        if "viseur" in b["modules"]:
            g2 = solutions(b)["viseur"][1]
            regles.append({"quand": {"evenement": f"viseur_b{b['n']}"}, "alors": [{"epreuve": {
                "type": "choix", "titre": "MODULE · VISEUR · GESTE 2 / 2", "texte": f"Cible : {b['cible']}",
                "options": ["Vers le haut", "Vers la droite", "Vers la gauche", "Vers le bas"], "gestes": OPTIONS_GESTES,
                "bonne": OPTIONS_GESTES.index(g2), "reussite": [{"evenement": "ok_viseur"}], "echec": ERREUR}}]})
    regles.append({"note": "Une erreur : une minute de moins ; à la troisième, la bombe explose", "quand": {"evenement": "erreur"},
                   "alors": [{"son": 4}, {"effet": "echec", "duree": 1500}, {"ajouter": {"fautes": 1}},
                             {"si": "fautes>=3", "alors": [{"defaite": "erreurs"}],
                              "sinon": [{"temps": -1}, {"si": "fautes>=2", "alors": [{"dire": 34}], "sinon": [{"dire": 33}]}]}]})
    regles.append({"note": "Les 5 modules désamorcés : la clé, en 15 secondes", "quand": {"evenement": "bilan"}, "si": "desamorces>=5",
                   "alors": [{"son": 8}, {"epreuve": {"type": "cle", "titre": "SÉCURITÉ FINALE", "texte": "Tournez la clé !", "secondes": 15,
                                                      "intro": {"dire": 35}, "reussite": [{"victoire": True}],
                                                      "echec": [{"evenement": "erreur"}, {"si": "fautes<3", "alors": [{"evenement": "bilan"}]}]}}]})
    regles.append({"quand": {"evenement": "bilan"}, "alors": []})
    # Une pastille posée ou retirée hors du module nuancier : rien.
    regles.append({"quand": {"couleur": "*"}, "alors": []})
    regles.append({"quand": {"couleur": "-"}, "alors": []})

    tirage = []
    for b in bombes:
        liste = " · ".join(noms[m].upper() for m in b["modules"])
        tirage.append([{"drapeau": [f"b{b['n']}"] + [f"m_{m}" for m in b["modules"]]},
                       {"titre": "BOMBE ARMÉE", "texte": f"N° de série : {b['serie']}\nModules : {liste}"}])
    objectifs = [{"nom": f"N° de série : {b['serie']}", "visible": f"b{b['n']}", "fait": "0"} for b in bombes]
    objectifs += [{"nom": f"Module {nom.upper()}", "visible": f"m_{mod}", "fait": f"d_{mod}"} for mod, _, nom in MODULES]
    objectifs.append({"nom": "Sécurité finale : la clé", "visible": "desamorces>=5", "fait": "0"})
    cartes = {carte: {"type": "lieu", "nom": f"Module {nom}"} for _, carte, nom in MODULES}
    return {
        "titre": "Désamorçage", "dossier_voix": 60, "dossier_sons": 61,
        "chrono": {"mode": "compte", "duree": 12}, "jauge": "chrono",
        "zones": {"noms": ["—", "MODULE", "—"]},
        "materiel": ["fente", "clavier", "voyants", "couleur", "cle", "boutons", "molette", "gros_bouton", "casque", "anneau",
                     "gestes", "souffle", "afficheur"],
        "voix": {"qg": "QG déminage", "bombe": "La bombe"},
        "cartes": cartes, "roles": {},
        "intro": [{"dire": 9}],
        "debut": [{"son": 8}, {"dire": 10}, {"dire": 14}, {"hasard": tirage}, {"ambiance": 2}],
        "reprise": [{"ambiance": 2}],
        "objectifs": objectifs,
        "panneau": {"voyants": [{"nom": f"Module {i}", "etats": []} for i in range(1, 6)] +
                              [{"nom": f"Désamorcés : {i}", "etats": [{"si": f"desamorces>={i}", "couleur": "vert"},
                                                                     {"si": "1", "couleur": "rouge", "effet": "faible"}]} for i in range(1, 6)]},
        "socle": {"etats": [{"si": "fautes>=2", "couleur": "rouge", "effet": "rapide"},
                            {"si": "fautes>=1", "couleur": "orange", "effet": "clignote"},
                            {"si": "1", "couleur": "rouge", "effet": "pulse"}]},
        "regles": regles,
        "defaut": {"lieu": [{"dire": 11}], "quoi": [{"dire": 11}]},
        "imprevus": [
            {"minute": 5, "actions": [{"son": 8}, {"effet": "alerte", "duree": 3000}, {"dire": 37},
                                      {"epreuve": {"type": "immobile", "titre": "CAPTEUR DE VIBRATIONS", "texte": "Ne touchez plus à rien !",
                                                   "secondes": 8, "reussite": [{"dire": 38}], "echec": ERREUR}}]},
            {"restant": 2, "actions": [{"dire": 39}]}],
        "aide": {"carte": "A6", "titre": "LE QG", "gratuits": 2, "cout": [{"temps": -1}],
                 "appel": 70, "gratuit": 71, "payant": 72, "fin": 79, "blocages": [{"si": "1", "niveaux": [73, 74, 75, 76]}]},
        "fins": {"victoire": [{"son": 7}, {"dire": 60}],
                 "rangs": [{"si": "restant>=6 && fautes==0", "dire": 61, "titre": "Démineurs d'élite"},
                           {"si": "restant>=2", "dire": 62, "titre": "Démineurs confirmés"},
                           {"si": "1", "dire": 63, "titre": "Démineurs chanceux"}],
                 "temps": [{"dire": 64}, {"son": 6}, {"dire": 65}], "erreurs": [{"son": 6}, {"dire": 65}],
                 "solution": [{"dire": 67}]},
    }


# ----------------------------------------------------------------------------------------
# Le manuel des experts
# ----------------------------------------------------------------------------------------
PASTILLES = {"rouge": "#d62020", "bleu": "#1f4fd8", "vert": "#1f9a3a", "jaune": "#f2c400"}
FLECHES = {"haut": "↑", "bas": "↓", "gauche": "←", "droite": "→"}


def manuel():
    lignes_det = "".join(f"<tr><td>« {e} »</td><td>{a}</td><td>{s}</td></tr>" for e, (a, s) in DETONATEUR.items())
    mots = list(MOTS.items())
    lignes_mots = "".join(f"<tr><td>{m1}</td><td class='c'>{c1}</td><td>{m2}</td><td class='c'>{c2}</td></tr>"
                          for (m1, c1), (m2, c2) in zip(mots[::2], mots[1::2]))
    lignes_cibles = "".join(f"<tr><td>{c}</td><td class='c'>{FLECHES[a]} {GESTES[a]}</td><td class='c'>{FLECHES[b]} {GESTES[b]}</td></tr>"
                            for c, (a, b) in CIBLES.items())
    pastille = lambda c: f"<span class='pastille' style='background:{PASTILLES[c]}'></span> {c}"
    lignes_nuancier = "".join(f"<tr><td class='c'>{n}</td><td>{pastille(a)}</td><td>{pastille(b)}</td></tr>" for n, (a, b) in NUANCIER.items())
    lignes_radio = "".join(f"<tr><td>{m}</td><td class='c'>{f}</td></tr>" for m, f in RADIO.items())
    regles_fils = "".join(f"<li>{r}</li>" for r in REGLES_FILS)
    return f"""<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Mission 6 · Manuel de désamorçage</title>
<link rel="stylesheet" href="../../../outils/impression/commun.css">
<style>
  /* Produit par mondes/deminage/generer.py : modifier les règles là-bas, pas ici. */
  :root {{ --vert: #2b2f36; --or: #f2b134; }}
  @page {{ size: A4; margin: 13mm 14mm 15mm; }}
  body {{ font-size: 10pt; }}
  .bandeau {{ display: flex; justify-content: space-between; font-size: 8pt; letter-spacing: .14em; text-transform: uppercase;
             color: #7a5a12; border-bottom: 2px solid var(--or); padding-bottom: 3px; margin-bottom: 8px; }}
  .couverture {{ border: 3px solid #2b2f36; padding: 10mm; text-align: center; margin-bottom: 6mm;
                background: repeating-linear-gradient(135deg, #f2b134 0 9mm, #2b2f36 9mm 18mm); }}
  .couverture div {{ background: #fff; padding: 8mm 6mm; }}
  .couverture h1 {{ font-size: 32pt; margin: 0; color: #2b2f36; }}
  .couverture p {{ margin: 2mm 0 0; letter-spacing: .2em; text-transform: uppercase; font-size: 9pt; color: #7a5a12; }}
  .module {{ border: 1.5px solid #2b2f36; border-radius: 5px; padding: 0 0 5px; margin: 0 0 4.5mm; break-inside: avoid; }}
  .module td, .module th {{ padding: 2.5px 6px; }}
  .module h2 {{ margin: 0 0 6px; padding: 5px 10px; background: #2b2f36; color: #f2b134; border: 0; border-radius: 3px 3px 0 0;
               font-size: 14pt; display: flex; justify-content: space-between; }}
  .module h2 small {{ color: #fff; font-family: "Source Sans"; font-size: 9pt; letter-spacing: .1em; font-weight: 400; }}
  .module > p, .module > ol, .module > table, .module > div {{ margin-left: 10px; margin-right: 10px; }}
  .module table {{ width: calc(100% - 20px); font-size: 9.6pt; }}
  .module th {{ background: #4a505a; }}
  td.c {{ text-align: center; font-weight: 700; }}
  ol li {{ margin-bottom: 3px; }}
  .pastille {{ display: inline-block; width: 10px; height: 10px; border-radius: 50%; vertical-align: -1px; border: 1px solid #0003; }}
  .attention {{ background: #fff4d6; border-left: 4px solid #f2b134; padding: 5px 9px; }}
  .deux {{ display: grid; grid-template-columns: 1fr 1fr; gap: 6mm; }}
  .saut {{ break-before: page; }}
</style>
</head>
<body>
<div class="bandeau"><span>Brigade de déminage · document réservé aux experts</span><span>Mission 6 · Désamorçage</span></div>
<div class="couverture"><div><h1>Manuel de désamorçage</h1><p>Modèle de bombe « Pupitre » · édition des experts</p></div></div>

<div class="attention"><strong>Experts : ne regardez jamais l'écran du pupitre.</strong> Le démineur vous décrit ce qu'il voit ;
vous trouvez la règle dans ce manuel, et vous lui dites quoi faire. Le démineur, lui, ne lit pas ce manuel.</div>

<h2>Avant tout : le numéro de série</h2>
<p>Il s'affiche quand la bombe s'arme, et à tout moment par un appui du démineur sur la molette. Exemple : <b>KZ-4815</b>.
Notez tout de suite ces deux informations, presque toutes les règles en dépendent :</p>
<table style="width:auto">
  <tr><th>Information</th><th>Comment la lire</th><th>Exemple KZ-4815</th></tr>
  <tr><td><b>Dernier chiffre impair</b></td><td>le tout dernier chiffre est 1, 3, 5, 7 ou 9</td><td>5 : <b>oui</b></td></tr>
  <tr><td><b>Voyelle</b></td><td>une des deux lettres est A, E, I, O, U ou Y</td><td>K, Z : <b>non</b></td></tr>
</table>
<p>La bombe compte <b>5 modules</b>, sur les 8 de ce manuel. Le démineur ouvre un module en glissant sa carte dans la fente.
Chaque <b>erreur</b> retire une minute ; à la <b>troisième</b>, la bombe explose. Un module raté se réessaie en glissant de nouveau sa carte.
Quand les 5 modules sont désamorcés, il reste la <b>sécurité finale</b> : tourner la clé dans les 15 secondes.</p>

<div class="module"><h2>Fils <small>CARTE « MODULE FILS »</small></h2>
<p>Cinq fils, numérotés de 1 à 5, s'allument sur les voyants du haut (et s'écrivent à l'écran). Le démineur tape au clavier le numéro du fil à couper.
Lisez les règles <b>dans l'ordre</b> : la première qui s'applique est la bonne.</p>
<ol>{regles_fils}</ol></div>

<div class="module"><h2>Détonateur <small>CARTE « MODULE DÉTONATEUR »</small></h2>
<p>Le démineur lit l'étiquette du détonateur, puis appuie sur <b>un seul</b> bouton de couleur.</p>
<table><tr><th>Étiquette</th><th>Le n° de série contient une voyelle</th><th>Pas de voyelle</th></tr>{lignes_det}</table></div>

<div class="module"><h2>Clavier <small>CARTE « MODULE CLAVIER »</small></h2>
<p>Quatre mots s'affichent. Chaque mot vaut un chiffre : le code s'écrit dans l'ordre des mots à l'écran,
<b>à l'envers si le numéro de série contient une voyelle</b>. Le démineur le tape au clavier (l'horloge l'affiche).</p>
<table><tr><th>Mot</th><th>Chiffre</th><th>Mot</th><th>Chiffre</th></tr>{lignes_mots}</table></div>

<div class="module"><h2>Surchauffe <small>CARTE « MODULE SURCHAUFFE »</small></h2>
<p>Le démineur lit la température du noyau. Le <b>seuil</b> est de <b>60 °C</b> si le dernier chiffre du numéro de série est impair,
de <b>80 °C</b> sinon.</p>
<ol><li>Température <b>au-dessus du seuil</b> : bouton <b>VERT</b> (refroidir), puis le démineur <b>souffle</b> sur la grille FORGE,
bouche grande ouverte, pendant 2 secondes.</li>
<li>Sinon : bouton <b>ROUGE</b> (laisser chauffer). Surtout ne pas souffler.</li></ol></div>

<div class="saut"></div>
<div class="bandeau"><span>Brigade de déminage · document réservé aux experts</span><span>Mission 6 · Désamorçage</span></div>
<div class="module"><h2>Viseur <small>CARTE « MODULE VISEUR »</small></h2>
<p>Une cible s'affiche. Le démineur passe la main à plat, à 5 cm au-dessus de l'<b>ŒIL</b>, d'un bord à l'autre, deux fois.
Si le dernier chiffre du numéro de série est <b>impair</b>, faites les deux gestes <b>dans l'ordre inverse</b>.</p>
<table><tr><th>Cible</th><th>1<sup>er</sup> geste</th><th>2<sup>e</sup> geste</th></tr>{lignes_cibles}</table></div>

<div class="deux">
<div class="module"><h2>Nuancier <small>« NUANCIER »</small></h2>
<p>Le démineur lit le niveau de contamination, pose la bonne <b>pastille</b> à plat sur le capteur NUANCIER, puis appuie sur le <b>gros bouton</b>.</p>
<table><tr><th>Niveau</th><th>Voyelle</th><th>Pas de voyelle</th></tr>{lignes_nuancier}</table></div>

<div class="module"><h2>Radio <small>« RADIO »</small></h2>
<p>Le démineur met le casque et entend un mot de code. Donnez-lui la fréquence à taper. Si le dernier chiffre du numéro de série
est <b>impair</b>, ajoutez 1 au <b>dernier</b> chiffre de la fréquence (9 devient 0).</p>
<table><tr><th>Mot de code</th><th>Fréquence</th></tr>{lignes_radio}</table></div>
</div>

<div class="module"><h2>Stabilisateur <small>CARTE « MODULE STABILISATEUR »</small></h2>
<p>Pas de règle ici : de l'adresse. Le pupitre fait entendre un rythme ; le démineur le reproduit <b>deux fois</b> sur le gros bouton.
Conseil : comptez à voix haute pendant l'écoute.</p></div>

<div class="deux">
<div class="module"><h2>Sécurité finale <small>LA CLÉ</small></h2>
<p>Quand les 5 modules sont désamorcés, le démineur a <b>15 secondes</b> pour tourner la clé. Si elle est déjà tournée, il la ramène d'abord au repos.</p></div>

<div class="module"><h2>Imprévu : le capteur de vibrations</h2>
<p>Si la bombe l'annonce, <b>plus personne ne touche le pupitre</b> pendant 8 secondes. Une pression, un appui, une carte : c'est une erreur.</p></div>
</div>
</body>
</html>
"""


# ----------------------------------------------------------------------------------------
# Le déroulé du maître du jeu, et les parties de test
# ----------------------------------------------------------------------------------------
def deroule(bombes):
    noms = dict((m, n) for m, _, n in MODULES)
    t = ["# Mission 6 : Désamorçage, le déroulé", "",
         "> **Le Pupitre des Énigmes** · Monde : Brigade de déminage. Un jeu coopératif de 2 à 8 joueurs, 12 minutes par bombe,",
         "> rejouable : le pupitre tire au sort une bombe parmi 16. Document produit par `mondes/deminage/generer.py`.",
         ">", "> **Repères sonores** : 🔊 20 désigne une réplique du dossier SD `60` ; `61/…` les bruitages.", "", "---", "",
         "## 1. Le principe", "",
         "| Situation | Effet |", "|---|---|",
         "| **Les rôles** | Un **démineur** au pupitre : il voit la bombe, ne lit pas le manuel. Les **experts** (1 à 7) ont le manuel et ne regardent pas l'écran. On change de démineur à chaque bombe. |",
         "| **La bombe** | Tirée au sort parmi 16 : un numéro de série, **5 modules** sur 8, des réglages. Le numéro et les modules s'affichent à l'armement, et dans le tableau de bord (appui sur la molette). |",
         "| **Un module** | Le démineur glisse sa carte dans la fente. Module absent : 🔊 15 ; déjà désamorcé : 🔊 12. |",
         "| **Une erreur** | `61/4`, une minute de moins (🔊 33, 🔊 34) ; la lumière sous la boîte passe à l'orange, puis au rouge rapide. **À la 3ᵉ, la bombe explose.** |",
         "| **Les voyants** | 1 à 5 : les fils du module Fils ; 6 à 10 : les modules désamorcés (vert). |",
         "| **L'horloge** | Le compte à rebours, et les codes pendant leur saisie. |",
         "| **La fin** | Les 5 modules désamorcés : 🔊 35, la clé en 15 s. Victoire : `61/7`, 🔊 60 et le rang. Temps écoulé ou 3 erreurs : `61/6`, 🔊 65. |",
         "| **L'imprévu** | Minute 5 : capteur de vibrations, 8 s sans toucher (🔊 37). À 2 minutes de la fin : 🔊 39. |",
         "| **Le QG** (carte A6) | 4 conseils généraux ; 2 gratuits, puis 1 minute chacun. |",
         "", "## 2. Les 16 bombes et leurs solutions", "",
         "| Bombe | N° de série | Impair | Voyelle | Modules et solutions |", "|---|---|---|---|---|"]
    for b in bombes:
        s, sol = b["serie"], solutions(b)
        parts = []
        for m in b["modules"]:
            if m == "fils":
                parts.append(f"**Fils** ({', '.join(b['fils'])}) → fil {sol[m]}")
            elif m == "detonateur":
                parts.append(f"**Détonateur** « {b['etiquette']} » → {sol[m]}")
            elif m == "clavier":
                parts.append(f"**Clavier** ({', '.join(b['mots'])}) → {sol[m]}")
            elif m == "surchauffe":
                parts.append(f"**Surchauffe** {b['temperature']} °C → {'VERT puis souffler' if sol[m] == 'VERT' else 'ROUGE'}")
            elif m == "viseur":
                parts.append(f"**Viseur** {b['cible']} → {GESTES[sol[m][0]]}, {GESTES[sol[m][1]]}")
            elif m == "nuancier":
                parts.append(f"**Nuancier** niveau {b['niveau']} → pastille {sol[m]}")
            elif m == "radio":
                parts.append(f"**Radio** {b['mot_radio']} → {sol[m]}")
            elif m == "stabilisateur":
                parts.append(f"**Stabilisateur** (rythme {', '.join(map(str, b['motif']))} ms)")
        t.append(f"| {b['n']} | {s} | {'oui' if impair(s) else 'non'} | {'oui' if voyelle(s) else 'non'} | " + " · ".join(parts) + " |")
    return "\n".join(t) + "\n"


def partie(b, gagner=True):
    sol = solutions(b)
    t = [f"# Mission 6 · Désamorçage — bombe {b['n']} ({b['serie']}), produite par mondes/deminage/generer.py",
         "fente M6", "fente -", "verifie phase menu", f"hasard {b['n'] - 1}", "touches 1", "calme", "verifie phase jeu",
         f"verifie drapeau b{b['n']}"]
    carte = dict((m, c) for m, c, _ in MODULES)
    if not gagner:
        # Trois erreurs : la bombe explose
        m = b["modules"][0]
        t += [f"# --- Trois erreurs sur le module {m}", "fente " + carte[m]]
        for _ in range(3):
            t += mauvaise(m, b, sol)
            t += ["fente -", "fente " + carte[m]]
        t += ["verifie phase defaite"]
        return "\n".join(t) + "\n"
    # Un module absent, puis chaque module
    absent = next(m for m, _, _ in MODULES if m not in b["modules"])
    t += [f"# --- Un module absent de cette bombe", f"fente {carte[absent]}", "verifie epreuve", "fente -"]
    for m in b["modules"]:
        t += [f"# --- {m}", f"fente {carte[m]}"] + bonne(m, b, sol) + [f"verifie drapeau d_{m}", "fente -"]
    t += ["# --- La sécurité finale", "verifie epreuve cle", "cle tournee", "verifie phase victoire"]
    return "\n".join(t) + "\n"


def bonne(m, b, sol):
    s = sol[m]
    if m == "surchauffe":
        return ["bouton VERT", "souffle 3"] if s == "VERT" else ["bouton ROUGE"]
    if m == "viseur":
        return [f"geste {s[0]}", f"geste {s[1]}"]
    if m == "nuancier":
        return [f"couleur {s}", "bouton GROS", "couleur -"]
    if m == "stabilisateur":
        return [f"rythme {','.join(map(str, s))} 2"]
    return [f"bouton {s}"] if m == "detonateur" else [f"touches {s}"]


def mauvaise(m, b, sol):
    s = sol[m]
    autre = lambda options: next(o for o in options if o != s)
    if m == "fils":
        return [f"touches {autre('12345')}"]
    if m == "detonateur":
        return [f"bouton {autre(BOUTONS)}"]
    if m == "surchauffe":
        return ["bouton ROUGE"] if s == "VERT" else ["bouton VERT"]
    if m == "viseur":
        return [f"geste {autre(OPTIONS_GESTES)}"]
    if m == "nuancier":
        return [f"couleur {autre(list(PASTILLES))}", "bouton GROS", "couleur -"]
    if m == "stabilisateur":
        return ["attendre 30"]
    return ["touches " + ("0" * len(s) if s != "0" * len(s) else "1" * len(s))]


def ecrire(chemin, contenu):
    chemin.parent.mkdir(parents=True, exist_ok=True)
    chemin.write_text(contenu if isinstance(contenu, str) else json.dumps(contenu, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"✓ {chemin.relative_to(JEU)}")


def main():
    bombes = tirer_bombes()
    ecrire(ICI / "pupitre" / "mission-6.json", script(bombes))
    ecrire(ICI / "pupitre" / "monde.json", {
        "id": "deminage", "nom": "Brigade de déminage", "carte": "M6", "aide": "A6", "aide_nom": "Le QG",
        "theme": {"fond": "#16181c", "texte": "#ece9e2", "accent": "#f2b134", "alerte": "#e0402a", "doux": "#8b8f96"},
        "accueil": {"dossier": 60, "dire": 13, "jingle_dossier": 61, "jingle": 1},
        "missions": [{"numero": 6, "titre": "Désamorçage", "script": "mission-6.json", "repliques": "repliques-6.json"}]})
    ecrire(ICI / "audio" / "mission-6.json", repliques())
    ecrire(ICI / "audio" / "sons.json", sons())
    ecrire(ICI / "audio" / "voix.json", VOIX)
    ecrire(ICI / "impression" / "manuel.html", manuel())
    ecrire(ICI / "mission-6-deroule.md", deroule(bombes))
    tests = JEU / "firmware" / "test" / "parties"
    for b in (bombes[0], bombes[4], bombes[7], bombes[11]):
        ecrire(tests / f"mission-6-bombe-{b['n']:02d}.txt", partie(b))
    ecrire(tests / "mission-6-explosion.txt", partie(bombes[3], gagner=False))


if __name__ == "__main__":
    main()
