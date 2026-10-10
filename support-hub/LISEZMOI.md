# Centre d'assistance NLapps

Console unique pour gérer toutes les installations de vos applications NLapps (Centriva…) chez vos clients :

- **Conversations en direct** avec les utilisateurs (bulle d'aide de l'application), captures d'écran, réponses rapides, suggestion de réponse par l'IA, suivi « à traiter / en attente / résolue », note de satisfaction ;
- **Parc clients et licences** : abonnement, échéance, option assistant IA, suspension, version installée, usage, revenu mensuel estimé ;
- **Versions** : vous publiez une mise à jour, chaque installation la propose en un clic à son administrateur ;
- **FAQ partagée** : les questions publiées ici enrichissent le chatbot de toutes les installations, sans mise à jour ;
- **Tutoriels vidéo** : une vidéo publiée ici arrive dans toutes les installations (menu « Tutoriels vidéo ») et le chatbot la propose, au bon chapitre.

Une seule console pour toutes vos applications : les **conversations** sont communes (avec un filtre par application), et chaque application a son **sous-menu** (parc clients, versions, FAQ, vidéos). Même interface sur ordinateur, tablette et téléphone (menu latéral, en tiroir sur petit écran), installable comme une application, avec notifications push. Chaque personne de l'équipe a son **compte** (identifiant + mot de passe, double authentification).

## Installation (5 minutes, sur nlapps.fr)

1. Déposez le contenu du paquet `nlapps-assistance.zip` (dossier `assistance/`) dans le site, par exemple `public_html/assistance` → `https://nlapps.fr/assistance/`.
   PHP 8.1+ avec les extensions SQLite, OpenSSL et Zip (présentes par défaut chez Hostinger). Aucune base MySQL n'est nécessaire.
2. Ouvrez `https://nlapps.fr/assistance/` : à la première visite, créez le compte administrateur (nom, identifiant, mot de passe).
   Puis **Mon compte → Double authentification** (fortement conseillé).
3. **Centriva → Parc clients → Nouveau client** pour chaque installation : nom, formule, date « payé jusqu'au », option IA.
   Copiez les deux lignes affichées et collez-les dans Centriva, **Administration → Paramètres → Licence et assistance NLapps**, puis « Enregistrer et tester » :
   ```php
   'support_hub_url' => 'https://nlapps.fr/assistance/api.php',
   'support_hub_key' => 'nlh_…',
   ```
   Cette clé sert à la fois de licence, d'accès aux mises à jour, de FAQ partagée et de conversation en direct.
4. (Facultatif) `config.php`, à partir de `config.sample.php` : e-mail d'alerte, nom affiché, notification via **ntfy**.

**Mettre à jour le centre d'assistance** : **Administration → Mise à jour**, déposez le paquet (`nlapps-assistance-maj.zip`, ou le paquet complet) et confirmez avec votre mot de passe. Sauvegarde automatique, retour à la version précédente en un clic ; `config.php` et `data/` ne sont jamais touchés. (Pour passer d'une version 2.0 à 2.1, copiez une fois les fichiers par FTP : la page de mise à jour arrive avec la 2.1.)

**Installer la console sur vos appareils** : Réglages → « Installer sur cet appareil » (ordinateur, Android), ou sur iPhone/iPad : Safari → Partager → « Sur l'écran d'accueil ». Elle s'ouvre alors comme une application, avec notifications.

## Comptes et applications (version 3.0)

- **Passage à la 3.0** : l'ancien accès par mot de passe seul devient le compte **admin** — identifiant `admin`, même mot de passe, même double authentification. Changez l'identifiant dans **Mon compte**.
- **Comptes** (administrateurs) : un compte par personne. *Administrateur* : toute la console. *Conseiller* : conversations, FAQ, vidéos et sa disponibilité. Les réponses sont signées du nom de la personne. Mot de passe oublié ou téléphone perdu : un administrateur définit un mot de passe provisoire ou réinitialise la double authentification.
- **Applications** : **Gérer les applications → Ajouter** (nom, couleur, tarifs). L'application obtient son sous-menu ; son identifiant technique est celui que l'application envoie au centre (kit `sdk/`). FAQ et vidéos peuvent être réservées à une application ou partagées avec toutes.
- Sécurité : blocage 15 minutes après 5 échecs de connexion depuis une même adresse, avec alerte.

## Au quotidien

- **Disponibilité** : bouton Disponible / Absent en haut, ou **horaires d'ouverture** (Réglages) : disponible automatiquement pendant vos plages, absent en dehors. Un clic sur le bouton repasse en mode manuel.
- **Conversations** : onglets *À traiter*, *En attente* (vous attendez l'utilisateur), *Résolues*. « Résoudre » clôt la conversation, envoie la transcription par e-mail à l'utilisateur (case cochée) et lui propose de noter l'échange (1 à 5 étoiles, visibles dans la console).
- **Réponses rapides** (Réglages) : textes types insérés en un clic, `{prenom}` remplacé par le prénom.
- **✨ Suggérer une réponse** : l'IA rédige une proposition (conversation, contexte technique, FAQ, réponses types) que vous relisez avant d'envoyer. Clé API Claude dans Réglages.
- **Images** : l'utilisateur envoie une capture d'écran depuis la bulle (bouton appareil photo) ; vous pouvez joindre une image (bouton 📎, ou coller une capture dans la zone de réponse).
- **＋ FAQ** depuis une conversation : prépare une question de la FAQ partagée avec votre réponse.
- **Notifications sur le téléphone** : ouvrez la console sur le téléphone, « Ajouter à l'écran d'accueil », puis Réglages → Notifications → « Activer sur cet appareil ». Aussi : e-mail et ntfy (au plus une alerte toutes les 3 minutes par conversation).

## Version 4.0 : le chat seulement

Le centre d'assistance ne gère plus ni licences, ni abonnements, ni paiements, ni versions, ni vidéos, ni FAQ : tout cela est centralisé dans la console de la plateforme Centriva (super administrateurs). Il garde les conversations, les réponses rapides, la suggestion IA, les comptes, les notifications et la page **Accès au chat** (une clé par client ; celles des espaces Centriva sont créées par la console).

## Console Centriva (version 3.5)

Centriva est désormais géré par la console de sa plateforme (centriva.fr/console.php) : clients, licences, abonnements Stripe, versions, vidéos et FAQ. Dans **Réglages → Console Centriva**, créez la clé de liaison et collez-la dans la console (menu Assistance, avec l'adresse de `api.php`), puis lancez « Reprendre l'historique ». Les pages de gestion de Centriva disparaissent alors d'ici ; seules les conversations restent. Pensez à remplacer, dans Stripe, l'adresse du webhook par celle indiquée dans la console (Abonnements).

## Brancher vos autres applications NLapps

Le dossier `sdk/` contient **`INTEGRATION.md`**, le guide complet du protocole (à donner tel quel à un développeur ou à une IA de développement), et :
- `NlappsSupport.php` : classe PHP à copier dans l'application (licence, versions, FAQ, conversation, images, notes) ;
- `nlapps-chat.js` : widget de conversation autonome, à inclure dans les pages ;
- `examples/relay.php` : relais serveur entre le widget et ce centre (la clé reste côté serveur).

Créez ensuite un client avec le nom de l'application (champ « Application »), comme pour Centriva.

## Sécurité

- Les applications clientes s'authentifient par leur clé (stockée hachée). « Renouveler la clé » en crée une nouvelle ; l'ancienne reste acceptée 14 jours.
- Le navigateur des utilisateurs ne contacte jamais directement ce centre : c'est le serveur de l'application qui relaie.
- Console : comptes nominatifs (identifiant + mot de passe haché), double authentification (TOTP) par compte, rôles administrateur / conseiller, blocage 15 minutes après 5 échecs avec alerte.
- Données dans `data/` (protégé par `.htaccess`, base sous un nom aléatoire) ; images contrôlées (JPEG, PNG, WebP, 4 Mo max).
- Tests : `php tests/run.php` (en ligne de commande).
