# Commandes Centres

Progiciel interne de **commandes pour un groupe de centres de santé** : les salariés (secrétaires, médecins, kinés, infirmiers…) font leurs demandes par centre, le service achats les regroupe par fournisseur, émet les bons de commande et suit les livraisons jusqu'à la réception sur site.

## Fonctionnalités

### Espace salarié (navigation par centre)
- **Sélecteur de centre** : un compte peut être validé sur un ou plusieurs sites ; chaque demande est rattachée à un seul centre.
- **Tableau de bord** : message d'accueil, alerte et **compte à rebours des dates limites de commande**, livraisons à réceptionner, favoris et articles les plus demandés, dernières demandes du centre.
- **Recherche assistée** :
  - recherche intelligente locale, sans configuration : insensible aux accents et aux pluriels, tolérante aux fautes de frappe (« gans nitril » → gants nitrile), synonymes du métier (fièvre → thermomètre, strapping → taping…), suggestions instantanées pendant la frappe, classement selon les habitudes du centre ;
  - **assistant IA (Claude)** en option : comprend les besoins en langage naturel (« de quoi nettoyer la table de massage ») et propose les articles adaptés avec un conseil et des recherches complémentaires.
- Catalogue avec photos, descriptif, filtres par catégorie et fournisseur, **favoris**, prix négocié affiché (masquable).
- **Panier** classé par fournisseur, précision par ligne (taille, couleur…), commentaire et indicateur **urgent**.
- **Suivi des demandes** (les miennes ou tout le centre) : en attente → à commander → commandée → reçue ; annulation d'une ligne tant qu'elle n'est pas traitée ; bouton « Recommander ».
- **Réceptions** : cocher tout ou partie d'un bon, ou saisir la quantité réellement livrée ; le statut passe automatiquement à « Reçu partiellement » ou « Reçu ». Les noms des demandeurs sont indiqués pour savoir à qui remettre les articles.

- **Inventaire** du centre : le stock augmente automatiquement à chaque réception ; on le corrige par un **inventaire** (quantité comptée) ou une **sortie** déclarée (consommation, périmé…). Seuils d'alerte, articles en commande, bouton « recommander » sur les stocks bas, historique de tous les mouvements.
- **Scan de codes-barres** avec la caméra du smartphone ou de la tablette : recherche d'un article, inventaire (la ligne de l'article s'affiche directement), sortie de stock. Les douchettes USB/Bluetooth fonctionnent aussi (saisie dans la recherche).
- **Articles hors catalogue** : depuis le panier, ou automatiquement quand un code-barres scanné est inconnu, le salarié propose un article avec le plus d'informations possible (nom, marque, référence, conditionnement, fournisseur connu, lien, prix constaté, **photo prise avec la caméra**, quantité souhaitée). La proposition part avec sa demande ; son avancement est visible dans « Suivi des demandes → Mes articles proposés ».
- **Notifications** dans l'application (cloche) et, en option, par e-mail à chaque étape : demande validée, commandée, livrée, refusée, stock bas. Chacun choisit ses notifications dans « Mon profil ».

- **Listes types** : « kit salle de soins », « commande mensuelle accueil »… ajoutées au panier en un clic, quantités modifiables. Le service achats partage des listes ; chacun peut enregistrer son panier comme liste personnelle.
- **Réapprovisionnement suggéré** : les stocks bas non couverts par une commande sont proposés dans le panier avec une quantité calculée.
- **Mode réserve (tablette)** : écran plein écran à gros boutons. On scanne, puis on fait une sortie, une entrée ou un inventaire en deux gestes.
- **Application installable** sur l'écran d'accueil du téléphone ou de la tablette (bouton « Installer »), avec une page hors ligne.
- **Mot de passe oublié** : lien de réinitialisation par e-mail, valable une heure et utilisable une seule fois.
- **Rappels automatiques** la veille d'une date limite, et relance des livraisons en retard.

### Responsable de centre
- Rôle intermédiaire entre salarié et administrateur. Au-delà d'un montant paramétrable, les demandes de son centre attendent sa **validation** avant de partir au service achats. Il peut ajuster les quantités, valider ou refuser avec un message, et voit le budget du centre au moment de décider.

### Espace administrateur (service achats)
- **Pilotage** : indicateurs, dépenses mensuelles, répartition par centre et par fournisseur, **économies réalisées grâce aux tarifs négociés**, livraisons en retard, comptes à valider.
- **Demandes à traiter** : regroupées par fournisseur puis par centre, avec une **jauge de progression vers le minimum de commande** et le franco de port. Sélection des lignes, refus motivé d'une ligne, création du bon de commande.
- **Bons de commande** : statuts **À commander → Commandé → Reçu partiellement / Reçu** (ou Annulé). Modification des quantités et des prix avant commande, ajout d'articles, intégration des nouvelles demandes, frais de port calculés selon le franco, référence de commande du fournisseur, date de livraison prévue, historique complet, **impression / PDF**, export CSV, e-mail pré-rempli au fournisseur.
- **Fournisseurs** : coordonnées, n° client, **minimum de commande**, frais de port, franco, délai, mode de commande, couleur ; disponible pour **tous les centres ou certains seulement**.
- **Articles** : photo optionnelle, descriptif, conditionnement, **tarif catalogue et tarif négocié**, TVA, mots-clés de recherche, duplication, **import/export CSV** du catalogue.
- **Catégories**, **centres** (adresse et consignes de livraison reprises sur les bons), **comptes** (validation des inscriptions, centres autorisés, rôle, réinitialisation du mot de passe).
- **Dates limites de commande** : par fournisseur et/ou par centre, avec répétition (hebdomadaire, toutes les deux semaines, mensuelle), affichées dans les tableaux de bord des centres concernés et dans le catalogue.
- **Articles proposés** : fiche pré-remplie avec les informations du salarié, fournisseur reconnu automatiquement, articles ressemblants du catalogue pour éviter les doublons. Trois actions :
  - **ajouter au catalogue** : la quantité demandée rejoint alors « Demandes à traiter » ;
  - **rattacher à un article existant** : le code-barres scanné complète la fiche s'il manquait ;
  - **refuser** avec un motif.

  Le salarié est notifié dans tous les cas.
- **Comparateur fournisseurs** : les articles de même code-barres ou de même « groupe d'équivalence » sont comparés, avec le surcoût payé sur 12 mois. Sur les demandes à traiter, un bouton bascule une ligne (ou toutes) vers l'équivalent le moins cher.
- **Historique des prix** de chaque article, avec alerte en cas de hausse (fiche article, import CSV) et liste des hausses récentes sur le pilotage.
- **Commandes groupées multi-centres** : une seule commande chez le fournisseur, mais un bon par centre (chaque commande reste attachée à un seul centre). Le minimum et le franco sont appréciés sur le total.
- **Envoi du bon de commande en PDF** au fournisseur par e-mail (copie possible), avec passage automatique en « Commandé ». Le PDF est généré par l'application, sans dépendance.
- **Rapprochement des factures** : numéro, date, montant et justificatif (PDF ou photo), comparés aux marchandises réellement reçues, avec détection des écarts.
- **Exports comptables CSV** : détail des lignes (HT, TVA, TTC, n° de facture), dépenses par centre, fournisseur, catégorie, mois ou centre × mois, et rapprochement des factures.
- **Journal d'audit** : qui a modifié un prix, un budget, un compte, un statut de bon, un paramètre ou installé une mise à jour.
- **Budgets annuels par centre**, avec un seuil d'alerte. La jauge distingue le commandé (bons passés) de l'engagé (bons à commander) ; elle apparaît sur les tableaux de bord, le panier et les demandes à traiter (avertissement en cas de dépassement).
- **Stocks des centres** : vue consolidée, articles sous le seuil, plus fortes consommations.
- **Notifications & e-mails, au cas par cas** : un interrupteur général pour les e-mails. Pour chaque situation, la notification dans l'application et l'e-mail se règlent séparément : demande, validation, commande, livraison, refus, retard, rappel, stock bas, budget, hausse de prix, mot de passe oublié, envoi des bons aux fournisseurs, copie à l'expéditeur. L'envoi se fait par la fonction mail() de l'hébergement ou par SMTP (OVH, Office 365, Gmail…), avec un e-mail de test.
- **Clé API de l'assistant IA** saisie, remplacée ou supprimée depuis les paramètres. Elle est chiffrée en base avec une clé propre à l'installation (`storage/secret.key`, à conserver avec les sauvegardes), affichée masquée et tracée dans le journal d'audit. Le mot de passe SMTP est chiffré de la même façon.
- **Mises à jour depuis l'interface** : envoi d'un paquet .zip, analyse puis confirmation par mot de passe, sauvegarde automatique (code et base) avant installation, puis **retour à la version précédente en un clic** (avec ou sans restauration des données).
- **Logo de l'entreprise** : un PNG à fond transparent, de préférence, envoyé dans les Paramètres. Il s'affiche dans le menu, sur la page de connexion, sur les bons de commande (PDF et impression) et dans les e-mails. Une version JPEG sur fond blanc est préparée automatiquement pour les PDF.
- **Fiche centre complète** :
  - raison sociale ;
  - personne à contacter, e-mail et téléphone ;
  - adresse de livraison avec complément (bâtiment, quai) et consignes ;
  - adresse de facturation identique ou distincte, avec e-mail de comptabilité et mentions ;
  - SIREN, SIRET, FINESS et TVA intracommunautaire.

  Les numéros sont contrôlés (clé de Luhn, cohérence SIREN / SIRET / TVA). Le SIREN et la TVA se déduisent automatiquement du SIRET. Ces informations sont reprises sur les bons de commande.
- **Paramètres** : nom, raison sociale et facturation pour les bons, affichage des prix, inscriptions ouvertes, assistant IA (activation, modèle, test).

## Choix techniques

Je recommande votre **hébergement web avec base SQL** plutôt que WordPress :

- l'application est autonome : PHP 8.1+ et MySQL/MariaDB, sans framework, et elle fonctionne sur un hébergement mutualisé classique ;
- un outil métier (statuts, droits par centre, bons de commande) cadre mal avec les contenus de WordPress, qui ajouterait des mises à jour et des extensions à surveiller sans rien apporter ici ;
- la sécurité est intégrée : mots de passe chiffrés (bcrypt), protection CSRF, requêtes préparées, contrôle d'accès par centre, envoi des photos contrôlé et dossiers sensibles bloqués par `.htaccess`.

SQLite est aussi pris en charge, pour les tests ou une petite structure sans serveur SQL.

## Installation

Le plus simple est d'utiliser le **paquet d'installation complet** (`commandes-centres-<version>-installation.zip`, fabriqué par `php tools/build-update.php --install`). Il contient déjà les dépendances de l'assistant IA (`vendor/`) : aucun Composer ni ligne de commande n'est nécessaire.

1. Créez une base MySQL/MariaDB et son utilisateur chez l'hébergeur.
2. Déposez le .zip à la racine du site, puis décompressez-le, par exemple avec le gestionnaire de fichiers de l'hébergeur.
3. Ouvrez `https://…/install.php`. L'assistant :
   - vérifie l'hébergement (version PHP, extensions, droits d'écriture) ;
   - demande les accès à la base, teste la connexion et écrit `config.php` ;
   - crée le compte administrateur, avec des données de démonstration si vous le souhaitez ;
   - **se supprime de lui-même** à la fin. Si l'hébergement l'en empêche, supprimez `install.php` à la main.
4. **Assistant IA (optionnel)** : créez une clé API sur console.anthropic.com et collez-la dans *Paramètres → Assistant de recherche IA*. Elle y est chiffrée et n'est jamais réaffichée en clair. Une clé dans `config.php` (`anthropic_api_key`) reste possible ; celle des paramètres est prioritaire. Sans clé, la recherche intelligente locale fonctionne seule.
5. Programmez la tâche planifiée `cron.php` toutes les 5 à 15 minutes (voir plus bas).

Depuis les sources du dépôt, lancez d'abord `composer install --no-dev` pour l'assistant IA.

### Hostinger, pas à pas

1. **Site** : dans *hPanel → Domaines → Sous-domaines*, créez par exemple `commandes.votre-groupe.fr`. Le dossier du site est alors `public_html/commandes` ou `domains/…/public_html`.
2. **HTTPS** : dans *Sécurité → SSL*, installez le certificat gratuit. La caméra (scanner) en a besoin.
3. **PHP** : dans *Avancé → Configuration PHP*, choisissez PHP 8.2 ou 8.3. Les extensions requises (pdo_mysql, mbstring, gd, zip, sodium, curl) sont actives par défaut ; l'assistant d'installation le vérifie.
4. **Base** : dans *Bases de données → Gestion*, créez la base, l'utilisateur et le mot de passe. Notez les noms complets, préfixés (`u123456789_…`). Le serveur est `localhost`.
5. **Fichiers** : dans *Fichiers → Gestionnaire de fichiers*, ouvrez le dossier du site et envoyez le .zip. Clic droit → *Extraire* dans le dossier courant, puis supprimez le .zip.
6. Ouvrez `https://commandes.votre-groupe.fr/install.php` et suivez l'assistant.
7. **Tâche planifiée** : dans *Avancé → Tâches Cron*, choisissez « PHP », la commande `domains/votre-groupe.fr/public_html/commandes/cron.php` (adaptez le chemin) et une fréquence de 10 minutes. Sans cron, les tâches tournent quand même après chaque visite, mais moins régulièrement.
8. **E-mails** : créez une adresse (*E-mails → Comptes de messagerie*, par ex. `achats@votre-groupe.fr`). Dans *Paramètres → E-mails* de l'application, choisissez SMTP avec le serveur `smtp.hostinger.com`, le port 465 en SSL et cette adresse comme identifiant. Envoyez-vous l'e-mail de test.
9. **Logo et centres** : chargez votre logo dans *Paramètres → Général*, puis complétez les fiches centres.

Données de démonstration : comptes salariés `claire.secretaire@demo.fr`, `dr.morel@demo.fr`, `lea.kine@demo.fr`, `nadia.idec@demo.fr` et `marc.accueil@demo.fr`, tous avec le mot de passe `demo1234`.

**Caméra** : l'accès à la caméra n'est autorisé par les navigateurs qu'en **HTTPS** (certificat Let's Encrypt gratuit chez la plupart des hébergeurs). Le scanner utilise le détecteur natif du navigateur s'il existe (Chrome/Android), sinon la bibliothèque ZXing livrée avec l'application (iPhone/iPad). Il n'y a aucune dépendance externe.

### Format d'import CSV des articles
Séparateur `;` (ou `,`), encodage UTF-8, première ligne d'en-têtes :
`fournisseur;reference;designation;description;categorie;conditionnement;prix_catalogue;prix_negocie;mots_cles;tva;code_barre`
Un article existant (même fournisseur et même référence) est mis à jour. L'export du catalogue sert de modèle.

## Tâches planifiées

Les e-mails, rappels, relances, la sauvegarde quotidienne et le nettoyage sont exécutés :
- **automatiquement pendant l'utilisation** de l'application (option activée par défaut, rien à configurer) ;
- ou, plus régulièrement, par une **tâche cron** chez l'hébergeur, toutes les 5 à 15 minutes : `php /chemin/cron.php`, ou l'appel de l'URL `https://…/cron.php?key=CLÉ`. La commande exacte et la clé sont affichées dans *Paramètres → Tâches planifiées*.

Les e-mails passent par une file d'attente : un serveur de messagerie momentanément indisponible ne bloque pas l'application, et l'envoi est retenté jusqu'à 5 fois.

## Tests automatiques

`php tests/run.php` rejoue 67 vérifications des règles métier sur une base temporaire :
- recherche ;
- validation par le responsable, commande, réception et stock ;
- factures, budgets, comparateur et commandes groupées ;
- PDF ;
- sécurité ;
- file d'e-mails et tâches planifiées ;
- sauvegarde et restauration.

À lancer avant de fabriquer un paquet de mise à jour.

## Mises à jour du progiciel

1. **Fabriquer le paquet** (sur le poste de développement) : `php tools/build-update.php` crée `dist/commandes-centres-<version>.zip` à partir du fichier `VERSION` et des notes de `CHANGELOG.md`. Avec `--vendor`, les dépendances sont incluses (paquet plus lourd).
2. **Installer** : *Mises à jour → Installer une mise à jour*. Le paquet est d'abord analysé (version, notes, fichiers) ; l'installation ne démarre qu'après confirmation par mot de passe.
3. **Pendant l'installation**, le progiciel :
   - sauvegarde automatiquement le code et la base de données dans `storage/backups/` ;
   - passe quelques secondes en maintenance, le temps d'écrire les fichiers ;
   - met la base à jour au chargement suivant ;
   - restaure lui-même l'ancienne version si l'écriture échoue.
4. **Revenir en arrière** : bouton *Restaurer* sur une sauvegarde. Par défaut, seul le code est restauré et les données sont conservées. En effet, les évolutions de la base sont uniquement additives (nouvelles tables ou colonnes) et l'ancien code fonctionne avec la nouvelle base. La restauration de la base de données est proposée en option. Chaque retour arrière crée d'abord une sauvegarde de l'état actuel, il est donc lui-même annulable.

Garde-fous : seuls les dossiers du code peuvent être modifiés (`app/`, `assets/`, `vendor/`, `tools/`, fichiers racine). `config.php`, les photos, les données et les chemins de type `../` sont systématiquement ignorés.

## Assistant IA : fonctionnement et coûts

- Envoyé à l'IA : la demande du salarié et le catalogue du centre (noms, catégories, mots-clés, descriptifs). **Aucune donnée patient ni personnelle n'est transmise.**
- Le modèle par défaut est Claude Opus 5.5 avec un effort de raisonnement faible, pour des réponses rapides. Claude Sonnet 5.5 et Claude Haiku 4.5 sont proposés dans les paramètres, pour un coût plus faible.
- Le catalogue est mis en cache côté API (*prompt caching*) et les réponses sont conservées 7 jours en base. Une même recherche ne coûte donc qu'une fois.
- Si le modèle décline une requête, l'API bascule automatiquement sur un modèle de repli (`fallbacks: "default"`). Si l'IA est indisponible, les résultats de la recherche locale restent affichés.

## Structure

```
index.php            routeur (index.php?r=…)
install.php          assistant d'installation (à supprimer après usage)
config.sample.php    modèle de configuration
app/                 code (protégé par .htaccess)
  controllers/       écrans salarié et administrateur
  views/             gabarits HTML
  domain.php         règles métier (panier, bons, réceptions, statistiques)
  search.php         moteur de recherche local + assistant IA
  stock.php          inventaire et budgets
  notify.php         notifications (application, e-mail, SMTP)
  updater.php        mises à jour, sauvegardes, retour arrière
  features.php       audit, sécurité, prix, équivalences, listes types, validations, groupes, factures
  pdf.php            générateur PDF des bons de commande
  cron.php           tâches planifiées (e-mails, rappels, sauvegardes)
  schema.php         schéma de base (MySQL / SQLite)
assets/              CSS / JS (+ ZXing pour le scanner, licence Apache 2.0)
tools/               fabrication des paquets de mise à jour
tests/               tests automatiques (php tests/run.php)
cron.php             point d'entrée des tâches planifiées
sw.js, manifest.webmanifest, offline.html   application installable
VERSION, CHANGELOG.md
uploads/products/    photos des articles (exécution de scripts interdite)
```

## Pistes d'évolution

- Budgets par catégorie en plus du budget par centre.
- Seuils d'alerte calculés automatiquement à partir des consommations réelles.
- Connexion directe aux portails de commande des fournisseurs (EDI / API) quand ils le proposent.
- Validation hiérarchique (un responsable de centre valide les demandes avant le service achats).
- Gestion de stock simplifiée et seuils de réapprovisionnement.
- Rapprochement avec les factures et export comptable.
- Envoi automatique du bon de commande en PDF au fournisseur.
