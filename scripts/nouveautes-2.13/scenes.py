# Génère scenes.json (texte de la voix off et minutage estimé à 2,7 mots/s, recalé ensuite sur la voix enregistrée).
import json, re
S = [
 ("intro", "", "Version 2.13", ["01-accueil"], "Jack&Cie s'enrichit encore ! Voici les nouveautés de la version 2.13."),
 ("accueil", "Bien démarrer", "Accueil", ["01-accueil", "02-demarrage", "03-urgence-proposee"], "Sur l'accueil, un démarrage guidé vous accompagne : ajouter votre cheval, noter un premier soin, programmer une séance, activer les notifications et compléter votre profil. Chaque étape se coche toute seule."),
 ("assistant", "Posez-lui vos questions", "Assistant", ["04-assistant-question"], "L'assistant répond maintenant à vos questions sur le dossier : combien m'a coûté le maréchal cette année, à quand le prochain vermifuge, combien de séances ce mois-ci. Et il peut lire sa réponse à voix haute, pratique quand on a les mains prises à l'écurie."),
 ("ordonnance", "L'ordonnance en photo", "Santé", ["05-ordonnance-carte", "06-ordonnance-verif", "07-ordonnance-traitements"], "Une ordonnance du vétérinaire ? Prenez-la en photo : l'assistant prépare le soin, les traitements et le prochain contrôle. Vous vérifiez, puis tout rejoint le carnet de santé et l'agenda. La posologie est recopiée telle qu'elle est écrite."),
 ("veto", "Un résumé pour le vétérinaire", "Vétérinaire", ["08-veto-apercu", "09-veto-partage", "11-veto-public"], "Avant une visite, partagez avec votre vétérinaire un résumé des six derniers mois : soins, traitements, poids et observations, avec une synthèse rédigée par l'assistant. Le lien expire tout seul, et vous pouvez le désactiver à tout moment."),
 ("plan", "Un plan de séance en un geste", "Séances", ["12-plan-carte", "13-plan-propose"], "Pour préparer une séance, l'assistant propose un plan adapté à l'objectif, à la durée, à votre galop et à vos derniers bilans. Gardez les exercices qui vous plaisent : ils s'ajoutent à la séance."),
 ("exercices", "Des exercices pour vous", "Exercices", ["14-choix-exercices", "15-exercices-pour-vous"], "Les exercices se choisissent étape par étape, avec des suggestions selon votre niveau et vos habitudes. Retrouvez-les aussi sur la page Exercices."),
 ("bilan", "Le bilan en quelques touches", "Bilan de séance", ["16-bilan", "17-bilan-questions"], "Après la séance, le bilan se remplit en quelques touches, avec des questions adaptées aux exercices prévus. Et quand un autre cavalier monte votre cheval, vous recevez son bilan."),
 ("agenda", "Tout est dans l'agenda", "Agenda", ["18-agenda", "19-agenda-synchro"], "L'agenda rassemble tout : rendez-vous, soins, traitements, séances et pesées. Vous pouvez même l'afficher dans Google Agenda ou dans le calendrier de votre téléphone."),
 ("urgence", "La fiche d'urgence", "Sécurité", ["20-urgence-reglage", "22-urgence-publique", "23-urgence-imprimee"], "Préparez la fiche d'urgence de votre cheval : un QR code à coller sur le box. En cas de problème, n'importe qui voit qui appeler, le vétérinaire, les traitements en cours et les précautions, sans avoir de compte."),
 ("semaine", "Votre semaine d'un coup d'œil", "Chaque dimanche", ["24-ma-semaine"], "Chaque dimanche soir, recevez le récapitulatif de votre semaine : rendez-vous, traitements, paiements à prévoir et bilans à faire."),
 ("budget", "Un budget plus clair", "Budget", ["25-budget", "26-budget-graphique", "27-depense-detail"], "Dans le budget, touchez un graphique pour l'agrandir, ou une dépense pour voir son détail. Un soin et sa dépense restent toujours synchronisés."),
 ("horsligne", "Même sans réseau", "Hors connexion", ["29-hors-ligne-consigne", "30-hors-ligne-page"], "Pas de réseau à l'écurie ? Notez votre consigne quand même : elle sera enregistrée dès le retour de la connexion. Et vos fiches d'urgence restent consultables."),
 ("fin", "", "", [], "Toutes ces nouveautés sont déjà disponibles. Bonne route avec Jack&Cie !"),
]
t, out = 0.6, []
for sid, title, kicker, shots, text in S:
    words = len(re.findall(r"\w+", text))
    dur = round(words / 2.7 + 0.6, 2)
    out.append({"id": sid, "title": title, "kicker": kicker, "shots": shots, "text": text, "start": round(t, 2), "dur": dur})
    t += dur
json.dump(out, open(__file__.replace('scenes.py', 'scenes.json'), 'w'), ensure_ascii=False, indent=1)
print(round(t, 1), 's')
