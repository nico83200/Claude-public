# Journal des versions

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
