# Tapikard — cartes de visite NFC paramétrables

Cartes de visite imprimées avec puce NFC sécurisée (NTAG 424 DNA) : un scan affiche le profil du porteur et l'ajoute aux contacts.
Application web Next.js 16 (App Router) + TypeScript ; base SQLite intégrée à Node (`node:sqlite`) pour démarrer sans infrastructure.

## Documentation

- [Architecture du système](docs/architecture-carte-visite-nfc.md)
- [Guide d'encodage des cartes NTAG 424 DNA](docs/encodage-ntag424.md)
- [Mise en ligne sur Hostinger](deploy/HOSTINGER.md) · [Installation sur un VPS](deploy/INSTALL.md)
- [`tools/encodeur/`](tools/encodeur/) — **script d'encodage des cartes** (Python, lecteur ACR122U) : `python3 encodeur.py --lot LOT.csv`
- [`android/`](android/) — **application Android « Tapikard Encodeur »** : encode les cartes avec le NFC du téléphone, depuis la page `/encodage`
- [`tools/sun_verify.py`](tools/sun_verify.py) — vérification SUN en Python (`python3 tools/sun_verify.py` lance le test NXP)
- [Captures d'écran](docs/captures/)
- **Emails** : gabarit et modèles dans [`lib/emails/`](lib/emails/) — aperçu, envoi de test et export des campagnes dans Back-office → Emails.
  Les images des emails sont dans `public/email/` et se régénèrent avec [`scripts/generer-visuels.mjs`](scripts/generer-visuels.mjs).

## Démarrer

```bash
npm install
npm run dev          # http://localhost:3000
npm test             # tests crypto SUN et protocole NTAG 424 (RFC 4493 + vecteurs NXP AN12196)
```

Node ≥ 22.13 requis. En développement, des données de démonstration sont créées automatiquement (`data/tapikard.db`) :

| Compte | Rôle |
|---|---|
| `demo@tapikard.com` | client avec profils, cartes, statistiques et contacts |
| `admin@tapikard.com` | back-office |

Connexion par lien magique : sans service d'email configuré, le lien s'affiche à l'écran (en dev uniquement).

**Tester un scan NFC sécurisé** (clés d'usine, vecteur NXP) :
<http://localhost:3000/c?e=EF963FF7828658A599F3041510671E88&m=94EED9EE65337086>
→ profil avec badge « Carte authentique ». Rechargé une 2ᵉ fois → « lien déjà utilisé » (anti-rejeu). Signature modifiée → « Carte non reconnue ».

## Pages

| Espace | URL | Contenu |
|---|---|---|
| Vitrine | `/` | Accueil, fonctionnement, tarifs, FAQ |
| | `/carte-basic` | **Carte Basic** prête à l'emploi (design Tapikard noir ou blanc, NFC + QR), sans BAT |
| | `/creer` | **Configurateur** : matière, finition, modèle, police, couleurs, logo, informations, quantité ; aperçu recto/verso en direct ; prix dégressif |
| | `/commande/[id]` | Suivi, paiement (Stripe ou simulé), **validation du BAT**, demande de modification, BAT SVG à l'échelle (fonds perdus, traits de coupe) |
| Scan | `/c?e=…&m=…` | Vérification SUN NTAG 424 (déchiffrement, CMAC, anti-rejeu), puis redirection vers le profil |
| | `/q/[carte]` | Profil public (QR code ou après scan) : actions rapides, vCard, liens. Après « Ajouter aux contacts », **proposition d'échange de coordonnées** : le porteur reçoit le contact par email (fiche .vcf jointe), le visiteur reçoit la fiche du porteur |
| | `/vcard/[carte]` | Fiche contact `.vcf` (vCard 3.0 avec photo) |
| Client | `/compte` | Aperçu, **éditeur de profil avec aperçu téléphone**, multi-profils, gestion des cartes (profil affiché, activation, désactivation, perte), statistiques, **contacts reçus** (statut de suivi, notes, fiche .vcf, export CSV, synchronisation CRM par webhook Zapier/Make/n8n), commandes |
| | `/activer` | Activation d'une carte reçue (code de l'encart si on n'est pas l'acheteur) |
| Back-office | `/admin` | Tableau de bord, commandes (paiement manuel, BAT, expédition avec suivi, annulation), **production** (guide pas à pas, lots, **stock de cartes Basic non attribuées**, CSV imprimeur/encodage), **stocks** (ruptures par coloris, matière, finition ou modèle), **emails** (aperçu, test, export des campagnes), recherche de cartes |
| Encodage | `/encodage` | **Encodage avec un téléphone Android** (dans l'application Tapikard Encodeur) : choix du lot, carte attendue, encodage, vérification |
| API | `POST /api/encodage` | Poste d'encodage : liaison carte ↔ UID de la puce (`Authorization: Bearer ENCODING_API_KEY`) |
| | `POST /api/encodage-mobile` | Encodage mobile : le serveur fait la cryptographie NTAG 424, le téléphone relaie les commandes (session administrateur) |
| | `POST /api/stripe/webhook` | Confirmation de paiement (signature vérifiée) |
| | `POST /api/leads`, `POST /api/evenements` | Formulaire de contact et clics (limités en débit) |

## Parcours de bout en bout

1. Le client configure sa carte sur `/creer`, puis paie. Son compte et son profil en ligne sont créés.
2. Le paiement crée une carte par exemplaire (identifiant aléatoire, code d'activation). Le client valide le BAT.
3. Le back-office crée un **lot**. Le CSV contient l'URL du QR (données variables) et le code d'activation de chaque carte, à transmettre à l'imprimeur.
4. Vous encodez chaque carte imprimée (`docs/encodage-ntag424.md`). Le script du poste appelle `POST /api/encodage`.
5. Vous expédiez la commande avec son numéro de suivi. Le client scanne sa carte et l'active.
6. Chaque scan est vérifié et compté, et le client modifie son profil quand il veut.

## Configuration

Copier `.env.example` en `.env.local` (en local) ou saisir les variables dans l'hébergeur. Variables **obligatoires en production** :

- `SESSION_SECRET`
- `SUN_K_META` et `SUN_MASTER_K1`
- `ENCODING_API_KEY`
- `APP_URL=https://tapikard.com`
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` et `EMAIL_FROM` : la connexion se fait par lien envoyé par email.

Variables optionnelles :

- `STRIPE_SECRET_KEY` et `STRIPE_WEBHOOK_SECRET` (paiement réel ; sinon paiement simulé) ;
- `DATABASE_PATH` (par défaut `~/tapikard-data/tapikard.db` en production, hors du dossier de l'application) ;
- `SUN_MASTER_K0` (encodage depuis le téléphone ; même valeur que sur le poste d'encodage) ;
- `NEXT_SERVER_ACTIONS_ENCRYPTION_KEY` (recommandée : les formulaires restent valides d'une mise à jour à l'autre).

### Application Android

Projet Java sans dépendance dans [`android/`](android/) : une WebView sur `/encodage` et un pont NFC (`window.TapikardNfc.transceive`).
Les clés ne quittent jamais le serveur : le site calcule chaque commande NTAG 424 (`lib/ntag424.ts`, `lib/encodeurServeur.ts`)
et le téléphone ne fait que la transmettre à la carte (`lib/relaisNfc.ts`, sessions en mémoire : une seule instance du site).

```bash
cd android
./gradlew assembleRelease     # SDK Android requis (ANDROID_HOME ou local.properties)
```

L'APK est publié dans `public/telechargements/tapikard-encodeur.apk`. Pour signer avec votre propre clé, créez
`android/keystore.properties` (`storeFile`, `storePassword`, `keyAlias`, `keyPassword`) ; ce fichier et la clé ne doivent jamais être commités.

## Avant la mise en production

- Passer de SQLite à PostgreSQL si plusieurs instances sont nécessaires. L'accès aux données est concentré dans `lib/db.ts`.
- Stocker photos et logos sur un stockage objet (S3). Ils sont aujourd'hui enregistrés en data URL dans la base.
- Ajouter un limiteur de débit partagé (Redis). Celui de `lib/ratelimit.ts` est en mémoire.
- Faire valider les mentions légales et la politique de confidentialité, et rédiger les CGV.
- Pour un BAT en PDF CMJN, convertir le SVG produit chez l'imprimeur ou avec un service de rendu.
