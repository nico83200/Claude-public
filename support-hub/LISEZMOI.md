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

## Licences

| État | Effet dans l'application du client |
|---|---|
| Active | Tout fonctionne. Bandeau d'information pour l'administrateur 15 jours avant l'échéance. |
| Échue, délai de grâce (si vous en réglez un dans Réglages → Licences) | Tout fonctionne, bandeau d'avertissement avec la date de coupure. |
| Expirée (le lendemain de l'échéance, ou après le délai de grâce) | **Accès coupé immédiatement** : tous les utilisateurs sont déconnectés et ne peuvent plus se connecter (page « Licence expirée » avec vos coordonnées). Les données sont conservées. |
| Suspendue (manuel) | Même effet, à tout moment. |

« Prolonger » ajoute 1, 3 ou 12 mois ; l'accès revient dès que le client clique sur « Vérifier à nouveau » (ou dans les 10 minutes). Sans date d'échéance, la licence est permanente. L'installation vérifie sa licence toutes les 10 minutes et contrôle aussi elle-même la date d'échéance ; une coupure réseau ne coupe pas l'accès d'une licence à jour.

## Paiement en ligne des abonnements (version 3.2)

Vos clients règlent leur abonnement par **carte bancaire** ou **prélèvement SEPA**, sur une page sécurisée par Stripe : NLapps ne voit jamais les numéros de carte ni les IBAN.

1. Créez un compte Stripe et activez le prélèvement SEPA (Paramètres → Moyens de paiement) et le portail client (Paramètres → Facturation → Portail client).
2. Dans **Réglages → Paiement en ligne** : collez la clé secrète (`sk_live_…`, ou `sk_test_…` pour essayer), puis déclarez le webhook indiqué (`…/api.php?a=stripe`) dans Stripe et collez son secret (`whsec_…`). Taux de TVA : 20 % par défaut. « Tester la connexion » vérifie la clé.
3. Chaque client a un **lien de paiement personnel** (Parc clients → Gérer → Paiement en ligne), à copier ou à envoyer par e-mail. L'administrateur du client trouve aussi le bouton **« Payer en ligne » / « Gérer mon abonnement »** dans Centriva (Paramètres → Licence), et dans les bandeaux d'échéance.

Le montant mensuel est calculé automatiquement : tarif de l'application + option IA si elle est cochée, plus la TVA. Une période déjà réglée est conservée : le premier prélèvement a lieu à son échéance.

**Chaque paiement reçu prolonge la licence** jusqu'à la fin de la période payée. Un échec de paiement vous est signalé (notification + e-mail) et Stripe relance automatiquement ; sans régularisation, la licence échoit comme d'habitude (délai de grâce, puis coupure). Une résiliation arrête les paiements, la licence court jusqu'à la fin de la période payée. Le parc clients indique pour chacun le mode de paiement et la prochaine échéance, avec le journal des paiements et le lien vers chaque facture.

## Versions

**Versions → Publier** : déposez le paquet de mise à jour (ex. `centriva-1.12.0.zip`, produit par `php tools/build-update.php`). La version et les notes sont lues dans le paquet. Les installations sous licence active la voient dans un bandeau et la page *Mises à jour*, la téléchargent (empreinte SHA-256 vérifiée) et l'installent avec sauvegarde automatique et retour arrière possible. « Retirer » la rend invisible.

## Tutoriels vidéo

**Vidéos → Publier une vidéo** : déposez le fichier MP4 (H.264), un titre, des mots-clés et les chapitres, un par ligne :

```
0:00 Se connecter et se repérer | connexion menu centre
4:12 Réceptionner une livraison | réception colis livré scanner
```

Les installations reçoivent la liste à leur prochaine vérification de licence (moins de 10 minutes) et téléchargent la vidéo en arrière-plan (empreinte vérifiée). Le chatbot propose ensuite la vidéo et ouvre directement le chapitre qui répond à la question. Les chapitres et mots-clés se modifient à tout moment sans renvoyer la vidéo. Cochez **« Vidéo d'accueil des salariés »** pour qu'elle s'ouvre en fenêtre à la connexion des salariés de chaque installation (jusqu'à ce qu'ils cochent « Ne plus afficher »). Taille d'envoi : limitée par `upload_max_filesize` et `post_max_size` (à augmenter chez l'hébergeur au besoin).

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
