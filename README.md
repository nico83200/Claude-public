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
- **Budgets annuels par centre**, avec un seuil d'alerte. La jauge distingue le commandé (bons passés) de l'engagé (bons à commander) ; elle apparaît sur les tableaux de bord, le panier et les demandes à traiter (avertissement en cas de dépassement).
- **Stocks des centres** : vue consolidée, articles sous le seuil, plus fortes consommations.
- **Notifications & e-mails** : activation par événement, envoi par la fonction mail() de l'hébergement ou par SMTP (OVH, Office 365, Gmail…), e-mail de test.
- **Mises à jour depuis l'interface** : envoi d'un paquet .zip, analyse puis confirmation par mot de passe, sauvegarde automatique (code et base) avant installation, puis **retour à la version précédente en un clic** (avec ou sans restauration des données).
- **Paramètres** : nom, raison sociale et facturation pour les bons, affichage des prix, inscriptions ouvertes, assistant IA (activation, modèle, test).

## Choix techniques

Je recommande votre **hébergement web avec base SQL** plutôt que WordPress :

- l'application est autonome : PHP 8.1+ et MySQL/MariaDB, sans framework, et elle fonctionne sur un hébergement mutualisé classique ;
- un outil métier (statuts, droits par centre, bons de commande) cadre mal avec les contenus de WordPress, qui ajouterait des mises à jour et des extensions à surveiller sans rien apporter ici ;
- la sécurité est intégrée : mots de passe chiffrés (bcrypt), protection CSRF, requêtes préparées, contrôle d'accès par centre, envoi des photos contrôlé et dossiers sensibles bloqués par `.htaccess`.

SQLite est aussi pris en charge, pour les tests ou une petite structure sans serveur SQL.

## Installation

1. Déposez les fichiers sur l'hébergement (FTP), par exemple dans un sous-domaine `commandes.votre-groupe.fr`.
2. Créez une base MySQL/MariaDB (utf8mb4), puis copiez `config.sample.php` en `config.php` et renseignez les accès.
3. **Assistant IA (optionnel)** : sur votre poste, lancez `composer install --no-dev` puis envoyez le dossier `vendor/` sur le serveur. Créez une clé API sur console.anthropic.com et indiquez-la dans `config.php` (`anthropic_api_key`). Sans clé, la recherche intelligente locale fonctionne seule.
4. Ouvrez `https://…/install.php` : créez le compte administrateur (et, si vous le souhaitez, chargez les données de démonstration).
5. **Supprimez `install.php`** du serveur.
6. Vérifiez que le dossier `uploads/products/` est accessible en écriture (photos).

Données de démonstration : comptes salariés `claire.secretaire@demo.fr`, `dr.morel@demo.fr`, `lea.kine@demo.fr`, `nadia.idec@demo.fr` et `marc.accueil@demo.fr`, tous avec le mot de passe `demo1234`.

**Caméra** : l'accès à la caméra n'est autorisé par les navigateurs qu'en **HTTPS** (certificat Let's Encrypt gratuit chez la plupart des hébergeurs). Le scanner utilise le détecteur natif du navigateur s'il existe (Chrome/Android), sinon la bibliothèque ZXing livrée avec l'application (iPhone/iPad). Il n'y a aucune dépendance externe.

### Format d'import CSV des articles
Séparateur `;` (ou `,`), encodage UTF-8, première ligne d'en-têtes :
`fournisseur;reference;designation;description;categorie;conditionnement;prix_catalogue;prix_negocie;mots_cles;tva;code_barre`
Un article existant (même fournisseur et même référence) est mis à jour. L'export du catalogue sert de modèle.

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
  schema.php         schéma de base (MySQL / SQLite)
assets/              CSS / JS (+ ZXing pour le scanner, licence Apache 2.0)
tools/               fabrication des paquets de mise à jour
VERSION, CHANGELOG.md
uploads/products/    photos des articles (exécution de scripts interdite)
```

## Pistes d'évolution

- Rappel automatique la veille d'une date limite (tâche planifiée cron).
- Budgets par catégorie en plus du budget par centre.
- Suggestion automatique de réapprovisionnement à partir des consommations.
- Validation hiérarchique (un responsable de centre valide les demandes avant le service achats).
- Gestion de stock simplifiée et seuils de réapprovisionnement.
- Rapprochement avec les factures et export comptable.
- Envoi automatique du bon de commande en PDF au fournisseur.
