# Mettre Tapikard en ligne sur Hostinger (hébergement mutualisé)

Hostinger récupère le code directement depuis GitHub, installe les dépendances, compile le site et le lance.
Vous n'avez aucune commande à taper sur le serveur, et **chaque `push` sur la branche choisie redéploie le site automatiquement**.

> **Prérequis : offre Business ou Cloud.** Les offres Single et Premium ne font pas tourner Node.js.
> Vérifiez dans hPanel : *Sites web → Créer un site* doit proposer **« Web App » / « Node.js »**.
> Sinon, passez à l'offre Business depuis hPanel.

---

## Étape 1 — Créer l'adresse email d'envoi (5 min)

La connexion à Tapikard se fait par un lien envoyé par email : **sans email configuré, personne ne peut se connecter**.

1. hPanel → **Emails** → choisissez le domaine `tapikard.com` → **Créer un compte email**.
2. Créez `bonjour@tapikard.com` avec un mot de passe solide. Notez-le.

## Étape 2 — Préparer les secrets (5 min, sur votre ordinateur)

Dans un terminal (Node.js est déjà installé chez vous), lancez cette commande **4 fois** et notez chaque résultat :

```bash
node -e "console.log(require('crypto').randomBytes(16).toString('hex'))"
```

| Variable | Valeur |
|---|---|
| `SESSION_SECRET` | 1er résultat **+** 2ᵉ résultat collés (64 caractères) |
| `SUN_K_META` | 3ᵉ résultat |
| `SUN_MASTER_K1` | 4ᵉ résultat |
| `ENCODING_API_KEY` | relancez une 5ᵉ fois |

⚠️ **`SUN_K_META` et `SUN_MASTER_K1` sont les clés de vos puces NTAG 424.** Ce sont les mêmes que sur votre poste d'encodage.
Conservez-les en double (gestionnaire de mots de passe **et** papier dans un coffre). Si vous les perdez, aucune carte vendue ne pourra plus être vérifiée.
Ne les changez jamais une fois des cartes encodées.

## Étape 3 — Déployer l'application (10 min)

1. hPanel → **Sites web** → **Créer un site / Ajouter un site** → **Web App (Node.js)**.
2. Choisissez **« Importer depuis GitHub »**, autorisez Hostinger à accéder à votre compte GitHub,
   puis sélectionnez le dépôt **`nico83200/Claude-public`**.
   (Sans GitHub, vous pouvez aussi envoyer un zip du code produit par `deploy/build-hostinger-zip.sh`.)
3. **Paramètres de build** (normalement détectés automatiquement) :

   | Réglage | Valeur |
   |---|---|
   | Branche | celle qui contient l'application (`main` une fois la pull request fusionnée) |
   | Framework | **Next.js** |
   | Version de Node.js | **22.x** (ou **24.x**, voir Dépannage) |
   | Commande de build | `npm run build` |
   | Gestionnaire de paquets | npm |

4. **Variables d'environnement** — ajoutez-les **avant** de cliquer sur Déployer :

   | Nom | Valeur |
   |---|---|
   | `APP_URL` | `https://tapikard.com` |
   | `ADMIN_EMAILS` | votre email personnel (accès au back-office) |
   | `SESSION_SECRET` | voir étape 2 |
   | `SUN_K_META` | voir étape 2 |
   | `SUN_MASTER_K1` | voir étape 2 |
   | `ENCODING_API_KEY` | voir étape 2 |
   | `SMTP_HOST` | `smtp.hostinger.com` |
   | `SMTP_PORT` | `465` |
   | `SMTP_USER` | `bonjour@tapikard.com` |
   | `SMTP_PASS` | le mot de passe de l'étape 1 |
   | `EMAIL_FROM` | `Tapikard <bonjour@tapikard.com>` |

   N'ajoutez **pas** `SEED_DEMO` ni `SUN_FACTORY_KEYS` (réservés aux tests).
   `DATABASE_PATH` est facultatif : par défaut la base est créée dans `~/tapikard-data/tapikard.db`,
   **en dehors** du dossier de l'application que Hostinger remplace à chaque déploiement.

5. Cliquez sur **Déployer** et attendez la fin du build (2 à 5 minutes).
6. Reliez le domaine `tapikard.com` à cette Web App si Hostinger le demande, et vérifiez que le **SSL** est actif (hPanel → Sécurité → SSL).

## Étape 4 — Vérifier (5 min)

1. Ouvrez https://tapikard.com : la page d'accueil s'affiche.
2. Allez sur https://tapikard.com/connexion et saisissez **l'email mis dans `ADMIN_EMAILS`**.
3. Vous recevez un email « Votre lien de connexion Tapikard ». Cliquez : vous arrivez dans le **back-office**.
4. Dans le gestionnaire de fichiers Hostinger, le dossier `tapikard-data` doit être apparu à la racine de votre espace.
5. Faites une commande test sur https://tapikard.com/creer (paiement simulé tant que Stripe n'est pas configuré).

## Étape 5 — Paiement Stripe (quand vous êtes prêt à vendre)

1. Dans Stripe : **Développeurs → Clés API**, puis copiez la clé secrète (`sk_live_…`).
2. **Développeurs → Webhooks → Ajouter un endpoint** : URL `https://tapikard.com/api/stripe/webhook`, événement `checkout.session.completed`. Copiez le **secret de signature** (`whsec_…`).
3. Ajoutez dans les variables d'environnement Hostinger : `STRIPE_SECRET_KEY` et `STRIPE_WEBHOOK_SECRET`, puis **redéployez**.

## Mettre à jour le site

Poussez (`git push`) sur la branche connectée : Hostinger recompile et redéploie tout seul.
Vos données ne sont pas touchées : elles sont dans `~/tapikard-data`, hors du dossier de l'application.

## Sauvegardes

Back-office → **Tableau de bord** → **Télécharger une sauvegarde de la base**.
Faites-le au moins une fois par semaine et rangez le fichier hors de Hostinger (Google Drive, disque externe…).
Ne copiez pas le fichier directement depuis le gestionnaire de fichiers pendant que le site tourne : utilisez ce bouton, qui produit une copie cohérente.

## Poste d'encodage

Le script d'encodage (voir `docs/encodage-ntag424.md` dans le dépôt) appelle :
`POST https://tapikard.com/api/encodage` avec l'en-tête `Authorization: Bearer <ENCODING_API_KEY>`.

## Dépannage

| Symptôme | Solution |
|---|---|
| Erreur `No such built-in module: node:sqlite` dans les journaux | Passez la version de Node.js à **24.x** dans les paramètres de build, puis redéployez |
| Le build échoue sur une erreur de mémoire | Redéployez une fois (pics passagers). Si l'erreur persiste, contactez le support Hostinger ou passez à une offre Cloud |
| Je ne reçois pas l'email de connexion | Vérifiez `SMTP_USER`, `SMTP_PASS` et que `EMAIL_FROM` utilise la même adresse que `SMTP_USER`. Regardez dans les spams |
| « SESSION_SECRET … obligatoire » ou « SUN_K_META … obligatoires » dans les journaux | Une variable d'environnement manque ou est mal recopiée (32 caractères hexadécimaux pour les clés SUN) |
| Le scan d'une carte affiche « Carte non reconnue » | Les clés SUN du site ne sont pas les mêmes que celles utilisées à l'encodage |

## Limites à connaître

- L'hébergement mutualisé partage la mémoire et le processeur avec vos autres sites. C'est largement suffisant pour démarrer (quelques milliers de scans par jour).
- La base SQLite convient tant qu'une seule instance de l'application tourne, ce qui est le cas chez Hostinger.
- Si l'activité décolle (beaucoup d'équipes, de très gros volumes), passez à un VPS. Le paquet `tapikard-…zip` (build autonome) et le guide `deploy/INSTALL.md` sont prévus pour ça.
