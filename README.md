# Crinière & Co — boutique équestre en impression à la demande

Boutique en ligne clé en main pour vendre des **vêtements, accessoires et objets déco sur le thème de l'équitation**, fabriqués **à la demande** par Printful et **personnalisables au nom du cheval**. Pensée pour un **hébergement mutualisé Hostinger** : PHP 8.1+ et MySQL, **aucune dépendance** (ni Composer ni Node), rien à compiler.

## Ce qui est inclus

| Domaine | Fonctionnalités |
|---|---|
| **Boutique** | Accueil, catalogue filtrable (catégories, recherche, tri, « personnalisables »), fiches produit avec couleurs, tailles et prix par variante, panier, suivi de commande client, pages éditables, contact, newsletter |
| **Personnalisation** | Champ « nom du cheval » avec **aperçu en direct** sur la photo ; génération automatique du **fichier d'impression HD** (PNG) envoyé à Printful |
| **Paiement — Stripe Checkout** | CB, Apple Pay, Google Pay (+ PayPal, Bancontact… activables dans Stripe), codes promo Stripe, livraison standard/express, livraison offerte dès un seuil, webhooks signés et idempotents, remboursements depuis l'admin |
| **Production — Printful** | Transmission automatique de chaque commande payée (brouillon ou confirmation automatique), import des produits Printful en un clic, webhooks expédition/échec/annulation, relance automatique en cas d'échec |
| **E-mails** | SMTP Hostinger : confirmation de commande, expédition avec suivi, alertes à l'admin (nouvelle commande, échec Printful, message de contact) |
| **Administration** | Tableau de bord (CA, panier moyen, paniers abandonnés, graphique), commandes (historique, notes, export CSV), produits et variantes, catégories, pages, clients, newsletter (export CSV + synchro **Brevo**), messages, réglages, page Intégrations avec tests de connexion |
| **SEO & marketing** | URLs propres, balises meta/Open Graph, données structurées Product, `sitemap.xml`, `robots.txt`, flux **Google Merchant Center**, GA4 et Meta Pixel chargés **après consentement** |
| **Légal (France)** | Bandeau cookies, modèles CGV / mentions légales / confidentialité / livraison & retours (exception de rétractation des produits personnalisés, art. L221-28), acceptation des CGV avant paiement |
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
cron/cron.php   tâche planifiée
bin/            installation locale
tests/          tests de bout en bout
docs/           guide de déploiement
```
