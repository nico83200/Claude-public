# Centre d'assistance NLapps

Console unique pour gérer toutes les installations de vos applications NLapps (Approvia…) chez vos clients :

- **Conversations en direct** avec les utilisateurs (bulle d'aide de l'application), captures d'écran, réponses rapides, suggestion de réponse par l'IA, suivi « à traiter / en attente / résolue », note de satisfaction ;
- **Parc clients et licences** : abonnement, échéance, option assistant IA, suspension, version installée, usage, revenu mensuel estimé ;
- **Versions** : vous publiez une mise à jour, chaque installation la propose en un clic à son administrateur ;
- **FAQ partagée** : les questions publiées ici enrichissent le chatbot de toutes les installations, sans mise à jour.

Sur ordinateur ou téléphone (console installable, notifications push).

## Installation (5 minutes, sur nlapps.fr)

1. Déposez le contenu du paquet `nlapps-assistance.zip` (dossier `assistance/`) dans le site, par exemple `public_html/assistance` → `https://nlapps.fr/assistance/`.
   PHP 8.1+ avec les extensions SQLite, OpenSSL et Zip (présentes par défaut chez Hostinger). Aucune base MySQL n'est nécessaire.
2. Ouvrez `https://nlapps.fr/assistance/` : à la première visite, choisissez le mot de passe de la console.
   Puis **Réglages → Sécurité → Double authentification** (fortement conseillé).
3. **Parc clients → Nouveau client** pour chaque installation : nom, formule, date « payé jusqu'au », option IA.
   Copiez les deux lignes affichées et collez-les dans Approvia, **Administration → Paramètres → Licence et assistance NLapps**, puis « Enregistrer et tester » :
   ```php
   'support_hub_url' => 'https://nlapps.fr/assistance/api.php',
   'support_hub_key' => 'nlh_…',
   ```
   Cette clé sert à la fois de licence, d'accès aux mises à jour, de FAQ partagée et de conversation en direct.
4. (Facultatif) `config.php`, à partir de `config.sample.php` : e-mail d'alerte, nom affiché, notification via **ntfy**.

**Mettre à jour le centre d'assistance** : remplacez les fichiers par ceux du nouveau paquet en gardant `config.php` et le dossier `data/` (base, versions publiées, images).

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
| Échue (délai de grâce, 15 jours) | Tout fonctionne, bandeau d'avertissement. |
| Expirée | Assistant IA et mises à jour coupés ; l'application reste utilisable. |
| Suspendue (manuel) | Les utilisateurs ne peuvent plus utiliser l'application (page « accès suspendu » avec vos coordonnées) ; l'administrateur garde l'accès aux paramètres, aux mises à jour et à l'assistance. |

« Prolonger » ajoute 1, 3 ou 12 mois. Sans date d'échéance, la licence est permanente. L'installation vérifie sa licence toutes les 6 heures (ou immédiatement via « Vérifier maintenant ») ; une coupure réseau ne bloque jamais l'application.

## Versions

**Versions → Publier** : déposez le paquet de mise à jour (ex. `approvia-1.12.0.zip`, produit par `php tools/build-update.php`). La version et les notes sont lues dans le paquet. Les installations sous licence active la voient dans un bandeau et la page *Mises à jour*, la téléchargent (empreinte SHA-256 vérifiée) et l'installent avec sauvegarde automatique et retour arrière possible. « Retirer » la rend invisible.

## Brancher vos autres applications NLapps

Le dossier `sdk/` contient :
- `NlappsSupport.php` : classe PHP à copier dans l'application (licence, versions, FAQ, conversation, images, notes) ;
- `nlapps-chat.js` : widget de conversation autonome, à inclure dans les pages ;
- `examples/relay.php` : relais serveur entre le widget et ce centre (la clé reste côté serveur).

Créez ensuite un client avec le nom de l'application (champ « Application »), comme pour Approvia.

## Sécurité

- Les applications clientes s'authentifient par leur clé (stockée hachée). « Renouveler la clé » en crée une nouvelle ; l'ancienne reste acceptée 14 jours.
- Le navigateur des utilisateurs ne contacte jamais directement ce centre : c'est le serveur de l'application qui relaie.
- Console : mot de passe haché, double authentification (TOTP), verrouillage 15 minutes après 5 échecs avec alerte.
- Données dans `data/` (protégé par `.htaccess`, base sous un nom aléatoire) ; images contrôlées (JPEG, PNG, WebP, 4 Mo max).
- Tests : `php tests/run.php` (en ligne de commande).
