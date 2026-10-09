# Journal des versions

## 1.18.0
- **Nouveau rôle « Acheteur »** : il dispose de tout le service achats (pilotage, direction, demandes à traiter, bons de commande, articles proposés, fournisseurs, articles, catégories, dates limites, comparateur, factures, exports comptables, budgets, stocks des centres) et voit tous les centres, mais pas l'**organisation** (centres, comptes) ni les **paramètres** (paramètres, fiche RGPD, journal d'audit, nettoyage des données, mises à jour et sauvegardes, file d'e-mails, gestion des vidéos). Ces pages sont retirées de son menu et refusées s'il tente d'y accéder directement.
- L'acheteur reçoit les notifications du service achats (nouvelles demandes, articles proposés, hausses de prix, budgets…), mais pas les comptes à valider ; la double authentification exigée des administrateurs s'applique aussi à lui.
- Choix du rôle dans *Organisation → Comptes* : Salarié, Responsable de centre, Acheteur ou Administrateur.

## 1.17.0
- **Vidéo d'accueil des salariés** : à la connexion d'un salarié, le tutoriel s'ouvre en fenêtre, avec une case « Ne plus afficher ». Tant qu'elle n'est pas cochée, la fenêtre revient à la connexion suivante (une seule fois par connexion) ; la vidéo reste disponible dans « Tutoriels vidéo ». Jamais montrée aux administrateurs.
- Réglage (Tutoriels vidéo → Vidéo d'accueil des salariés) : automatique (vidéo désignée par NLapps), une vidéo au choix ou aucune ; bouton pour la montrer à nouveau à tous les salariés.

### Centre d'assistance NLapps 3.1.0
- Case « Vidéo d'accueil des salariés » sur chaque vidéo : transmise aux installations, qui l'ouvrent à la connexion de leurs salariés.

## Centre d'assistance NLapps 3.0.0
- **Comptes avec identifiant et mot de passe** : un compte par personne (administrateur ou conseiller), double authentification par compte, réponses signées du nom du conseiller. L'ancien accès devient le compte `admin` (même mot de passe).
- **Plusieurs applications** : un sous-menu par application (parc clients, versions, FAQ, vidéos) ; les conversations restent communes, avec un filtre par application. Ajout d'une application en un formulaire (nom, couleur, tarifs).
- **Même interface partout** : menu latéral sur ordinateur, en tiroir sur tablette et téléphone, tableaux lisibles en fiches sur petit écran.

## 1.16.0
- **Tutoriels vidéo** (menu *Aide → Tutoriels vidéo*) : lecteur avec chapitres cliquables (le chapitre en cours est mis en évidence), avance rapide, lecture sur ordinateur, tablette et téléphone. Les vidéos publiées par NLapps arrivent automatiquement (téléchargées en arrière-plan, empreinte vérifiée) ; un administrateur peut aussi ajouter ses propres vidéos (procédures internes), avec titre, mots-clés, chapitres et visibilité (tous / administrateurs).
- **L'aide en ligne propose la bonne vidéo** : à une question comme « comment réceptionner un colis ? », le chatbot répond et ajoute « Voir le tutoriel vidéo », qui ouvre la vidéo directement au chapitre concerné. Si aucune réponse écrite ne convient, c'est la vidéo qui répond. Raccourci « 🎬 Tutoriels vidéo » dans la bulle d'aide.

### Centre d'assistance NLapps 2.2.0
- Page **Vidéos** : publiez un tutoriel (MP4) une seule fois, avec ses chapitres et mots-clés ; il est transmis à toutes les installations à jour de leur licence et proposé par leur chatbot. Modification des chapitres sans renvoyer la vidéo ; retrait en un clic.

## 1.15.1
- **Réception par scan** : correction, la caméra ne lisait plus que le premier code-barres ; elle enchaîne maintenant tous les articles livrés sans se refermer. Une boîte restée devant la caméra n'est comptée qu'une fois : il suffit de la retirer du cadre puis de présenter la suivante, même s'il s'agit du même article.
- Vidéo tutoriel « version salarié » enrichie (demande d'articles et réception détaillées), avec le texte des sous-titres pour une voix off (`tools/tutoriel-salarie.mjs`).

## 1.15.0
- **Licence expirée = accès coupé** : dès le lendemain de l'échéance (ou à la fin d'un délai de grâce si NLapps en accorde un), et à tout moment en cas de suspension, **tous les utilisateurs sont déconnectés** et la connexion est refusée (page « Licence expirée » avec les coordonnées de NLapps). Les données sont conservées. L'échéance est contrôlée localement chaque jour et la licence est revérifiée auprès de NLapps toutes les 10 minutes ; après renouvellement, « Vérifier à nouveau » rétablit l'accès aussitôt. La déconnexion forcée est inscrite au journal d'activité.
- Bandeau administrateur : rappel 15 jours avant l'échéance avec la date de coupure.

### Centre d'assistance NLapps 2.1.0
- **Mise à jour par paquet ZIP** depuis la console (Réglages → Mise à jour du centre), comme dans Approvia : confirmation par mot de passe, sauvegarde automatique, retour arrière en un clic ; `config.php` et `data/` jamais modifiés. Paquet allégé `nlapps-assistance-maj.zip` (sans la bibliothèque de l'IA) pour les hébergements limités en taille d'envoi.
- **Console installable** sur ordinateur, Android et iPhone/iPad (bouton « Installer », raccourcis, page hors connexion).
- **Délai de grâce réglable** (0 par défaut : coupure immédiate à l'échéance).

## 1.14.1
- **Inventaire tablette** (ex-« Mode réserve ») : une seule saisie, le **stock actuel**. Après le scan, la quantité théorique est proposée ; on saisit ce qui est réellement présent et l'écart est enregistré automatiquement comme **sortie** (consommation, prise en compte dans les seuils conseillés) ou **entrée**, avec l'écart affiché avant validation.
- Après « Valider le stock », **le scanner se relance automatiquement** pour l'article suivant (avec une douchette, la zone de saisie reprend la main ; Entrée valide). La même boîte encore devant la caméra n'est pas recomptée.

## 1.14.0
- **Tableau de bord direction** (menu *Direction*) : dépenses engagées, économies obtenues grâce aux tarifs négociés, nombre de bons et panier moyen, valeur des stocks, comparaison avec la période précédente ; dépenses mensuelles sur 12 mois ; répartition par centre, catégorie, fournisseur et articles les plus achetés ; suivi des budgets. Filtres par période et par centre.
- **Rapport PDF mensuel** créé automatiquement chaque début de mois, téléchargeable et envoyé par e-mail aux destinataires choisis (direction, DAF).
- **Lecture des factures par l'IA** (option assistant IA) : depuis le bon de commande, « Lire la facture avec l'IA » reconnaît le numéro, la date et le montant HT d'une facture PDF ou photo, pré-remplit le formulaire et signale les écarts avec le bon (prix, quantités, articles non commandés).
- **Alerte hausse de prix à l'import** : l'aperçu affiche la variation de prix de chaque article mis à jour, signale en rouge les hausses au-delà d'un seuil réglable (5 % par défaut) avec un bouton pour ne pas les importer ; un seul récapitulatif est notifié aux administrateurs.
- **Double authentification** (code à 6 chiffres, Google Authenticator, Microsoft Authenticator, Authy…) depuis « Mon profil » ; peut être rendue obligatoire pour les administrateurs (Paramètres) ; réinitialisation par un autre administrateur en cas de téléphone perdu.
- **Fiche RGPD et sécurité** (Paramètres) : document prêt à imprimer ou enregistrer en PDF pour la direction ou le DPO, établi d'après la configuration réelle (données traitées, absence de données patient donc pas d'hébergement HDS requis, durées de conservation, sous-traitants, mesures de sécurité).

## 1.13.0
- **Réapprovisionnement automatique** : dès qu'un article suivi passe sous son seuil d'alerte, une demande est créée dans « Demandes à traiter » (quantité = double du seuil, déduction faite de ce qui est déjà en commande ; urgente en cas de rupture ; jamais en double). Activable / désactivable dans *Stocks des centres*.
- **Seuils conseillés** dans l'inventaire : consommation des 90 derniers jours × (délai du fournisseur + 7 jours de sécurité). Un clic sur « conseillé : n » reprend la valeur ; « Appliquer les seuils conseillés » les met tous à jour.
- **Transferts entre centres** : dans « Demandes à traiter », quand un autre centre a l'article en excédent (au-delà de son seuil), le bouton « Transférer depuis… » sert la demande sans commande (stocks des deux centres ajustés, demandeur prévenu, ligne « Transférée »).
- **Réception par scan** : sur un bon à réceptionner, « Scanner les articles livrés » (caméra en continu) ou douchette : chaque code lu ajoute une unité à la bonne ligne ; alerte si l'article est complet ou absent du bon.
- **Inventaire tournant** : chaque semaine, une dizaine d'articles à compter par centre (jamais comptés depuis longtemps, coûteux ou très consommés), écart affiché pendant la saisie et chiffré en euros, historique des écarts par semaine ; rappel le lundi aux responsables.

## 1.12.0
- **Licence NLapps** : la clé fournie par NLapps (Paramètres → Licence et assistance) active l'abonnement. L'état est vérifié toutes les 6 heures : échéance, option assistant IA (coupée si non souscrite), bandeau pour l'administrateur avant l'échéance, délai de grâce de 15 jours, puis coupure de l'IA et des mises à jour ; accès suspendu possible par NLapps. Une coupure réseau ne bloque jamais l'application.
- **Mises à jour en un clic** : les nouvelles versions publiées par NLapps sont annoncées à l'administrateur (bandeau + page Mises à jour avec les nouveautés), téléchargées et vérifiées (empreinte SHA-256) puis installées avec sauvegarde automatique et retour arrière possible.
- **FAQ partagée** : le chatbot connaît aussi les questions publiées par NLapps depuis son centre d'assistance, sans mise à jour.
- **Conversation avec un conseiller** : envoi de captures d'écran (bouton appareil photo), images du conseiller affichées dans la bulle, note de satisfaction (1 à 5 étoiles) à la clôture.

## 1.11.1
- **Demandes à traiter : stock du centre** pour chaque article suivi en stock dans le centre demandeur (colonne « Stock centre ») : quantité disponible, repères « Rupture », « Sous le seuil » ou « Couvre la demande », quantité déjà en commande, emplacement au survol et lien vers l'historique des mouvements. « — » pour les articles non suivis.

## 1.11.0
- **Étiquettes d'étagère** pour la salle de stock : nom de l'article, fournisseur, référence, conditionnement, emplacement et **code-barres scannable** (EAN-13 / EAN-8 de l'article, ou Code 128 de sa référence ; vérifiés avec le lecteur de l'application). Bouton « Imprimer l'étiquette » sur la fiche article (et dans la fiche admin), « Étiquettes » dans l'inventaire (tous les articles suivis du centre) et dans la sélection multiple de la liste des articles.
  - Formats : planches A4 de 24 (70 × 37 mm), 14 (99 × 38 mm) ou 8 grandes étiquettes (105 × 74 mm), rouleaux 100 × 50 mm et 62 × 29 mm (imprimantes d'étiquettes Brother, Dymo…).
  - Nombre d'exemplaires, départ à la n-ième étiquette pour réutiliser une planche entamée, bande de couleur de la catégorie, aperçu à taille réelle.
- **Emplacement de rangement** des articles suivis en stock (lieu, étagère, bac…), propre à chaque centre :
  - affiché aux salariés sur la fiche article (« Dans votre centre : rangé à … », avec la quantité en stock), lors d'un scan et dans l'historique ;
  - saisi depuis la fiche article ou dans l'inventaire, sous le nom de chaque article ;
  - repris automatiquement sur les étiquettes.

## 1.10.0
- **Corrections de stock par l'administrateur**, depuis l'historique des mouvements (Inventaire → article ou « Mouvements ») :
  - **suppression** d'une entrée, d'une sortie ou de tout autre mouvement, ou de plusieurs à la fois (cases à cocher) ; son effet sur le stock est annulé ;
  - **correction** d'un mouvement : quantité, motif et **centre** (mouvement saisi dans le mauvais centre : il est retiré de ce centre et appliqué au bon, à la même date) ;
  - **« Changer de centre »** pour le stock complet d'un article : stock, seuil d'alerte et historique sont rattachés au bon centre (si l'article y est déjà suivi, les quantités sont additionnées par des mouvements « Transfert »).
  - Le stock et les « stock après » sont recalculés automatiquement ; un inventaire postérieur reste la référence. Chaque correction est tracée dans le journal d'activité.

## 1.9.1
- **Accès à l'assistance NLapps dans les paramètres** : l'administrateur colle les deux lignes fournies par NLapps (adresse et clé `nlh_…`) dans *Paramètres → Assistance NLapps*. Les champs se remplissent automatiquement, la clé est chiffrée et la connexion est testée à l'enregistrement. Boutons « Tester la connexion » et « Supprimer l'accès ». Plus besoin de modifier `config.php`, qui reste utilisé si rien n'est saisi.

## 1.9.0
- L'application devient **Approvia** : nouveau logo dans le menu, la page de connexion, l'onglet du navigateur et l'icône de l'application installable.
- **Conversation en direct avec NLapps** dans la bulle d'aide, à la place de WhatsApp : si le chatbot ne trouve pas de réponse, ou dès que l'utilisateur demande à parler à un conseiller, la conversation s'ouvre dans la même fenêtre. L'échange avec le chatbot et le contexte (centre, version, page) sont transmis au conseiller. Disponibilité affichée, message d'absence, conversation reprise d'une page à l'autre, notification dans l'application quand une réponse arrive fenêtre fermée.
- Nouveau **centre d'assistance NLapps** (dossier `support-hub/`, à installer sur nlapps.fr) : console unique pour toutes les installations clientes, clés d'accès par client, alertes e-mail et notification sur téléphone (ntfy).

## 1.8.0
- L'application devient **ScanAppro**, éditée et maintenue par NLapps (nom par défaut des nouvelles installations, application installable, mention dans le menu).
- **Assistance intégrée** : bulle d'aide sur toutes les pages avec un chatbot qui répond immédiatement aux questions courantes (commande, scanner, caméra, réception, inventaire, budgets, import…), avec réponses plus fines si l'option assistant IA est activée.
- Si la réponse ne suffit pas : contact de l'équipe NLapps par **WhatsApp** (message pré-rempli avec le contexte : client, utilisateur, centre, version, page) ou par **formulaire** (demande enregistrée et envoyée par e-mail, ou transmise en un clic par WhatsApp / e-mail si l'envoi automatique est désactivé). Page « Assistance » avec l'historique des demandes et les coordonnées NLapps.

## 1.7.3
- Affichage sur smartphone : l'icône du panier n'est plus coupée (nom du centre tronqué si nécessaire) et plus aucune page ne déborde en largeur (colonnes, boutons, onglets et liens longs s'adaptent).

## 1.7.2
- Correctif : après une mise à jour, le navigateur pouvait continuer d'utiliser d'anciennes feuilles de style et d'anciens scripts gardés par l'application installable (service worker). Styles et scripts sont désormais toujours chargés depuis le serveur, les anciens caches sont supprimés et les pages ouvertes rechargées automatiquement. Le service worker n'est plus mis en cache par le serveur.

## 1.7.1
- Correctif : sur le tableau de bord, les suggestions de la recherche n'étaient plus coupées par le bandeau d'accueil ; elles s'affichent en entier par-dessus la page (ordinateur et mobile).

## 1.7.0
- Fiche fournisseur : choix du mode de commande (commande en ligne, bon PDF par e-mail, téléphone, autre) avec l'adresse de commande en ligne et une précision libre. Les anciennes saisies libres (« Site web », « E-mail »…) sont reconnues automatiquement.
- Commande en ligne : dès la création du bon (simple ou groupé), bouton « Ouvrir le site du fournisseur » (nouvel onglet), rappel du n° client et copie en un clic des références et quantités à coller sur le site ; on note ensuite le n° de commande web.
- PDF par e-mail sans envoi automatique : e-mail prêt à envoyer avec le PDF joint (fichier .eml pour Outlook ou Courrier Windows), ou téléchargement du PDF et e-mail pré-rempli dans n'importe quelle messagerie.
- Le mode de commande s'affiche sur les cartes fournisseurs, avec un raccourci vers le site de commande.

## 1.6.1
- Suppression d'articles, un par un (bas de la fiche article) ou en lot (cases à cocher dans la liste des articles). Un article jamais commandé est effacé avec son stock et son historique de prix ; un article présent dans des demandes ou des bons de commande est masqué du catalogue (et retiré des paniers, favoris et listes types) pour conserver l'historique.
- La barre d'actions groupées (articles, comptes) reste visible en bas de l'écran pendant le défilement.

## 1.6.0
- Import assisté d'articles (Articles → Importer) à partir d'un fichier CSV, Excel (.xlsx, et .xls exporté au format HTML/XML) ou OpenDocument (.ods), sans modèle imposé : lignes de titre, encodage Windows, prix « 12,50 € » ou TTC gérés.
- Correspondance par l'IA : colonnes du fichier → champs du catalogue, fournisseurs et familles du fichier → fournisseurs et catégories existants, catégorie proposée pour les articles qui n'en ont pas. Tout est modifiable avant l'import ; sans IA, reconnaissance par les intitulés des colonnes.
- Aperçu ligne à ligne : nouveaux articles, articles déjà au catalogue (même référence, même code-barres ou même désignation chez ce fournisseur) mis à jour avec historique des prix, doublons et lignes incomplètes signalés.

## 1.5.0
- Nettoyage des données (menu Service achats) : suppression en un clic des données de démonstration (centres, fournisseurs, articles, comptes @demo.fr, demandes et bons associés), ou effacement de toute l'activité de test en conservant catalogue, centres, comptes et paramètres. Sauvegarde automatique avant chaque nettoyage et confirmation par mot de passe. Bandeau sur le tableau de bord tant que la démo est présente.
- Suppression de comptes, un par un (fiche du compte) ou en lot (cases à cocher dans la liste). Un compte sans historique est effacé ; un compte qui a passé des demandes est anonymisé pour conserver l'historique des commandes. Son propre compte et le dernier administrateur sont protégés.
- Réception : après « Enregistrer la réception », le service achats revient sur la fiche du bon de commande concerné.

## 1.4.2
- « Tester l'assistant » affiche désormais la cause précise d'un échec de l'IA, avec la piste de correction : clé refusée, crédit API insuffisant, modèle introuvable, connexion sortante bloquée, bibliothèque absente, clé illisible…
- Si le compte n'accepte pas le repli automatique côté serveur, la recherche IA est relancée sans cette option au lieu d'échouer.

## 1.4.1
- Correctif : l'enregistrement de la clé API IA (ou du mot de passe SMTP) pouvait provoquer une erreur 500 sur les hébergements sans l'extension PHP sodium. Chiffrement de secours OpenSSL (AES-256-GCM) et message explicite en cas de problème (droits du dossier storage/, extension manquante).
- Les erreurs inattendues affichent une page explicite (détail visible des administrateurs) et sont consignées dans storage/logs/php-errors.log.

## 1.4.0
- Logo de l'entreprise modifiable (Paramètres) : menu, page de connexion, bons de commande (PDF et impression) et e-mails.
- Fiche centre complète : raison sociale, contact (nom, e-mail, téléphone), adresse de livraison avec complément et consignes, adresse de facturation distincte possible avec e-mail de comptabilité et mentions, SIREN, SIRET, FINESS et TVA intracommunautaire.
- Contrôles : clé de Luhn (SIREN, SIRET, dont l'exception La Poste), cohérence SIREN / SIRET / TVA, format FINESS ; SIREN et TVA déduits automatiquement du SIRET.
- Installation simplifiée : paquet complet prêt à décompresser (dépendances incluses), assistant qui vérifie l'hébergement, saisit les accès à la base, écrit config.php et se supprime à la fin ; guide pas à pas pour Hostinger.
- Bons de commande : bloc « Facturation » et identifiants légaux du centre, contact de livraison.

## 1.3.1
- Paramètres : envoi des e-mails activable au cas par cas (chaque notification, mot de passe oublié, bons aux fournisseurs, copie à l'expéditeur), indépendamment des notifications dans l'application.
- Paramètres : clé API de l'assistant IA saisissable et remplaçable dans l'interface, chiffrée en base (storage/secret.key), jamais réaffichée en clair ; mot de passe SMTP également chiffré.

## 1.3.0
- Mise en service : photos réduites automatiquement (navigateur et serveur), mot de passe oublié par e-mail, file d'attente des e-mails avec nouvelles tentatives, anti-force brute en base (par compte et par IP), sauvegarde quotidienne de la base avec restauration.
- Tâches planifiées (cron.php ou exécution automatique pendant l'utilisation) : rappels la veille des dates limites, relance des livraisons en retard, sauvegarde, nettoyage.
- Salariés : listes types (partagées ou personnelles), suggestions de réapprovisionnement des stocks bas, mode réserve plein écran pour tablette, application installable (PWA).
- Achats : historique des prix et alerte de hausse, comparateur fournisseurs et bascule vers l'équivalent le moins cher, commandes groupées multi-centres (un bon par centre, franco sur le total), envoi du bon de commande en PDF au fournisseur, rapprochement des factures, exports comptables CSV.
- Organisation : rôle « responsable de centre » et validation des demandes au-delà d'un seuil, journal d'audit.
- Qualité : suite de tests automatiques (php tests/run.php).

## 1.2.0
- Articles hors catalogue : proposition par le salarié depuis le panier ou après le scan d'un code-barres inconnu (photo, marque, référence, lien, prix estimé, quantité).
- Écran administrateur « Articles proposés » : compléter et ajouter au catalogue, rattacher à un article existant ou refuser ; la demande liée rejoint automatiquement le circuit de commande.

## 1.1.0
- Inventaire par centre : stock alimenté par les réceptions, inventaires (stock compté), sorties et entrées manuelles, seuils d'alerte, historique des mouvements, vue consolidée des stocks.
- Code-barres : champ EAN sur les articles, scan par la caméra du smartphone ou de la tablette (recherche, inventaire, fiche article), compatibilité douchette.
- Mises à jour depuis l'interface d'administration avec sauvegarde automatique et retour à la version précédente.
- Budgets annuels par centre avec seuil d'alerte.
- Notifications à chaque étape de la commande (application et e-mail, réglables par événement et par utilisateur).

## 1.0.0
- Version initiale : catalogue, recherche assistée par IA, panier, demandes, bons de commande, réceptions, fournisseurs, articles, centres, comptes, dates limites.
