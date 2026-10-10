# Journal des versions

## 1.28.0
- **Une seule page de connexion** : le super administrateur se connecte sur la même page que tout le monde (centriva.fr ou la page de connexion d'un espace) et arrive dans l'administration de la plateforme, double authentification comprise.
- **Paramètres en catégories** : sous-menu Identité, Règles de gestion, Sécurité, Assistant IA, E-mails et notifications, Abonnement (et Assistance NLapps hors plateforme), dans le menu et en onglets.
- **Tâches planifiées réservées au super administrateur** sur la plateforme (cron commun à tous les clients, depuis la console) : la catégorie n'apparaît plus dans les espaces.
- **Abonnement géré par chaque client** (*Paramètres → Abonnement*, administrateur) : formule, échéance, mensualité détaillée, paiement en ligne par carte ou prélèvement SEPA (Stripe), espace de gestion Stripe (moyen de paiement, factures), historique. **Abonnement expiré** : l'administrateur est conduit au paiement dès sa connexion et l'accès revient aussitôt pour tous ; les autres utilisateurs sont invités à le prévenir.
- **Codes d'accès gratuit et bons de réduction** (console → Codes) : jours offerts, ou réduction en % ou en € HT par mois pendant N mois (ou sans limite), période de validité, nombre maximal d'utilisations, réservation à certains clients ; un code ne sert qu'une fois par client. La réduction s'applique au paiement en ligne (coupon Stripe), y compris à un abonnement déjà en place.
- **Base SQL commune à tous les clients** (console → Base de données) : une seule base MySQL / MariaDB, chaque client y a ses propres tables préfixées par son identifiant (imss_users…) ; ses données ne sont jamais mêlées à celles des autres. Les nouveaux clients y sont créés ; un client existant y est transféré en un clic (copie complète, vérification table par table, ancienne base conservée). La suppression d'un client archive puis supprime ses seules tables.
- **Licences centralisées dans la console Centriva** : le centre d'assistance NLapps ne gère plus aucune licence, version, FAQ ni vidéo. Hors plateforme, Centriva n'a plus de licence ; la clé de l'assistance ne sert qu'au chat.

### Centre d'assistance NLapps 4.0.0
- **Le chat seulement** : conversations, réponses rapides, suggestion IA, comptes, notifications. Les pages Parc clients, Versions, FAQ, Vidéos, Abonnements et les réglages de licence et de paiement sont retirés (les données d'avant restent lisibles par la console Centriva pour la reprise).
- Nouvelle page **Accès au chat** : une clé par client (créées automatiquement pour les espaces Centriva). Les anciens liens de paiement mènent à la plateforme.

## 1.27.0
- **Toute la gestion de Centriva dans la console des super administrateurs** (le centre d'assistance ne garde que les conversations en direct) :
  - **Abonnements** : licence de chaque client (formule, prix propre ou tarif de la plateforme, option IA, échéance « payé jusqu'au », suspension, message aux administrateurs), prolongation après un paiement reçu hors ligne, revenu mensuel, clients en paiement automatique, encaissé du mois, retards et échéances proches, journal des paiements avec les factures. Les espaces lisent leur licence directement, sans appel réseau : un changement s'applique immédiatement.
  - **Paiement en ligne (Stripe)** : lien de paiement personnel (`centriva.fr/?paiement=…`) par carte ou prélèvement SEPA, espace client Stripe, webhook `centriva.fr/?webhook=stripe` (paiement reçu → échéance prolongée, échec signalé par e-mail). Les anciens liens de paiement du centre d'assistance redirigent vers la plateforme.
  - **Versions** : installation pour tous les clients et **historique des versions** (notes modifiables, paquets téléchargeables).
  - **FAQ partagée** : questions proposées par le chatbot de tous les clients, dès l'enregistrement.
  - **Assistance** : liaison au centre d'assistance par une clé ; l'accès de chaque espace à la conversation en direct est créé automatiquement (nouveau client), coupé à la suspension ou la suppression.
- **Reprise de l'historique du centre d'assistance** en un clic : versions (notes et paquets), vidéos (fichiers compris, désormais communes), FAQ, tarifs, délai de grâce et clé Stripe, puis pour chaque espace sa licence, son abonnement en ligne et ses paiements (client reconnu par sa clé, son adresse ou son nom ; relançable sans doublon). Les copies des vidéos et de la FAQ reçues autrefois par chaque espace sont retirées.
- Espaces de la plateforme : Paramètres → Licence et assistance affiche la licence gérée par NLapps (plus de clé à coller) ; les mises à jour ne s'installent plus depuis un espace.

### Centre d'assistance NLapps 3.5.0
- **Réglages → Console Centriva** : clé de liaison pour la console de la plateforme. Une fois reliée, l'application n'affiche plus que « Console de gestion ↗ » dans le menu : parc clients, versions, FAQ, vidéos, abonnements et réglages de paiement de Centriva ne sont plus gérés ici. Le centre d'assistance garde les conversations, les réponses rapides, l'IA, les comptes et les notifications. « Délier » rétablit les pages.
- API réservée à la console (clé de liaison) : reprise de l'historique, fichiers, création et mise à jour de l'accès de chaque espace.

## 1.26.0
- **Plateforme multi-clients** : un seul Centriva pour tous les clients, chacun avec sa propre base de données, ses fichiers, ses utilisateurs et sa licence.
- **Connexion unique sur centriva.fr** : e-mail et mot de passe, sans identifiant d'espace à connaître ; Centriva retrouve le client du compte et ouvre la session dans son espace (jeton à usage unique signé avec la clé de ce client ; la double authentification du compte reste demandée). Compte présent chez plusieurs clients : choix de l'espace. « Mot de passe oublié » depuis la même page.
- **Comptes super administrateur** (remplacent le mot de passe unique de la console) : nom, e-mail, mot de passe, double authentification ; plusieurs comptes possibles. Ils créent et gèrent les clients, installent les mises à jour pour tous et **publient les vidéos communes**, visibles dans tous les clients (chapitres, vidéo d'accueil des salariés). Connexion depuis la page commune ou la console.
- **Mise en service guidée** : création du premier super administrateur, et les données existantes deviennent le premier client (IMSS) sans être modifiées.

### Centre d'assistance NLapps 3.4.0
- **Nouvelle page « Abonnements »** (menu Administration) : tous les clients de toutes les applications avec leur montant mensuel TTC, leur mode de paiement (prélèvement SEPA, carte, manuel, résilié), la prochaine échéance et l'état de leur licence ; revenu mensuel, clients en paiement automatique, encaissé du mois, impayés et échéances proches ; lien de paiement de chaque client (copie ou envoi par e-mail) et journal de tous les paiements avec les factures. Sans Stripe relié, la page est déjà utile et propose de configurer le paiement en ligne.
- Parc clients : le mode de paiement s'affiche dès qu'il est connu, et le compteur « en paiement automatique » mène à la page Abonnements. Sous-menus des applications dépliés (jusqu'à trois applications).
- Les noms de clients, formules et messages de licence contenant l'ancien nom du logiciel sont renommés.

## 1.25.0
- **Tous les clients à la même adresse** : chaque espace est servi à `centriva.fr/<identifiant>/` (ex. centriva.fr/imss/). L'adresse seule affiche « Accéder à votre espace » : chacun saisit l'identifiant de son établissement, mémorisé sur l'appareil. Les données restent strictement séparées : base, fichiers, comptes, et sessions limitées à leur espace (être connecté chez un client ne donne aucun accès aux autres, même dans le même navigateur). Liens des e-mails et des invitations avec l'espace. Une adresse dédiée par client reste possible. Réécriture d'adresses fournie pour Apache (.htaccess), indiquée pour nginx.
- **Export et import complets** (*Organisation → Export et import*, administrateur) : une archive chiffrée par mot de passe contient toute la base (centres, comptes, catalogue, fournisseurs, demandes, commandes, stock, contrats, budgets, réglages) et, au choix, les fichiers (photos, logo, factures, contrats). À l'import dans un autre Centriva (même version ou plus récente, MySQL ou SQLite) : aperçu du contenu, confirmation, sauvegarde automatique de la base remplacée ; double authentification et mots de passe enregistrés re-chiffrés pour la nouvelle installation ; licence, assistance NLapps et tutoriels vidéo de l'installation de destination conservés.

## 1.24.0
- **Approvia devient Centriva** : nouveau nom partout (écrans, e-mails, bons de commande, assistance, documentation) et nouveau logo (un « C » et une coche, mêmes couleurs), icônes de l'application installée sur téléphone et ordinateur comprises.
- Les installations dont le nom d'application était resté « Approvia » s'affichent automatiquement « Centriva » ; un nom personnalisé est conservé. Aucune donnée, aucun compte ni réglage n'est modifié.

### Centre d'assistance NLapps 3.3.0
- L'application « Approvia » devient « Centriva » dans la console : parc clients, versions, FAQ et vidéos sont rattachés au nouveau nom (tarifs conservés), et les textes de la FAQ, des vidéos, des notes de version et des réponses rapides sont renommés.
- Les installations pas encore mises à jour, qui se présentent encore sous l'ancien nom, sont reconnues : licence, FAQ, vidéos et mise à jour vers Centriva leur parviennent normalement.

## 1.23.0
- **Abonnement payable en ligne** : dans *Paramètres → Licence et assistance*, l'administrateur voit son mode de paiement, la prochaine échéance et le montant mensuel, avec le bouton « Payer en ligne » (ou « Gérer mon abonnement » : moyen de paiement, factures). Le bouton figure aussi dans le bandeau d'échéance et sur la page de licence expirée.

### Centre d'assistance NLapps 3.2.0
- **Encaissement automatique des abonnements** avec Stripe : carte bancaire ou prélèvement SEPA, sur une page de paiement sécurisée propre à chaque client (lien à copier ou à envoyer par e-mail, bouton dans l'application du client). Montant calculé d'après les tarifs de l'application et l'option IA, TVA comprise ; une période déjà réglée est conservée.
- **Chaque paiement reçu prolonge la licence** automatiquement ; échec de paiement et résiliation signalés à l'opérateur. Parc clients : mode de paiement, prochaine échéance, journal des paiements avec les factures, nombre de clients en paiement automatique.
- Réglages → Paiement en ligne : clé Stripe, secret du webhook (signature vérifiée, événements rejoués ignorés), TVA, test de connexion.

## 1.22.0
- **Démarrage guidé d'un nouveau client** : sur le pilotage, l'administrateur suit une liste d'étapes avec barre de progression, cochées automatiquement d'après ses données : retirer les données de démonstration, renseigner l'organisation, créer les centres, ajouter les fournisseurs, importer le catalogue, inviter les salariés, activer les e-mails, relier Centriva à NLapps ; puis, en facultatif, dates limites, budgets et contrats. La prochaine étape est mise en avant, chaque étape ouvre la bonne page. Guide masquable (et réaffichable depuis Paramètres).
- **Inviter des salariés en nombre** (*Comptes → Inviter des salariés*) : collez une liste (adresses seules, « Prénom Nom <adresse> », « Prénom ; Nom ; adresse » ou colonnes Excel), choisissez les centres, le rôle et la fonction. Chaque personne reçoit par e-mail un lien pour choisir son mot de passe, valable 7 jours ; sans e-mails activés, les liens personnels s'affichent pour être transmis (bouton « Copier tous les liens »). Les comptes déjà actifs ne sont pas touchés, les demandes d'accès en attente sont validées.

## 1.21.0
- **Démo publique pour les prospects** : un espace créé dans la console avec « Démo publique » propose sur sa page de connexion quatre profils en un clic (salarié, responsable de centre, acheteur, administrateur), avec un bandeau « Démo » et un bouton « Obtenir Centriva » sur chaque page.
- **Remise à zéro chaque nuit à 3 h** (ou à la demande depuis la console) : toutes les saisies des visiteurs sont effacées et les données de démonstration recréées (contrats compris) ; clé cron, licence et tutoriels vidéo conservés.
- **Actions sensibles neutralisées** sur la démo, avec une explication : mots de passe, double authentification, inscriptions, paramètres, comptes, sauvegardes et nettoyage ; aucun e-mail n'est envoyé ; assistant IA limité à 150 appels par jour.
- Données de démonstration : ajout du compte acheteur (acheteur@demo.fr).

## 1.20.0
- **Plusieurs clients sur un même serveur** : un seul exemplaire du code sert plusieurs clients, chacun avec sa base de données (SQLite ou MySQL), ses fichiers, ses comptes et sa licence, reconnu à son adresse (ex. imss.centriva.fr). Les sessions d'un espace ne valent jamais dans un autre.
- **Console NLapps** (`console.php`, mot de passe propre créé avec un code déposé sur le serveur) : création d'un client en un formulaire (premier administrateur, clé de licence, données de démonstration en option), reprise de l'installation existante comme premier client sans toucher à sa base, chiffres clés par client, suspension et rétablissement, changement d'adresses, suppression avec archive.
- **Mise à jour de tous les clients en une fois** depuis la console : sauvegarde de chaque base et du code, remplacement des fichiers (retour automatique en cas d'échec), migration de chaque base. Dans les espaces clients, le menu « Mises à jour » disparaît.
- `php cron.php` traite tous les clients, chacun dans son processus. L'installation simple (un seul client) fonctionne exactement comme avant.

## 1.19.0
- **Contrats et marchés** (menu *Service achats*) : enregistrez vos marchés de groupement (UniHA, Resah, UGAP, CAIH…) et vos contrats directs : fournisseur, n° de marché, période, montant annuel, interlocuteur, document PDF.
- **Prix contractuels** : saisis article par article, collés depuis l'annexe tarifaire (référence ; prix) ou repris des tarifs négociés actuels. Pendant la durée du contrat ils deviennent le tarif négocié des articles (paniers, bons de commande, comparateur) ; un import de tarifs fournisseur ne peut pas les écraser, et la fiche article indique le contrat qui fixe le prix.
- **Alerte avant l'échéance** : au début du préavis choisi (1 à 6 mois, reconduction tacite prise en compte), puis à l'expiration, le service achats est prévenu (notification et e-mail) ; bandeau sur le pilotage et compteur dans le menu. Prolongation en un clic (6 à 36 mois).
- Liste des contrats avec avancement, filtres (en cours, à renouveler, à venir, expirés), articles couverts et montant annuel ; contrats visibles aussi sur la fiche fournisseur. Accessible aux administrateurs et aux acheteurs.

## 1.18.1
- **Comparaison des droits par rôle** : une bulle « ? » à côté du rôle (fiche d'un compte et colonne « Rôle » de la liste), ainsi qu'un bouton « Droits des rôles » sur la page Comptes, ouvrent un tableau comparant salarié, responsable de centre, acheteur et administrateur : commander et recevoir, service achats, organisation et paramètres. Lisible aussi sur téléphone.

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
- **Mise à jour par paquet ZIP** depuis la console (Réglages → Mise à jour du centre), comme dans Centriva : confirmation par mot de passe, sauvegarde automatique, retour arrière en un clic ; `config.php` et `data/` jamais modifiés. Paquet allégé `nlapps-assistance-maj.zip` (sans la bibliothèque de l'IA) pour les hébergements limités en taille d'envoi.
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
- L'application devient **Centriva** : nouveau logo dans le menu, la page de connexion, l'onglet du navigateur et l'icône de l'application installable.
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
