# Déménagement de l'application : jackcie.nlapps.fr → app.jackcie.fr

La base de données ne change pas. L'ancienne adresse doit **rester en service pour toujours** et rediriger vers la
nouvelle : des QR codes sont imprimés (fiches d'urgence collées sur les box, affiches EquitaLyon), des liens de
résumé vétérinaire et de parrainage circulent, des agendas sont abonnés au flux `.ics`.

Ordre conseillé : 0 → 1 → 2 → 3 → 5 → 6 (vérifier la nouvelle adresse) → 4 (rediriger l'ancienne).
En complément, l'application redirige aussi d'elle-même toute visite sur une adresse listée dans `LEGACY_HOSTS`
vers `APP_URL` (utile si les deux adresses servent un jour le même dossier).

## 0. Avant (la veille)
- Administration › Messages : prévenir les utilisateurs. Après le changement, il faudra **réinstaller l'application
  sur l'écran d'accueil** depuis app.jackcie.fr et **réactiver les notifications** (une application installée et ses
  notifications sont liées à une adresse ; c'est une règle des navigateurs).
- Faire une sauvegarde (Administration › Mises à jour, ou hPanel › Sauvegardes).

## 1. Créer app.jackcie.fr chez Hostinger
hPanel › Sites web › **Ajouter un site web** › utiliser un domaine existant › `app.jackcie.fr`
(il obtient son propre dossier, `domains/app.jackcie.fr/public_html`, séparé du site vitrine).
Activer le **SSL** pour app.jackcie.fr (hPanel › Sécurité › SSL) et attendre qu'il soit actif.
Si hPanel ne propose qu'un sous-domaine dans `jackcie.fr`, ça fonctionne aussi : utiliser le dossier qu'il indique.

## 2. Copier l'application (sans SSH, avec le Gestionnaire de fichiers) — à une heure creuse
1. Gestionnaire de fichiers › `domains/jackcie.nlapps.fr/public_html` : tout sélectionner (afficher les fichiers
   cachés pour inclure `.env` et `.htaccess`) › **Compresser** en `jackcie-transfert.zip`.
   Le dossier `storage/app/updates` (sauvegardes des mises à jour) peut être exclu s'il est volumineux.
2. **Déplacer** `jackcie-transfert.zip` vers `domains/app.jackcie.fr/public_html`, puis **Extraire** sur place
   (vider d'abord ce dossier s'il contient une page par défaut d'Hostinger).
   Le contenu de `public_html` doit être identique à l'ancien : `.env`, `.htaccess`, `app/`, `public/`, `storage/`, `vendor/`…
3. Supprimer `jackcie-transfert.zip` des deux dossiers une fois l'extraction vérifiée.

La base de données est la même : rien à copier de ce côté.

## 3. Réglages de la nouvelle copie
Gestionnaire de fichiers › `domains/app.jackcie.fr/public_html/.env` › Modifier :
```
APP_URL=https://app.jackcie.fr
LEGACY_HOSTS=jackcie.nlapps.fr
```
Ne pas définir `SESSION_DOMAIN` (les utilisateurs devront se reconnecter une fois sur la nouvelle adresse).
La modification est prise en compte tout de suite.
**Cron** (hPanel › Avancé › Tâches cron) : remplacer le chemin `domains/jackcie.nlapps.fr/public_html/cron.php`
par `domains/app.jackcie.fr/public_html/cron.php`. Administration › Notifications affiche la commande exacte
une fois l'application ouverte sur la nouvelle adresse.

## 4. Faire rediriger l'ancienne adresse (une fois app.jackcie.fr vérifiée, étape 6)
Gestionnaire de fichiers › `domains/jackcie.nlapps.fr` : **renommer** `public_html` en `public_html_ancien`
(gardé quelques semaines par sécurité), créer un nouveau dossier `public_html` vide et y déposer le fichier
`ancien-domaine.htaccess` renommé en **`.htaccess`**. Toute visite de l'ancienne adresse (QR codes, liens, agendas)
part alors vers app.jackcie.fr, même chemin et mêmes paramètres. Ne jamais supprimer ce dossier ni le domaine.

## 5. Services extérieurs
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

## 6. Vérifications
- https://app.jackcie.fr : connexion, tableau de bord, assistant.
- https://jackcie.nlapps.fr/login → redirigé vers https://app.jackcie.fr/login.
- Un QR code de fiche d'urgence imprimé : s'ouvre bien sur app.jackcie.fr.
- Administration › Notifications › « Envoyer un essai » ; Administration › Intégrations › tests Stripe et e-mail.
- Stripe › Webhooks : le prochain événement est « réussi » sur la nouvelle adresse.
