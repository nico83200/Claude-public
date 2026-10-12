# État des fonctionnalités

Légende : ✅ terminé et couvert par des tests automatisés · ☑️ terminé, vérifié manuellement / partiellement testé ·
⚙️ terminé mais nécessite une configuration · ⚠️ partiel · ❌ non réalisé

## Version 2.12 — exercices et démonstrations
- ✅ 2.12 : bibliothèque d'exercices par défaut portée à 125 exercices (échauffement, transitions, figures, latéral, barres au sol, obstacle, travail à pied, longe, extérieur, western, endurance, attelage, voltige, position du cavalier, désensibilisation, liberté…) ; ajoutés aussi aux installations existantes lors de la mise à jour, sans jamais écraser un exercice modifié
- ✅ 2.12 : Administration › Exercices : le super-administrateur recherche, ajoute, modifie et retire les exercices de la bibliothèque par défaut ; image (JPEG, PNG, WebP) ou vidéo (MP4, WebM, MOV) de démonstration par exercice, envoyée par morceaux, contrôlée (signature du fichier) et servie aux seuls utilisateurs connectés ; affichée sur la fiche de l'exercice, signalée dans la liste et dans les séances (« Voir la démonstration »). Les séances gardent leur copie figée de l'exercice
- ✅ 2.12 : code promo à l'inscription : champ « J'ai un code promo » et lien ou QR code `…/register?code=CODE` (le code est retenu aussi pour l'inscription avec Google / Apple) ; un code d'accès gratuit est activé dès la création du compte, sans carte bancaire ; un code refusé est signalé sans créer le compte
- ✅ 2.12 : corrigé : à la fin d'un accès offert par code, l'espace revient à l'offre gratuite Découverte au lieu de passer en lecture seule
- ✅ 2.12 : offre « Particulier » renommée « Propriétaire » : un seul cheval, toutes les fonctionnalités (assistant, budget, documents, exports, partage) ; plusieurs chevaux : « Propriétaire multi-chevaux ». Appliqué à la mise à jour seulement si l'offre n'a pas été retouchée dans l'administration ; les abonnements en cours conservent leurs 3 chevaux
- ✅ 2.12 : provenance des inscriptions : les liens et QR codes d'inscription portent `?src=` (affiche, instagram, instagram-story…) ; Administration › Statistiques et la fiche de chaque code promo affichent, par source, les scans / clics, les visiteurs, les comptes créés (taux de conversion) et les codes activés — inscription par email, Google ou Apple ; aucune donnée personnelle enregistrée (jeton de session condensé)
- ✅ 2.12.3 : Codes promo : générateur de QR code (code promo et source facultatifs, aperçu, lien à copier, SVG pour l'impression, PNG pour les réseaux) ; sources de provenance ajoutées, renommées ou retirées dans l'administration, avec un lien et un QR code par source sur la fiche de chaque code
- ✅ 2.12.3 : Codes promo modifiables après création : libellé, dates de validité (fin incluse), nombre maximal d'utilisations (jamais sous les utilisations déjà faites), réservé aux nouveaux clients ; un code de réduction Stripe est remplacé chez Stripe par un code identique portant les nouveaux réglages (ancien code réactivé si Stripe refuse)
- ✅ 2.12.4 : rappel quotidien non activé : suggestion envoyée de temps en temps (application et téléphone, jamais par email) aux utilisateurs actifs inscrits depuis plus de 3 jours — au plus 3 fois, espacées de 2 semaines ; activation en un geste (19 h, modifiable) ou « Ne plus me le proposer » ; plus aucune suggestion après une désactivation volontaire
- ✅ 2.12.5 : corrigé : sur téléphone, la date ou l'heure ne pouvait pas être modifiée dans les fenêtres (nouvel événement, dépense, pension…) : ces fenêtres s'ouvrent désormais en plein écran sur téléphone, hors de la couche modale du navigateur
- ✅ 2.12.5 : séance : bouton « Modifier » (date et heure, type, cavalier, enseignant, objectif, durée, lieu, précautions, notes, statut) ; séance terminée : notes seules
- ✅ 2.12.5 : un compte = son espace personnel (où il est aussi cavalier) et au plus une écurie (offre Écurie) ; être invité dans l'écurie d'un tiers ne compte pas. Les écuries supplémentaires déjà créées ne sont pas supprimées
- ✅ 2.12.5 : événement : intervenant en tête de fiche, nom cliquable vers sa fiche ; sur téléphone uniquement (pas sur tablette ni ordinateur), boutons « Appeler » et « SMS » (message pré-rempli avec la date du rendez-vous et le cheval) ; mêmes boutons sur la fiche de l'intervenant
- ✅ 2.12.6 : Administration › Organisations : comptes ayant plusieurs écuries (créées avant la règle) listés avec chevaux, membres et abonnement payant éventuel ; le super-administrateur choisit l'écurie conservée, les autres sont suspendues (lecture seule, données conservées, réactivables) ; les abonnements payants ne sont pas résiliés automatiquement
- ✅ 2.12.7 : Administration › Organisations › fiche : suppression d'un espace, confirmée en recopiant son nom ; chevaux de l'espace supprimés (effacés définitivement après 30 jours par la purge), membres retirés, chevaux confiés par d'autres espaces rendus, utilisateurs replacés dans leur espace personnel ; impossible pour l'espace personnel d'un compte (il suit la suppression du compte) ou tant qu'un abonnement payant est en cours ; journalisé
- ✅ 2.12.14 : `cron.php` se relance automatiquement avec PHP 8.3 quand l'hébergeur lance le cron avec une version plus ancienne (cas Hostinger : PHP 8.2 par défaut pour les tâches cron) ; Administration › Notifications affiche la commande exacte avec le chemin de PHP 8.3 du serveur.
- ✅ 2.12.13 : Cron fiabilisé sur hébergement mutualisé : `cron.php` fonctionne aussi quand l'hébergeur le lance avec PHP CGI (avant, il s'arrêtait sans rien faire), tâches planifiées exécutées dans le même processus (plus de sous-processus PHP), journal des passages affiché dans Administration › Notifications. Notifications envoyées en priorité haute (sinon retardées par Apple et Android en veille). Accusé de réception de l'appareil : le diagnostic distingue « accepté par Apple/Google » et « reçu et affiché par le téléphone ». Service worker v8 : installation qui ne bloque plus si une ressource manque.
- ✅ 2.12.12 : Une mise à jour restée inachevée (site bloqué sur « Service indisponible ») est terminée automatiquement par la page de maintenance au bout de 2 minutes, même sans cron, puis la page se recharge.
- ✅ 2.12.11 : Fichier `cron.php` à la racine de l'application pour les hébergeurs qui demandent le chemin d'un fichier PHP au lieu d'une commande (équivaut à `php artisan schedule:run`, refusé depuis le navigateur) ; chemin exact affiché dans Administration › Notifications.
- ✅ 2.12.10 : Notifications fiabilisées. Le téléphone se réabonne tout seul si la clé d'envoi du serveur a changé (avant, il restait lié à l'ancienne clé et ne recevait plus rien, sans alerte). Relais du planificateur quand le cron de l'hébergement manque (tâches lancées à la visite suivante, rappels du jour rattrapés sans doublon). Administration › Notifications : état du cron (avec la ligne à ajouter), file d'attente, appareils abonnés par utilisateur, dernière réception et erreur exacte du service Apple/Google, essai d'envoi, raison du rappel quotidien (envoyé, pas encore l'heure, déjà noté aujourd'hui…). Les messages indiquent le nombre d'appareils atteints. Paramètres › Notifications : état du rappel du jour, erreur détaillée lors de l'essai.
- ✅ 2.12.9 : Tout est rassemblé dans l'agenda. Synchronisation complétée : une séance, un cours ou une balade saisi dans l'agenda devient une vraie séance ; un traitement saisi dans l'agenda est créé dans Santé (et inversement, début et fin) ; le contrôle prévu après un soin suit dans les deux sens (déplacer, marquer réalisé = nouveau soin, supprimer = contrôle annulé), quelle que soit l'origine du soin (formulaire, assistant, facture). L'agenda affiche aussi, lus à la source et selon les droits : pesées, observations, administrations de traitement, journal, dépenses, échéances récurrentes, relevés de pension, rations, déplacements (filtre « Afficher »). Créer un soin ou un traitement depuis l'agenda demande les droits santé. Rappel régulier de pesée (Poids : fréquence, notification le jour prévu, « Pesée à faire » dans l'agenda). Super-administrateur : « Messages » envoyés en notification (application, téléphone, e-mail facultatif) à tous ou selon l'offre, avec historique.
- ✅ 2.12.8 : Agenda ↔ Santé synchronisés : un rendez-vous vétérinaire, maréchalerie ou soin marqué « Réalisé » (ou créé à une date passée) dans l'agenda crée le soin dans le carnet de santé ; un soin saisi dans Santé apparaît dans l'agenda ; modification et suppression suivent des deux côtés ; les rendez-vous à venir ou à valider sont listés dans Santé. Cliquer un événement ouvre sa fiche détaillée, la modification est une page séparée (bouton « Modifier »). Reprise automatique de l'historique existant.
- ✅ 2.12 : Poids : méthode « Ruban et mètre » proposée par défaut ; vidéo « Pas de balance ? Mesurez au ruban » (avec voix off) mise en avant en haut du formulaire

## Version 2.11 — thème sombre, poids, rappels intelligents, factures lues, pension
- ✅ Thème clair (par défaut), sombre ou automatique (suit le réglage du téléphone ou de l'ordinateur) : Paramètres › Apparence ; choix mémorisé sur le compte et appliqué avant l'affichage (pas de flash)
- ✅ Suivi du poids et de l'état corporel (note de 1 à 9) : onglet « Poids » du cheval, pesée, ruban (tour de poitrine² × longueur / 11 877) ou estimation ; courbe interactive et tableau ; alerte aux responsables du cheval au-delà de 5 % de variation ou de 2 points d'état ; ajout possible par l'assistant vocal
- ✅ Vidéo explicative « Comment mesurer au ruban » (1 min, sous-titrée) dans le formulaire de mesure
- ✅ Rappels intelligents : « Rythme habituel » de chaque type de soin calculé sur l'historique (au moins 3 passages), date probable du prochain passage, retard signalé, « Prendre rendez-vous » pré-rempli dans l'agenda, adoption du rythme comme rappel de suivi ; prévenance quotidienne quand un passage habituel approche
- ✅ Facture photographiée (ou PDF) lue par l'assistant : fournisseur, date, lignes, total, catégorie et cheval proposés ; écran de vérification avant enregistrement ; facture jointe en justificatif ; soin ajouté au carnet de santé si souhaité. Testé avec une réponse simulée de l'API — ⚙️ nécessite la clé de l'assistant (offre avec assistant)
- ✅ Fusion d'une facture lue avec une dépense déjà saisie (même cheval, date proche, montant voisin) : la dépense est complétée, sans doublon
- ✅ Les dépenses sont toujours rattachées à un cheval (formulaire, assistant vocal, factures)
- ✅ Espace écurie : facturation de la pension aux propriétaires — contrats de pension, prestations ponctuelles, relevé mensuel par propriétaire au prorata des jours de présence, numérotation, PDF, envoi (notification et somme « à payer » dans le budget du propriétaire qui a un compte), paiement noté
- ✅ Administration › Statistiques : inscriptions, activité, parcours des nouveaux comptes, essais et conversions, fonctions utilisées, propositions d'offre cliquées, résiliations — nombres agrégés uniquement

## Version 2.10 — connexion avec Google et Apple, intervenants, menus, photos
- ✅ 2.10.1 : Administration › Utilisateurs : offre en cours de chaque utilisateur (nom, payante / essai gratuit / offerte / gratuite / paiement en retard / terminée, montant, date de renouvellement, de fin d'essai ou de fin), une ligne par espace dont il est propriétaire ; aussi sur la fiche de l'utilisateur
- ✅ 2.10.1 : notification aux super-administrateurs à chaque nouvelle inscription (formulaire, Google ou Apple) : dans l'application, sur le téléphone et par email, avec lien vers la fiche
- ✅ « Continuer avec Google / Apple » à l'inscription et à la connexion (OpenID Connect, jetons vérifiés) ; compte et espace personnel créés en un geste ; association automatique à un compte existant seulement si l'email est confirmé par le fournisseur ; double authentification conservée ; association / retrait dans Paramètres › Sécurité ; confirmation des actions sensibles avec Google / Apple. Testé avec un faux fournisseur (vraies clés et signatures) — ⚙️ nécessite les identifiants Google Cloud et Apple Developer (voir `docs/INTEGRATIONS.md`)
- ✅ Menus déroulants modernes : liste ancrée sur ordinateur, panneau venant du bas sur téléphone, recherche au-delà de 10 choix, clavier ; le champ d'origine reste envoyé avec le formulaire (validation, filtres automatiques)
- ✅ « Nouvel intervenant ou contact » directement dans les formulaires (soin, rendez-vous, dépense, intervenants du cheval) et « Nouvel enseignant » dans les cours (suggestions des enseignants enregistrés) : la fiche créée est sélectionnée aussitôt
- ✅ Import d'une fiche contact du téléphone pour créer ou mettre à jour un intervenant : sélecteur de contacts sur Android (Chrome), fichier .vcf sur iPhone (Partager le contact › Enregistrer dans Fichiers) ; fonction déduite (vétérinaire, maréchal…) ; fiche existante reconnue par email, téléphone (tous formats) ou nom et complétée sans perdre d'information. ☑️ Sélecteur Android à confirmer sur un téléphone réel (non disponible dans le navigateur de test) ; lecture vCard testée (iPhone 3.0, Android 2.1, 4.0)
- ✅ Photos du cheval, documents image et justificatifs photo ouverts dans une visionneuse (plus de nouvel onglet) : photo suivante / précédente, glisser du doigt, bouton retour du téléphone, téléchargement
- ✅ Corrigé : invitation à activer les notifications et « Plus tard » de la carte d'offre (erreur JavaScript), initialisation des notifications

## Version 2.9 — propositions d'offre supérieure (Découverte)
- ✅ Propositions affichées là où l'offre gratuite bloque, avec un argumentaire par fonction et un bouton « Essayer 14 jours gratuitement » (ou « Passer à l'offre … » si l'essai a déjà été utilisé) qui mène à la page Abonnement, offres concernées mises en avant :
  - assistant vocal : le micro (barre du bas, bouton « Parler », carte « Dites-le simplement ») ouvre une présentation de l'assistant au lieu d'un simple message d'indisponibilité ;
  - budget : proposition en tête de page, plus de bouton « Dépense » menant à un enregistrement refusé ;
  - documents : proposition à la place du formulaire d'envoi ; onglets « Documents » et « Dépenses » du cheval signalés d'une étoile ;
  - limite de chevaux, de partages ou de stockage atteinte : proposition de l'offre qui la relève (formulaire « Nouveau cheval » remplacé, liste des chevaux, page Partage, message d'erreur d'envoi) ;
  - tableau de bord (carte masquable 7 jours) et menu (encart « 14 jours d'essai gratuit ») ;
  - page Abonnement : fonctions de chaque offre listées, « Nouveau pour vous », offres qui débloquent la fonction demandée signalées.
- ✅ Seules les personnes qui gèrent l'abonnement voient un bouton ; les autres membres sont informés que le responsable de l'espace peut changer d'offre. L'assistant n'est proposé que s'il est activé et configuré sur l'instance. Rien n'est débloqué sans paiement confirmé par Stripe.
- ✅ 2.9.1 : exports PDF / CSV (fiche, santé, séances, dépenses) réservés aux offres payantes, y compris en lecture seule après la fin d'un abonnement ; en Découverte, boutons signalés d'une étoile et proposition d'offre au clic. L'export des données personnelles (RGPD, JSON) reste ouvert à tous

## Version 2.8 — tutoriel ordinateur
- ✅ 2.8.2 : résiliation, reprise et changement d'offre visibles immédiatement (réponse de l'API Stripe appliquée, plus de bouton « Résilier » ni de date de renouvellement après une résiliation) ; état des abonnements relu chez Stripe à l'ouverture de la page Abonnement (au plus toutes les 5 min, immédiatement au retour du portail Stripe) et chaque heure par la tâche `billing:sync` : fin d'essai, renouvellement ou résiliation rattrapés même si un webhook manque
- ✅ 2.8.1 : l'abonnement s'active dès le retour du paiement Stripe (session relue auprès de l'API Stripe, espace et statut vérifiés) et même si les événements `customer.subscription.*` n'arrivent pas (abonnement relu chez Stripe à la réception de `checkout.session.completed` ou d'une facture). Corrige un abonnement resté en « Découverte » après un paiement d'essai à 0 €
- ✅ Tutoriel version ordinateur (1920×1080, 1 min 40) : mêmes scènes, même texte, même voix off et mêmes sous-titres que la version mobile ; barre latérale, bouton « Parler », calendrier mensuel, curseur visible
- ✅ Vidéos ciblées par appareil (téléphone et tablette, ordinateur, tous) : un nouveau compte reçoit le tutoriel de l'appareil sur lequel il se connecte ; réglable pour chaque vidéo dans Administration › Vidéos ; la page « Tutoriels » propose les deux versions

## Version 2.7 — vidéos
- ✅ Tutoriel vidéo mobile (1 min 40 avec voix off, MP4 H.264 780×1688, 4 Mo ; le lecteur sait utiliser une version WebM si elle est fournie à côté) livré avec l'application, sous-titres français (WebVTT) ; tourné sur l'application réelle (données de démonstration ; pour la scène de l'assistant, réponse de l'IA rejouée par un faux serveur, la création de la séance étant réelle)
- ✅ Affichage automatique en plein écran : tutoriel à la première connexion des nouveaux comptes, nouveautés à la prochaine connexion des comptes existants ; une fois par session tant qu'elle n'est pas vue (« Passer » ou fin de la vidéo) ; jamais par-dessus l'écran d'activation des notifications
- ✅ Administration › Vidéos : création, envoi par morceaux (vidéo, voix off, sous-titres SRT/VTT, image d'aperçu), aperçu, publication / retrait, statistiques de vues ; fichiers privés servis avec reprise de lecture (iPhone)
- ✅ Voix off : piste audio séparée, lue en synchronisation avec la vidéo (lecture, pause, avance) ; texte minuté dans `docs/TUTORIEL-VOIX-OFF.md`
- ✅ Menu « Tutoriels » : toutes les vidéos publiées, à revoir à tout moment
- ✅ 2.7.1 : voix off enregistrée intégrée (1 min 40), scènes recalées sur chaque paragraphe, sous-titres calés sur les phrases prononcées

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
- ✅ Recherche globale limitée aux droits ; exports fiche/santé/séances/dépenses (offres payantes) ; export RGPD JSON ; anonymisation ; purge planifiée

## Reste à configurer pour la production
1. Téléverser le zip, lancer l'installeur web (base MySQL, super‑administrateur), HTTPS, cron `schedule:run`.
2. SMTP (Administration › Intégrations ou `MAIL_*`) — indispensable pour la vérification d'email et les invitations.
3. Stripe (Administration › Intégrations ou `.env`) : clés, webhook, portail client, prix des offres.
4. Premier super-administrateur + 2FA.
5. Textes légaux validés et paramètres RGPD (raison sociale, DPO, sous-traitants).
6. Sauvegardes et test de restauration.
