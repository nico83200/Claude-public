<?php
declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\Admin;
use App\Controllers\AiPortraitController;
use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Controllers\CronController;
use App\Controllers\HomeController;
use App\Controllers\OrderController;
use App\Controllers\PageController;
use App\Controllers\ReviewController;
use App\Controllers\SeoController;
use App\Controllers\ShopController;
use App\Controllers\WebhookController;
use App\Core\Router;

$r = new Router();

// Boutique
$r->get('/', [HomeController::class, 'index']);
$r->get('/boutique', [ShopController::class, 'catalog']);
$r->get('/categorie/{slug}', [ShopController::class, 'category']);
$r->get('/produit/{slug}', [ShopController::class, 'product']);
$r->get('/portrait-aquarelle', [ShopController::class, 'portraitLanding']);

// Panier & paiement
$r->get('/panier', [CartController::class, 'show']);
$r->get('/panier/apercu', [CartController::class, 'drawer']);
$r->get('/panier/photo/{name}', [CartController::class, 'photo']);
$r->post('/panier/ajouter', [CartController::class, 'add']);
$r->post('/portrait-ia', [AiPortraitController::class, 'create']);
$r->get('/portrait-ia/{token}', [AiPortraitController::class, 'status']);
$r->get('/portrait-ia/{token}/apercu', [AiPortraitController::class, 'preview']);
$r->post('/panier/modifier', [CartController::class, 'update']);
$r->post('/panier/supprimer', [CartController::class, 'remove']);
$r->post('/commande', [CheckoutController::class, 'start']);
$r->get('/commande/merci', [CheckoutController::class, 'success']);

// Compte cliente
$r->get('/compte', [AccountController::class, 'index']);
$r->get('/compte/connexion', [AccountController::class, 'loginForm']);
$r->post('/compte/connexion', [AccountController::class, 'login']);
$r->post('/compte/inscription', [AccountController::class, 'register']);
$r->post('/compte/deconnexion', [AccountController::class, 'logout']);
$r->get('/compte/confirmer/{token}', [AccountController::class, 'verify']);
$r->post('/compte/confirmation', [AccountController::class, 'resendVerification']);
$r->get('/compte/mot-de-passe-oublie', [AccountController::class, 'forgotForm']);
$r->post('/compte/mot-de-passe-oublie', [AccountController::class, 'forgot']);
$r->get('/compte/nouveau-mot-de-passe/{token}', [AccountController::class, 'resetForm']);
$r->post('/compte/nouveau-mot-de-passe/{token}', [AccountController::class, 'reset']);
$r->post('/compte/informations', [AccountController::class, 'updateProfile']);
$r->post('/compte/mot-de-passe', [AccountController::class, 'changePassword']);
$r->post('/compte/supprimer', [AccountController::class, 'destroy']);
$r->post('/commande/merci/compte', [AccountController::class, 'fromOrder']);

// Suivi de commande
$r->get('/suivi', [OrderController::class, 'trackForm']);
$r->post('/suivi', [OrderController::class, 'track']);
$r->get('/commande/{number}', [OrderController::class, 'show']);

// Pages & formulaires
$r->get('/page/{slug}', [PageController::class, 'show']);
$r->get('/contact', [PageController::class, 'contact']);
$r->post('/contact', [PageController::class, 'sendContact']);
$r->post('/newsletter', [PageController::class, 'newsletter']);

// Avis clients (lien personnel envoyé après livraison)
$r->get('/avis/{number}', [ReviewController::class, 'form']);
$r->post('/avis/{number}', [ReviewController::class, 'submit']);

// SEO & flux
$r->get('/sitemap.xml', [SeoController::class, 'sitemap']);
$r->get('/robots.txt', [SeoController::class, 'robots']);
$r->get('/flux/google-merchant.xml', [SeoController::class, 'merchantFeed']);

// Intégrations serveur-à-serveur
$r->post('/webhooks/stripe', [WebhookController::class, 'stripe']);
$r->post('/webhooks/printful/{token}', [WebhookController::class, 'printful']);
$r->get('/tache-planifiee/{token}', [CronController::class, 'run']);

// Administration
$r->get('/admin/connexion', [Admin\AuthController::class, 'loginForm']);
$r->post('/admin/connexion', [Admin\AuthController::class, 'login']);
$r->post('/admin/deconnexion', [Admin\AuthController::class, 'logout']);
$r->get('/admin/mot-de-passe-oublie', [Admin\AuthController::class, 'forgotForm']);
$r->post('/admin/mot-de-passe-oublie', [Admin\AuthController::class, 'forgot']);
$r->get('/admin/nouveau-mot-de-passe/{token}', [Admin\AuthController::class, 'resetForm']);
$r->post('/admin/nouveau-mot-de-passe/{token}', [Admin\AuthController::class, 'reset']);
$r->get('/admin', [Admin\DashboardController::class, 'index']);

$r->get('/admin/commandes', [Admin\OrdersController::class, 'index']);
$r->get('/admin/commandes/export', [Admin\OrdersController::class, 'export']);
$r->post('/admin/commandes/purger-abandonnees', [Admin\OrdersController::class, 'purgeAbandoned']);
$r->get('/admin/commandes/{id}', [Admin\OrdersController::class, 'show']);
$r->post('/admin/commandes/{id}/transmettre', [Admin\OrdersController::class, 'fulfill']);
$r->post('/admin/commandes/{id}/confirmer-printful', [Admin\OrdersController::class, 'confirmPrintful']);
$r->post('/admin/commandes/{id}/synchroniser', [Admin\OrdersController::class, 'sync']);
$r->post('/admin/commandes/{id}/expedier', [Admin\OrdersController::class, 'ship']);
$r->post('/admin/commandes/{id}/rembourser', [Admin\OrdersController::class, 'refund']);
$r->post('/admin/commandes/{id}/note', [Admin\OrdersController::class, 'note']);
$r->post('/admin/commandes/{id}/annuler', [Admin\OrdersController::class, 'cancel']);
$r->post('/admin/commandes/{id}/supprimer', [Admin\OrdersController::class, 'destroy']);
$r->post('/admin/commandes/{id}/portrait/{item}', [Admin\OrdersController::class, 'artwork']);
$r->get('/admin/photo/{name}', [Admin\OrdersController::class, 'photo']);
$r->get('/admin/portrait-ia/{token}', [Admin\OrdersController::class, 'aiArtwork']);

$r->get('/admin/produits', [Admin\ProductsController::class, 'index']);
$r->get('/admin/produits/nouveau', [Admin\ProductsController::class, 'create']);
$r->post('/admin/produits/nouveau', [Admin\ProductsController::class, 'store']);
$r->get('/admin/produits/printful', [Admin\ProductsController::class, 'printfulList']);
$r->get('/admin/produits/catalogue-printful', [Admin\ProductsController::class, 'printfulCatalog']);
$r->post('/admin/produits/printful/{id}', [Admin\ProductsController::class, 'printfulImport']);
$r->get('/admin/produits/{id}', [Admin\ProductsController::class, 'edit']);
$r->post('/admin/produits/{id}', [Admin\ProductsController::class, 'update']);
$r->post('/admin/produits/{id}/supprimer', [Admin\ProductsController::class, 'destroy']);
$r->get('/admin/produits/{id}/apercu-impression', [Admin\ProductsController::class, 'printPreview']);
$r->get('/admin/produits/{id}/fichier-impression', [Admin\ProductsController::class, 'downloadDesign']);

$r->get('/admin/avis', [Admin\ReviewsController::class, 'index']);
$r->post('/admin/avis/{id}/{action}', [Admin\ReviewsController::class, 'action']);

$r->get('/admin/categories', [Admin\CategoriesController::class, 'index']);
$r->post('/admin/categories', [Admin\CategoriesController::class, 'save']);
$r->post('/admin/categories/{id}/supprimer', [Admin\CategoriesController::class, 'destroy']);

$r->get('/admin/accueil', [Admin\HomePageController::class, 'index']);
$r->post('/admin/accueil', [Admin\HomePageController::class, 'update']);

$r->get('/admin/pages', [Admin\PagesController::class, 'index']);
$r->get('/admin/pages/{id}', [Admin\PagesController::class, 'edit']);
$r->post('/admin/pages/{id}', [Admin\PagesController::class, 'update']);

$r->get('/admin/clients', [Admin\CustomersController::class, 'index']);
$r->get('/admin/newsletter/export', [Admin\CustomersController::class, 'exportNewsletter']);
$r->get('/admin/messages', [Admin\CustomersController::class, 'messages']);

$r->get('/admin/reglages', [Admin\SettingsController::class, 'index']);
$r->post('/admin/reglages', [Admin\SettingsController::class, 'update']);
$r->post('/admin/reglages/mot-de-passe', [Admin\SettingsController::class, 'password']);
$r->get('/admin/mise-a-jour', [Admin\UpdateController::class, 'index']);
$r->post('/admin/mise-a-jour', [Admin\UpdateController::class, 'upload']);
$r->get('/admin/mise-a-jour/finaliser', [Admin\UpdateController::class, 'finalize']);
$r->post('/admin/mise-a-jour/migrations', [Admin\UpdateController::class, 'migrate']);
$r->post('/admin/mise-a-jour/sauvegarde-base', [Admin\UpdateController::class, 'backupDatabase']);
$r->get('/admin/mise-a-jour/sauvegarde-base/{name}', [Admin\UpdateController::class, 'downloadDatabase']);
$r->post('/admin/mise-a-jour/restaurer/{name}', [Admin\UpdateController::class, 'restore']);
$r->get('/admin/integrations', [Admin\SettingsController::class, 'integrations']);
$r->post('/admin/integrations/{action}', [Admin\SettingsController::class, 'integrationAction']);

return $r;
