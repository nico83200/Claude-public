# Mise en ligne sur Hostinger (hébergement mutualisé)

Durée : environ 30 minutes. Prérequis : un plan Hostinger **Premium ou Business** (pour MySQL, les tâches cron et les boîtes e-mail), un nom de domaine, un compte **Stripe** et un compte **Printful**.

---

## 1. Préparer l'hébergement (hPanel)

1. **Version de PHP** : *Avancé › Configuration PHP* → choisissez **PHP 8.2** ou une version plus récente. Dans l'onglet *Extensions PHP*, vérifiez que `gd`, `intl`, `curl`, `mbstring`, `pdo_mysql` et `fileinfo` sont cochées (c'est le cas par défaut).
2. **SSL** : *Sécurité › SSL* → installez le certificat gratuit. Le site force ensuite automatiquement le HTTPS.
3. **Base de données** : *Bases de données › Bases de données MySQL* → créez une base et un utilisateur, puis notez le **nom de la base**, l'**utilisateur** et le **mot de passe** (ils commencent par `u123456789_`). L'hôte est `localhost`.
4. **Boîte e-mail** : *E-mails* → créez par exemple `contact@votre-domaine.fr` et notez son mot de passe.

## 2. Envoyer les fichiers

Choisissez **une** des trois méthodes. Dans tous les cas, l'intégralité du projet va dans `public_html/` : le fichier `.htaccess` racine sert le dossier `public/` et bloque l'accès web à tout le reste.

**A. Déploiement Git (recommandé)**
*Avancé › GIT* → dépôt `https://github.com/<vous>/<dépôt>.git`, branche `main`, répertoire vide (= `public_html`). Activez le *déploiement automatique* et ajoutez l'URL de webhook fournie dans GitHub (*Settings › Webhooks*). `public_html` doit être vide avant le premier déploiement (supprimez le `default.php`).

**B. GitHub Actions (FTP)**
Dans GitHub, *Settings › Secrets and variables › Actions*, ajoutez `FTP_SERVER`, `FTP_USERNAME` et `FTP_PASSWORD` (*hPanel › Fichiers › Comptes FTP*). Chaque push sur `main` lance les tests, puis le déploiement si les tests passent. La configuration, les journaux et les téléversements ne sont jamais écrasés.

**C. Manuellement**
Téléchargez le ZIP du dépôt, puis *Fichiers › Gestionnaire de fichiers › public_html › Téléverser*, et décompressez-le.

## 3. Lancer l'installateur

Ouvrez `https://votre-domaine.fr/` : l'assistant d'installation s'affiche automatiquement.

- Il vérifie le serveur (toutes les lignes doivent être vertes).
- Renseignez la base MySQL, le compte administrateur et la boîte e-mail (SMTP `smtp.hostinger.com`, port `465`).
- Les clés Stripe et Printful peuvent être saisies maintenant ou plus tard.
- Laissez « catalogue de démonstration » coché pour un premier aperçu ; vous pourrez ensuite supprimer ou désactiver ces produits.

En fin d'installation, l'assistant affiche les **URL de webhooks** et la **commande cron** : notez-les. Il est ensuite désactivé définitivement (fichier `storage/installed.lock`).

> Les clés (Stripe, Printful, e-mail, Brevo, Analytics) sont enregistrées **dans la base de données** : elles se modifient à tout moment dans *Admin › Intégrations › Clés et jetons* (confirmation par votre mot de passe) et ne sont jamais perdues lors d'une mise à jour ou d'une réinstallation. Le fichier `config/config.php` ne contient que l'adresse du site et l'accès à la base.

## 4. Stripe (paiement)

1. Sur https://dashboard.stripe.com/apikeys, copiez la **clé secrète** (`sk_live_…`) et la **clé publique** (`pk_live_…`). Utilisez les clés `sk_test_` pour vos essais.
2. *Développeurs › Webhooks › Ajouter un endpoint* :
   - URL : `https://votre-domaine.fr/webhooks/stripe`
   - Événements : `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `checkout.session.expired`, `charge.refunded`
   - Copiez le **secret de signature** (`whsec_…`).
3. Saisissez ces trois valeurs dans *Admin › Intégrations › Clés et jetons*, rubrique Stripe.
4. Facultatif :
   - *Paramètres › Moyens de paiement* : activez Apple Pay, Google Pay, PayPal, Bancontact…
   - *Produits › Coupons* : créez vos codes promo. Le champ « code promo » apparaît automatiquement sur la page de paiement.

Carte de test : `4242 4242 4242 4242`, date future, n'importe quel CVC.

## 5. Printful (fabrication et expédition)

1. Sur Printful, créez une boutique de type **« Manual order platform / API »**.
2. Sur https://developers.printful.com/tokens, créez un **jeton privé** couvrant cette boutique, avec les droits *orders*, *sync products*, *webhooks* et *stores*. Saisissez-le dans *Admin › Intégrations › Clés et jetons* (rubrique Printful).
3. Dans l'admin, ouvrez *Intégrations* et cliquez sur **Tester** dans la ligne Printful, puis sur **Enregistrer chez Printful** pour le webhook : expéditions, numéros de suivi et incidents remonteront automatiquement.
4. **Ajouter vos produits** : créez-les dans Printful (support, couleurs, tailles, visuel, prix de vente), puis dans l'admin ouvrez *Produits › Importer depuis Printful*. Le produit est importé en **brouillon** : complétez la description, choisissez une catégorie, puis cochez « En ligne ».
5. **Produits personnalisables (nom du cheval)** : dans la fiche produit,
   - cochez « Proposer un texte personnalisé » ;
   - téléversez le **visuel d'impression PNG transparent HD** (sans le nom) ;
   - réglez la position du texte et cliquez sur **Tester le fichier d'impression**.

   Chaque commande génère alors un fichier propre à la commande, envoyé à Printful avec l'ID catalogue de la variante (déjà renseigné par l'import).
6. Par défaut, la confirmation automatique est désactivée : les commandes arrivent chez Printful **en brouillon**, à confirmer depuis l'admin (bouton « Confirmer chez Printful ») ou depuis Printful. Une fois vos produits validés, cochez « Confirmer automatiquement les commandes » dans *Intégrations › Clés et jetons* pour un fonctionnement 100 % automatique. Pensez à enregistrer un moyen de paiement dans Printful (*Billing*).

### Les trois modes de personnalisation

Dans la fiche d'un produit personnalisable (*Admin › Produits*), section *Personnalisation* :

| Mode | Ce que fait la cliente | Ce que fait le site |
|---|---|---|
| **Nom** | Saisit le nom de son cheval (aperçu en direct) | Écrit le nom sur votre visuel PNG (police Playfair, Allura ou Montserrat) |
| **Photo** | Ajoute la photo de son cheval + son nom (aperçu en direct) | Cadre la photo dans la zone définie (rectangle, cercle ou arche) puis ajoute le nom |
| **Portrait illustré** | Ajoute la photo de son cheval | La commande passe en « Portrait à réaliser » : vous téléchargez la photo, dessinez le portrait, déposez le PNG final dans la commande, et elle part automatiquement chez Printful |
| **Portrait IA** | Ajoute la photo, clique sur « Créer mon portrait », saisit le nom et l'année (aperçu encadré en direct) | L'IA redessine la photo dans le style « Encre & Poudre » ; à la commande, l'affiche (dessin + nom + année) est composée et envoyée à Printful. Mise en service : [`PORTRAIT_IA.md`](PORTRAIT_IA.md) |

Pour chaque produit, renseignez le **visuel d'impression** (PNG transparent), les **zones** (valeurs de 0 à 1) et testez avec le bouton **Tester le fichier d'impression**. L'« image vierge » est la photo produit sans exemple de nom/photo, utilisée pendant l'aperçu en direct.

## 6. Tâche planifiée (cron)

*Avancé › Tâches Cron* → type **PHP**, toutes les 15 minutes (`*/15 * * * *`) :

```
/usr/bin/php /home/<UTILISATEUR>/domains/<DOMAINE>/public_html/cron/cron.php
```

La commande exacte est affichée dans l'admin, page *Intégrations*. Elle relance les transmissions Printful en échec, rattrape les expéditions, annule les paniers non payés, synchronise la newsletter avec Brevo et supprime les photos et portraits IA non commandés après 30 jours.

## 7. Finaliser

- *Admin › Réglages* : renseignez la raison sociale, l'adresse, le SIRET, la TVA, le médiateur de la consommation, les frais de port, les réseaux sociaux… Les pages légales se remplissent automatiquement.
- *Admin › Pages* : relisez les CGV (modèle à faire valider par un juriste).
- *Intégrations › Envoyer un test* : vérifiez la réception des e-mails. Pour éviter le dossier spam, contrôlez dans *E-mails › Paramètres DNS* que les enregistrements SPF, DKIM et DMARC sont actifs.
- Facultatif, dans *Admin › Intégrations › Clés et jetons* :
  - clé API et n° de liste Brevo (newsletter) ;
  - identifiants Google Analytics 4 et Meta Pixel (chargés seulement après consentement) ;
  - jeton **Replicate** du portrait IA (rubrique « Portrait IA ») : voir [`PORTRAIT_IA.md`](PORTRAIT_IA.md). Sans jeton, le portrait fonctionne en mode démonstration. La page de vente est en ligne à l'adresse `/portrait-aquarelle`.
- *Réglages › Conversion* :
  - créez dans Stripe un code promo de bienvenue (*Produits › Coupons*, ex. `BIENVENUE10`) et reportez-le dans « Code promo de bienvenue » : la popup et l'e-mail de bienvenue l'offrent aux nouvelles inscrites ;
  - si vous activez Klarna dans Stripe, passez « Afficher paiement en 3x » à Oui.
- *Réglages › Avis clients* : les invitations à laisser un avis partent automatiquement X jours après l'expédition ; les avis se modèrent dans *Admin › Avis clients* (ne refusez jamais un avis au seul motif qu'il est négatif).
- Google Search Console : déclarez `https://votre-domaine.fr/sitemap.xml`.
- Google Merchant Center : ajoutez le flux `https://votre-domaine.fr/flux/google-merchant.xml` (fiches produits gratuites dans Google Shopping).
- Passez une **commande réelle de test** (avec un code promo à 100 % ou un petit montant), puis remboursez-la depuis l'admin.

## Mettre à jour le site

1. Récupérez le ZIP de la nouvelle version : celui qu'on vous a fourni, ou dans GitHub, onglet *Actions*, dernière exécution, rubrique *Artifacts*.
2. Dans l'administration, ouvrez **Mise à jour**, choisissez le ZIP et cliquez sur **Installer la mise à jour**.

Le site vérifie d'abord le paquet : structure, chemins, numéro de version, syntaxe PHP. En cas de problème, rien n'est modifié. Sinon il :
- sauvegarde les fichiers remplacés ;
- copie les nouveaux fichiers (quelques secondes de maintenance) ;
- met à jour la base de données si besoin.

Votre configuration, vos données et vos images ne sont jamais touchées. En cas de souci, le bouton **Restaurer** remet les fichiers de la version précédente. Les modifications de base de données, elles, ne sont pas annulées : pour une mise à jour importante, exportez aussi la base via phpMyAdmin.

## Dépannage

| Symptôme | Piste |
|---|---|
| Page blanche ou erreur 500 | `storage/logs/error-AAAA-MM.log` et `php-error.log`. Pour voir le détail temporairement : `'debug' => true` dans `config.php` (à remettre à `false` ensuite). |
| Commande payée mais restée « Payée » | Webhook Stripe mal configuré : *Stripe › Webhooks › endpoint › tentatives*. Le secret `whsec_` doit correspondre. |
| « Erreur de transmission » Printful | Le message exact apparaît dans la commande (admin). Cause habituelle : une variante sans ID Printful. Corrigez-la, puis cliquez sur « Transmettre à Printful ». |
| E-mails non reçus | `storage/logs/mail-AAAA-MM.log`. Essayez le port `587` avec `'encryption' => 'tls'`. |
| L'assistant d'installation réapparaît | Les fichiers `config/config.php` ou `storage/installed.lock` ont été écrasés (extraction d'un ZIP par-dessus le site, par exemple). Si seul le verrou manque, le site se répare tout seul. Sinon, remplissez l'assistant avec **la même base MySQL** : la boutique est reconnectée sans rien effacer, et comme les clés sont stockées en base, elles sont conservées (laissez leurs champs vides). Pour éviter cela, faites les mises à jour via *Admin › Mise à jour*. |
| Mise à jour bloquée en « maintenance » | Le mode maintenance se lève tout seul au bout de 10 minutes. Vous pouvez aussi supprimer `storage/maintenance.flag`. |
| Téléversement refusé (visuel HD) | Le fichier `.user.ini` relève la limite à 64 Mo ; sinon *Configuration PHP › Options PHP* → `upload_max_filesize`. |
