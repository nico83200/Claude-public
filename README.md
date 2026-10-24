# Crinière Club — le club des passionnées

Boutique de print on demand pour cavalières

Boutique en ligne clé en main pour vendre des **vêtements, accessoires et cadeaux pour cavalières**, fabriqués **à la demande** par Printful, organisés en trois collections : **Le Club** (pièces à l'effigie de la marque), **Mon cheval, ma fierté** (nom, photo ou portrait illustré du cheval) et **Les petites attentions** (mugs, affiches, cadeaux). Pensée pour un **hébergement mutualisé Hostinger** : PHP 8.1+ et MySQL, **aucune dépendance** (ni Composer ni Node), rien à compiler.

## Ce qui est inclus

| Domaine | Fonctionnalités |
|---|---|
| **Boutique** | Accueil, catalogue filtrable (catégories, recherche, tri, « personnalisables »), fiches produit avec couleurs, tailles et prix par variante, panier, suivi de commande client, pages éditables, contact, newsletter |
| **Personnalisation** | Trois modes : **nom** (écriture manuscrite, aperçu en direct), **photo du cheval** (téléversée par la cliente, cadrée automatiquement dans une arche/un cercle, aperçu en direct) et **portrait illustré** (la commande attend que l'illustratrice dépose le portrait final dans l'admin). Fichier d'impression HD généré automatiquement pour Printful |
| **Conversion** | Mini-panier latéral sans rechargement, barre de progression et suggestions pour atteindre la livraison offerte, date de livraison estimée, meilleures ventes et badges, filtre « idées cadeaux », guide des tailles, barre d'achat collante sur mobile, popup de bienvenue avec code promo, mention de paiement en 3× (Klarna) |
| **Avis clients vérifiés** | Invitation par e-mail après livraison, formulaire sécurisé par commande, modération, étoiles sur les fiches et cartes, données structurées Google (étoiles dans les résultats) |
| **Identité** | Charte Crinière Club : logo vectorisé, bordeaux / rose poudré / beige / taupe / crème, Playfair Display + Montserrat + Allura (polices hébergées sur le site, conformes RGPD) |
| **Paiement — Stripe Checkout** | CB, Apple Pay, Google Pay (+ PayPal, Bancontact… activables dans Stripe), codes promo Stripe, livraison standard/express, livraison offerte dès un seuil, webhooks signés et idempotents, remboursements depuis l'admin |
| **Production — Printful** | Transmission automatique de chaque commande payée (brouillon ou confirmation automatique), import des produits Printful en un clic, webhooks expédition/échec/annulation, relance automatique en cas d'échec |
| **E-mails** | SMTP Hostinger : confirmation de commande, expédition avec suivi, alertes à l'admin (nouvelle commande, échec Printful, message de contact) |
| **Administration** | Tableau de bord (CA, panier moyen, paniers abandonnés, graphique), commandes (historique, notes, export CSV), produits et variantes, catégories, pages, clients, newsletter (export CSV + synchro **Brevo**), messages, réglages, page Intégrations avec tests de connexion |
| **SEO & marketing** | URLs propres, balises meta/Open Graph, données structurées Product, `sitemap.xml`, `robots.txt`, flux **Google Merchant Center**, GA4 et Meta Pixel chargés **après consentement** |
| **Légal (France)** | Bandeau cookies, modèles CGV / mentions légales / confidentialité / livraison & retours (exception de rétractation des produits personnalisés, art. L221-28), acceptation des CGV avant paiement |
| **Mises à jour** | Import d'un ZIP de nouvelle version depuis l'admin : contrôle du paquet, sauvegarde automatique, migrations de base, restauration en un clic |
| **Sécurité** | CSRF, requêtes préparées, mots de passe hachés, limitation des tentatives de connexion, téléversements contrôlés, dossiers privés hors web, HTTPS forcé |

## Démarrage rapide

- **Mise en ligne sur Hostinger** : suivez [`docs/DEPLOIEMENT_HOSTINGER.md`](docs/DEPLOIEMENT_HOSTINGER.md) (comptez environ 30 minutes).
- **Essai en local** :
  ```bash
  php bin/install-local.php admin@example.com admin12345   # SQLite + catalogue de démo
  php -S localhost:8000 -t public public/index.php
  php tests/run.php                                          # tests de bout en bout
  ```
  Boutique : http://localhost:8000 — Admin : http://localhost:8000/admin

## Comment circule une commande

```
Client ── panier ──► Stripe Checkout ──(webhook checkout.session.completed)──► commande « payée »
                                                                               │  e-mails client + admin
                                                                               ▼
                         fichier d'impression personnalisé (GD) ──► Printful (API /orders)
                                                                               │
                         Printful ──(webhook package_shipped)──► « expédiée » + e-mail de suivi
Tâche cron (15 min) : relance des transmissions en échec, rattrapage des statuts, annulation des paniers abandonnés, synchro Brevo.
```

## Arborescence

```
app/            code (contrôleurs, services Stripe/Printful/Mailer/Personalizer, vues)
config/         config.example.php (référence) — config.php est généré à l'installation
database/       schema.sql (MySQL, converti en SQLite pour le dev) et données initiales
public/         racine web : index.php, CSS/JS, images, téléversements
storage/        journaux, visuels d'impression privés, polices (Liberation, licence SIL OFL)
database/migrations/  migrations appliquées lors des mises à jour
VERSION         numéro de version (à incrémenter à chaque livraison)
cron/cron.php   tâche planifiée
bin/            installation locale
tests/          tests de bout en bout
docs/           guide de déploiement
```
