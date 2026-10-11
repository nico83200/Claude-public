# État des fonctionnalités

Légende : ✅ terminé et couvert par des tests automatisés · ☑️ terminé, vérifié manuellement / partiellement testé ·
⚙️ terminé mais nécessite une configuration · ⚠️ partiel · ❌ non réalisé

## Version 2.6
- 🗑️ « Rechercher mon cheval » (recherche d'identité sur Internet : Wikidata, Brave) supprimé : boutons, pages, code et réglages retirés ; les informations déjà importées restent en base (`horse_external_sources`) sans affichage

## Version 2.5.1 — installation
- ✅ Le bouton « Installer » déclenche l'installation native (vraie application sur Android, WebAPK) : l'invite du navigateur est conservée dès le chargement de la page, et si elle n'est pas encore prête le bouton l'attend jusqu'à 4 s (« Préparation de l'installation… ») au lieu d'envoyer vers « Ajouter à l'écran d'accueil », qui peut ne créer qu'un raccourci
- ✅ Instructions de secours précisées : choisir « Installer » et non « Créer un raccourci » (Chrome), icône d'installation de Samsung Internet, option « Ouvrir comme app web » sur iPhone ; après installation, invitation à ouvrir l'application depuis l'écran d'accueil
- ☑️ Vérifié dans Chromium (Android émulé) : critères d'installation remplis, invite immédiate et invite tardive. À confirmer sur un téléphone réel

## Version 2.5 — budget récurrent et notifications obligatoires
- ✅ Dépenses récurrentes (pension, assurance…) : chaque semaine / mois / trimestre / an, date de fin facultative ; chaque échéance devient une dépense « à payer » (sans doublon, sans dérive des fins de mois, rattrapage si le planificateur n'a pas tourné) ; notification le jour de l'échéance ; suspendre / reprendre (sans arriéré) / modifier / arrêter (l'historique reste)
- ✅ Suivi des paiements : bloc « À payer » (en retard en rouge), bouton « Payé », remise « à payer » ; une dépense simple peut être saisie non réglée
- ✅ Téléphone / tablette (application installée) : écran plein écran bloquant tant que les notifications ne sont pas activées ; si refusées, marche à suivre iPhone / Android et vérification au retour dans l'application ; pas de blocage quand l'appareil ne peut pas recevoir de notifications (iOS < 16.4), ni sur la page « Mes données » ; « Se déconnecter » toujours disponible ; ordinateur non concerné
- ✅ Rappel après X heures sans ouvrir l'application (X et texte réglables dans Administration › Paramètres, 0 = désactivé) : une fois par absence, entre 9 h et 20 h, pas au-delà de 60 jours d'absence
- ⚙️ Tâches planifiées : `expenses:recurring` (6 h 05) et `reminders:activity` (toutes les 5 min)

## Version 2.4 — cours et rappels
- ✅ Assistant : un cours dicté (« cours collectif à 16 h avec Jimmy sur Jack Jack ») crée une séance de type cours avec son enseignant, sans exercices inventés ; elle apparaît dans le calendrier (« Cours collectif avec Jimmy – Jack Jack »)
- ✅ Après la fin prévue d'une séance ou d'un cours (+10 min), notification « Comment s'est passé votre cours ? » vers le bilan, envoyée une fois au cavalier (désactivable)
- ✅ Bilan : exercices cochés comme faits ou non, ajout des exercices travaillés (suggestions de la bibliothèque, minutes), puis ressenti et clôture
- ✅ Rappel quotidien facultatif à l'heure choisie, seulement si rien n'a été noté dans la journée
- ✅ Notifications sur le téléphone (Web Push) : activation dans Paramètres › Notifications, essai, désactivation ; clés VAPID générées et chiffrées en base ; seuls les services de notification des navigateurs sont acceptés comme destinataires
- ☑️ Vérifié dans Chromium : abonnement réel, envoi serveur accepté par le service de Google, affichage par le service worker et ouverture de la bonne page. La réception de bout en bout n'a pas pu être observée dans l'environnement de test (réseau sortant de réception bloqué) : **à valider sur un téléphone** (iPhone : application installée, iOS 16.4+)
- ⚙️ Nécessite le cron `schedule:run` (tâche `reminders:activity` toutes les 5 minutes) et HTTPS
- ❌ Rappel déclenché par la position (arrivée à l'écurie) : impossible dans une application web, voir la discussion du 11/10

## Version 2.3 — affichage revu
- ✅ En-tête commun à tous les onglets d'un cheval (photo, nom, actions de la page, menu « ⋯ » : modifier, PDF, partager, historique, archiver, transférer/supprimer), onglets en pastilles défilantes ; les onglets Séances et Dépenses gardent le contexte du cheval
- ✅ Correction du titre écrasé lettre par lettre sur téléphone (règle CSS globale limitée aux lignes flex sans retour à la ligne)
- ✅ Filtres repliés derrière un bouton « Filtres » (compteur, application immédiate, « Effacer ») : chevaux, séances, exercices, calendrier, budget
- ✅ Fiche cheval allégée : actions rapides (Séance, Soin, Signaler en fenêtre), « Ajouter une photo » en un geste, gestion du dossier repliée
- ✅ Séance : actions Démarrer / Préparer / Bilan, menu « ⋯ » (dupliquer, modèle, supprimer), commentaire en une ligne ; calendrier, budget et ration alimentaire réorganisés pour le téléphone
- ✅ Vérifié dans Chromium à 320, 360, 393 et 1280 px (aucun débordement, aucun titre écrasé)

## Version 2.1
- ✅ Mises à jour depuis l'administration : envoi par morceaux, vérification, sauvegardes code + base, remplacement sans toucher `.env`/`storage`, migrations dans une seconde requête, restauration automatique en cas d'échec, finalisation de secours par le planificateur — testé de bout en bout (installation 2.1.0 depuis le zip puis mise à jour 2.1.1 via l'interface)
- ✅ Téléphones et tablettes : connexion et inscription proposées uniquement dans l'application installée ; dans le navigateur, seule l'installation est proposée (navigateurs intégrés aux réseaux sociaux : invitation à ouvrir Safari/Chrome) ; lien de confirmation d'email utilisable sans connexion ; invitations acceptables depuis le tableau de bord
- ⚠️ À valider sur appareils réels : iPad en « mode ordinateur » (détecté par l'écran tactile), Samsung Internet

## Assistant vocal (2.0)
- ✅ Micro central, arrêt au silence, saisie clavier, transcription ElevenLabs, compréhension Claude (SDK officiel) avec outils : rendez-vous, séances avec exercices, soins, observations, suivi quotidien, dépenses, ration, questions, demande de précision
- ✅ Exécution directe, annulation 10 s + 24 h, historique, droits identiques aux écrans, quota mensuel par offre, indications libres et modèle réglables par le super-administrateur
- ⚙️ Testé avec Claude et ElevenLabs simulés (format des requêtes SDK vérifié contre un faux serveur API) — **nécessite les clés Anthropic et ElevenLabs** pour un essai réel ; à valider sur iPhone (Safari enregistre en audio/mp4)

## Interface (2.0)
- ✅ Nouvelle DA épurée claire : fond clair, vert Jack&Cie en accent, une seule police (Inter), cartes arrondies, barre du bas avec micro central, tableau de bord « Aujourd'hui / Échéances / Vos chevaux »

## Socle
- ✅ Installeur web du premier lancement (prérequis, base MySQL, application, super‑administrateur, verrouillage) — testé sur base vierge et depuis le zip de distribution
- ✅ Web app installable : manifeste complet (icônes, captures, raccourcis), invite native Android/ordinateur, instructions iPhone, page /application-mobile
- ✅ Inscription avec espace personnel, connexion, déconnexion, vérification d'email, réinitialisation du mot de passe, limitation des tentatives
- ✅ 2FA TOTP (☑️ parcours d'activation vérifié manuellement uniquement), obligatoire pour l'administration
- ✅ Organisations multiples, changement d'espace, rôles système et rôles personnalisés, invitations de membres
- ✅ Cloisonnement entre comptes / organisations, accès par URL refusé (tests d'isolation)
- ✅ Licences → limites (chevaux, membres, partages, stockage) et fonctionnalités par offre ; lecture seule si expiré/suspendu, exports conservés

## Chevaux
- ✅ Fiche d'identité complète, identifiants SIRE/UELN/transpondeur, archivage, transfert d'espace, suppression logique
- ✅ Propriétaires/détenteurs datés, lieux de vie, rattachement à une écurie avec droits choisis par le propriétaire
- ✅ Généalogie (2 générations) avec liens vers les fiches accessibles, représentation graphique
- ✅ Photos (compression, miniatures, diffusion contrôlée), documents privés

## Santé
- ✅ Rappels de suivi par cheval (fréquence, préavis) : maréchal (6 semaines) et vaccination (12 mois) **actifs par défaut**, vermifuge/dentiste/ostéopathe désactivables ; rappel N jours avant puis le jour même
- ✅ À l'enregistrement d'un soin ou à la validation d'une intervention du calendrier : fréquence du prochain passage demandée (pré-remplie), date calculée, fréquence mémorisée pour le cheval
- ✅ Soins par catégorie, professionnel, coût → dépense, document joint, prochain contrôle → calendrier, verrou de modification concurrente
- ✅ Traitements (posologie saisie, jamais calculée), administrations, fin → calendrier ; observations / signalements avec notification ciblée
- ✅ Vue d'ensemble : échéances, alertes, traitements ; export PDF de l'historique sanitaire
- ⚠️ Catégories de soins personnalisées : prévues en base (`care_categories.organization_id`), sans écran de gestion

## Intervenants
- ✅ Répertoire par espace, anti-doublon, association aux chevaux, historique des interventions

## Calendrier
- ✅ Vues jour/semaine/mois (liste sur mobile), types, statuts, récurrences calculées sans doublon, événements générés par soins/traitements
- ✅ Rappels : rendez-vous J-1, échéances de soins J-7, fins de traitement J-1, envoyés une fois, aux seules personnes autorisées
- ⚠️ Délais de rappel non paramétrables par l'utilisateur (fixés dans `reminders:send`)
- ❌ Synchronisation avec un calendrier externe (iCal) — prévue (`external_uid`)

## Séances et exercices
- ✅ Préparation par glisser-déposer (souris et tactile) : réordonner, changer de section, déposer un exercice de la bibliothèque dans une section ; flèches ▲▼ conservées pour le clavier
- ✅ Séances visibles dans le calendrier (prévues, terminées, annulées), mises à jour automatiquement ; visibles seulement avec le droit « Consulter les séances »
- ✅ Création, préparation (phases, ordre, durées, répétitions, consignes, pauses), modèles, mode séance mobile, bilan, historique filtrable, indicateurs de progression
- ✅ Copie figée des exercices (snapshot + version) : la bibliothèque ne modifie jamais les séances passées ; duplication sans altérer l'originale
- ✅ Bibliothèque : 23 exercices par défaut, exercices personnels et partagés en écurie, duplication, archivage, filtres
- ❌ Médias de séance (photos/vidéos) : table prévue (`session_media`), sans interface

## Partage et demi-pension
- ✅ Invitations par email (jeton haché, usage unique, adresse vérifiée), droits configurables, préréglages, période d'accès, révocation immédiate, historique des accès

## Quotidien et budget
- ✅ Plans alimentaires avec historique, suivi quotidien, notification en cas d'anomalie
- ✅ Dépenses par cheval / espace, justificatifs, indicateurs (mois, année, catégorie, cheval, évolution), exports CSV/PDF

## Application installable (PWA)
- ✅ Installation sur iPhone / Android, page « Pas de connexion » ; l'application nécessite Internet
- 🗑️ Mode hors ligne (« mode écurie ») et synchronisation supprimés en 2.2 : les copies locales sont effacées des téléphones à la mise à jour ; les tables correspondantes sont conservées en base sans usage

## Commercial
- ✅ Offres administrables (prix, limites, fonctionnalités, essai, public, archivage sans suppression), historique tarifaire, migration avec préavis ≥ 30 j
- ✅ Webhooks signés, idempotents, désordre géré, retraitement, alertes ; licences synchronisées ; grâce puis suspension ; licences manuelles
- ⚙️ Checkout, portail client, changement d'offre, résiliation/reprise : intégrés et testés avec une passerelle simulée — **nécessitent les clés Stripe** et un test en mode test Stripe
- ☑️ `billing:price-migrations` (notification + application) : non couvert par des tests automatisés
- ✅ Codes promo : réductions (coupon + code Stripe, pré-appliqué au Checkout, utilisation enregistrée par webhook) et accès gratuits pour N jours (licence `code`, expiration automatique) — testés avec une passerelle simulée ; création réelle des coupons **nécessite les clés Stripe**
- ✅ Accès offert par l'administration pour une durée en jours
- ⚠️ TVA : à déléguer à Stripe Tax si nécessaire

## Administration
- ✅ Réglages email (SMTP) et Stripe modifiables par le super-administrateur, secrets chiffrés, email de test et test de connexion Stripe
- ✅ Indicateurs (comptes, organisations, chevaux, abonnements, répartition, échecs, résiliations, MRR **estimé** distinct du facturé et de l'encaissé), utilisateurs, organisations, suspensions, licences, offres, événements Stripe, journal d'audit, erreurs, demandes RGPD
- ⚠️ Paramètres « logo » et « couleur principale » de l'administration non appliqués : l'identité Jack&Cie (logo fourni, vert/or/ivoire) est intégrée au thème

## Notifications, exports, RGPD
- ✅ Notifications en application ; ⚙️ par email dès qu'un SMTP est configuré
- ✅ Recherche globale limitée aux droits ; exports fiche/santé/séances/dépenses ; export RGPD JSON ; anonymisation ; purge planifiée

## Reste à configurer pour la production
1. Téléverser le zip, lancer l'installeur web (base MySQL, super‑administrateur), HTTPS, cron `schedule:run`.
2. SMTP (Administration › Intégrations ou `MAIL_*`) — indispensable pour la vérification d'email et les invitations.
3. Stripe (Administration › Intégrations ou `.env`) : clés, webhook, portail client, prix des offres.
4. Premier super-administrateur + 2FA.
5. Textes légaux validés et paramètres RGPD (raison sociale, DPO, sous-traitants).
6. Sauvegardes et test de restauration.
