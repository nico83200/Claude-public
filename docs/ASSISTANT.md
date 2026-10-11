# Assistant vocal (IA)

Un bouton micro (au centre de la barre du bas sur téléphone, « Parler » en haut sur ordinateur) permet de donner une consigne à l'oral ou au clavier :

- « Le maréchal est prévu le 2 décembre à 9 h » → rendez-vous au calendrier, année en cours ;
- « Réalise une séance de monte libre en dressage pour travailler la rectitude » → séance aujourd'hui, 50 à 60 min, exercices choisis dans la bibliothèque et répartis par phase ;
- « Le vétérinaire a vacciné Uni ce matin, 75 € » → soin réalisé, prochain rappel selon la fréquence du cheval, dépense ;
- « Uni passe à 2 kg de granulés le soir » → nouvelle version de la ration ;
- « Quand est le prochain maréchal d'Olympe ? » → réponse à l'écran.

## Fonctionnement

1. **Voix → texte** : le navigateur enregistre (arrêt automatique au silence, 60 s au plus) et envoie l'audio au serveur, qui le transmet à **ElevenLabs** (Speech-to-Text, modèle `scribe_v2`, français). L'audio n'est jamais écrit sur le disque ni conservé.
2. **Texte → actions** : le serveur appelle **Claude** (SDK officiel `anthropic-ai/sdk`, modèle réglable, Claude Opus 5.5 par défaut) avec des outils : créer/modifier un rendez-vous, créer/modifier une séance, enregistrer un soin, une observation, le suivi quotidien, une dépense, modifier la ration, rechercher des informations, poser une question.
3. **Exécution directe** : chaque outil applique les mêmes contrôles que les écrans (droits sur le cheval, offre en lecture seule, fonctionnalités incluses). L'assistant ne peut ni supprimer, ni partager, ni toucher à la facturation.
4. **Annulation** : bandeau « Annuler » pendant 10 s, puis depuis *Assistant › Historique* pendant 24 h, tant que l'élément n'a pas été modifié entre-temps.
5. **Précisions** : si une information indispensable manque (quel cheval ?), l'assistant pose la question avec des choix ; la réponse poursuit la même conversation (15 min).

Cheval par défaut : celui de la page ouverte, sinon le seul cheval accessible, sinon question.

## Réglages (Administration › Assistant IA)

- activation globale, modèle (Opus 5.5 / Sonnet 5.5 / Haiku 5.5), niveau de réflexion ;
- durée par défaut des séances (50–60 min) ;
- **indications libres pour l'IA** : texte ajouté aux consignes de l'assistant (vocabulaire, habitudes, préférences) ;
- clés Anthropic et ElevenLabs (chiffrées, jamais réaffichées ; repli sur `ANTHROPIC_API_KEY` / `ELEVENLABS_API_KEY`) ;
- tests de connexion, statistiques du mois, dernières erreurs.

Accès par offre : fonctionnalité « Assistant vocal (IA) » et quota mensuel de consignes par espace (Administration › Offres ; dérogation possible par licence). Offres de référence : Particulier 150, Multi-chevaux 400, Écurie 2 000 ; Découverte : non incluse.

## Données personnelles

- Envoyé à ElevenLabs : l'enregistrement audio de la consigne.
- Envoyé à Anthropic : le texte de la consigne, le nom de l'utilisateur et de l'espace, la liste des chevaux et intervenants accessibles, la bibliothèque d'exercices, les indications de l'administrateur et, pour les questions, les informations consultées.
- Conservé dans l'application : texte de la consigne, réponse et actions (historique, annulation), purgés après 90 jours (`data:purge`), inclus dans l'export RGPD, supprimés avec le compte.

À déclarer dans la liste des sous-traitants (Paramètres › Informations légales).

## Coûts

Chaque consigne = une transcription (quelques secondes d'audio) + 2 à 4 appels Claude. Les règles et la bibliothèque sont mises en cache côté Anthropic (prompt caching) pour réduire le coût des appels suivants. Le suivi des jetons consommés est affiché dans Administration › Assistant IA.
