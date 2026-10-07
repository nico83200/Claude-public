# Journal des versions

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
