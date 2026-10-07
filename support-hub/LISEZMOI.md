# Centre d'assistance NLapps

Console unique pour répondre en direct aux utilisateurs de toutes les applications NLapps installées chez vos clients (Approvia…).
Les utilisateurs écrivent depuis la bulle d'aide de l'application ; vous répondez depuis cette console, sur ordinateur ou téléphone.

## Installation (5 minutes, sur nlapps.fr)

1. Déposez le contenu de ce dossier dans un sous-dossier du site, par exemple `public_html/assistance` → `https://nlapps.fr/assistance/`.
   PHP 8.1+ avec l'extension SQLite (présente par défaut chez Hostinger). Aucune base MySQL n'est nécessaire.
2. Ouvrez `https://nlapps.fr/assistance/` : à la première visite, choisissez le mot de passe de la console.
3. *Applications clientes* → **Créer une clé** pour chaque installation cliente. Copiez les deux lignes affichées et collez-les dans
   Approvia, **Administration → Paramètres → Assistance NLapps**, puis « Enregistrer et tester »
   (ou, au choix, dans le `config.php` de l'installation) :
   ```php
   'support_hub_url' => 'https://nlapps.fr/assistance/api.php',
   'support_hub_key' => 'nlh_…',
   ```
   Le bouton « Parler à un conseiller » apparaît alors dans la bulle d'aide de cette installation.
4. (Facultatif) `config.php` du centre d'assistance, à partir de `config.sample.php` : e-mail d'alerte, nom affiché,
   notification instantanée sur votre téléphone via l'application gratuite **ntfy** (`'ntfy_url' => 'https://ntfy.sh/votre-sujet-secret'`).

## Au quotidien

- **Disponible / Absent** (en haut) : quand vous êtes absent, les utilisateurs voient votre message d'absence ; leurs messages sont gardés.
- Alertes : e-mail (et ntfy si configuré) à chaque nouvelle conversation ou nouveau message, au plus une alerte toutes les 3 minutes par conversation.
  La console ouverte sonne et affiche une notification du navigateur.
- Chaque conversation montre l'échange préalable avec le chatbot et le contexte (client, centre, version, page).
- Si l'utilisateur a fermé la fenêtre, votre réponse lui est signalée dans l'application (notification et e-mail selon ses réglages).

## Sécurité

- Les applications clientes s'authentifient par leur clé (stockée hachée) ; une clé peut être désactivée à tout moment.
- Le navigateur des utilisateurs ne contacte jamais directement ce centre : c'est le serveur de l'application qui relaie.
- Données dans `data/` (protégé par `.htaccess`, base sous un nom aléatoire). Mot de passe de la console haché, blocage après 5 essais.
