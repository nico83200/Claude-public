# Déménagement de l'application : jackcie.nlapps.fr → app.jackcie.fr

La base de données ne change pas. L'ancienne adresse doit **rester en service pour toujours** et rediriger vers la
nouvelle : des QR codes sont imprimés (fiches d'urgence collées sur les box, affiches EquitaLyon), des liens de
résumé vétérinaire et de parrainage circulent, des agendas sont abonnés au flux `.ics`.

L'application sait déjà rediriger elle-même : toute visite sur une adresse listée dans `LEGACY_HOSTS`
(par défaut `jackcie.nlapps.fr`) est renvoyée en 301 vers `APP_URL`, même chemin et mêmes paramètres.
Le webhook Stripe est traité sur place (Stripe ne suit pas les redirections). Tant que `APP_URL` vaut encore
l'ancienne adresse, rien ne change.

## 0. Avant (la veille)
- Administration › Messages : prévenir les utilisateurs. Après le changement, il faudra **réinstaller l'application
  sur l'écran d'accueil** depuis app.jackcie.fr et **réactiver les notifications** (une application installée et ses
  notifications sont liées à une adresse ; c'est une règle des navigateurs).
- Faire une sauvegarde (Administration › Mises à jour, ou hPanel › Sauvegardes).

## 1. Créer app.jackcie.fr chez Hostinger
hPanel › Sites web › jackcie.fr › Sous-domaines › créer `app`, puis activer le **SSL** (hPanel › Sécurité › SSL)
pour app.jackcie.fr et attendre qu'il soit actif.

## 2. Faire servir l'application par app.jackcie.fr — deux options

**Option A (recommandée, aucune copie de fichiers, nécessite l'accès SSH)** : le dossier du sous-domaine devient un
lien vers le dossier actuel de l'application. Les deux adresses servent la même application ; l'ancienne redirige.
```sh
cd ~/domains/jackcie.fr/public_html            # ou le dossier indiqué par hPanel pour le sous-domaine
rm -rf app && ln -s ~/domains/jackcie.nlapps.fr/public_html app
```
(Si hPanel a créé le sous-domaine dans `~/domains/app.jackcie.fr/public_html`, faire le lien à cet endroit-là.)

**Option B (sans SSH)** : copier tout le contenu de `~/domains/jackcie.nlapps.fr/public_html` (y compris `.env`,
`storage/` et les fichiers cachés) dans le dossier du sous-domaine avec le Gestionnaire de fichiers, puis, une fois
la nouvelle adresse vérifiée, remplacer le contenu de l'ancien `public_html` par le seul fichier
`scripts/migration/ancien-domaine.htaccess` renommé en `.htaccess`. Dans ce cas, changer d'abord l'adresse du webhook
Stripe (étape 4), et la commande cron (étape 3) doit viser le nouveau dossier.

## 3. Réglages de l'application (`.env`, dans le dossier de l'application)
```
APP_URL=https://app.jackcie.fr
LEGACY_HOSTS=jackcie.nlapps.fr
```
Ne pas définir `SESSION_DOMAIN` (les utilisateurs devront se reconnecter une fois sur la nouvelle adresse).
La modification est prise en compte tout de suite (seulement si la configuration a été mise en cache à la main,
lancer `php artisan optimize:clear`).
Cron : avec l'option A, rien à changer ; avec l'option B, mettre à jour le chemin de `cron.php`
(Administration › Notifications › diagnostic affiche la commande exacte).

## 4. Services extérieurs
- **Stripe** › Développeurs › Webhooks : ajouter le point de terminaison `https://app.jackcie.fr/stripe/webhook`
  (mêmes événements), copier son nouveau secret dans Administration › Intégrations, puis supprimer l'ancien.
  Portail client et Checkout reprennent automatiquement la nouvelle adresse.
- **Google** (si la connexion Google est active) : Google Cloud › Identifiants › client OAuth : ajouter l'URI de
  redirection `https://app.jackcie.fr/connexion/google/retour` et le domaine `jackcie.fr` dans l'écran de consentement.
- **Apple** (si la connexion Apple est active) : Services ID › Sign in with Apple › ajouter le domaine
  `app.jackcie.fr` et la Return URL `https://app.jackcie.fr/connexion/apple/retour`.
- **Notifications push** : les clés VAPID ne changent pas ; chaque utilisateur réactive les notifications sur la
  nouvelle adresse.
- **E-mails** : les liens des e-mails utilisent `APP_URL` automatiquement.

## 5. Vérifications
- https://app.jackcie.fr : connexion, tableau de bord, assistant.
- https://jackcie.nlapps.fr/login → redirigé vers https://app.jackcie.fr/login.
- Un QR code de fiche d'urgence imprimé : s'ouvre bien sur app.jackcie.fr.
- Administration › Notifications › « Envoyer un essai » ; Administration › Intégrations › tests Stripe et e-mail.
- Stripe › Webhooks : le prochain événement est « réussi » sur la nouvelle adresse.
