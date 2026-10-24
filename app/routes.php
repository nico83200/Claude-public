<?php
declare(strict_types=1);

use App\Controllers\Admin;
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

// Panier & paiement
$r->get('/panier', [CartController::class, 'show']);
$r->get('/panier/apercu', [CartController::class, 'drawer']);
$r->get('/panier/photo/{name}', [CartController::class, 'photo']);
$r->post('/panier/ajouter', [CartController::class, 'add']);
$r->post('/panier/modifier', [CartController::class, 'update']);
$r->post('/panier/supprimer', [CartController::class, 'remove']);
$r->post('/commande', [CheckoutController::class, 'start']);
$r->get('/commande/merci', [CheckoutController::class, 'success']);

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
$r->get('/admin', [Admin\DashboardController::class, 'index']);

$r->get('/admin/commandes', [Admin\OrdersController::class, 'index']);
$r->get('/admin/commandes/export', [Admin\OrdersController::class, 'export']);
$r->get('/admin/commandes/{id}', [Admin\OrdersController::class, 'show']);
$r->post('/admin/commandes/{id}/transmettre', [Admin\OrdersController::class, 'fulfill']);
$r->post('/admin/commandes/{id}/confirmer-printful', [Admin\OrdersController::class, 'confirmPrintful']);
$r->post('/admin/commandes/{id}/synchroniser', [Admin\OrdersController::class, 'sync']);
$r->post('/admin/commandes/{id}/expedier', [Admin\OrdersController::class, 'ship']);
$r->post('/admin/commandes/{id}/rembourser', [Admin\OrdersController::class, 'refund']);
$r->post('/admin/commandes/{id}/note', [Admin\OrdersController::class, 'note']);
$r->post('/admin/commandes/{id}/portrait/{item}', [Admin\OrdersController::class, 'artwork']);
$r->get('/admin/photo/{name}', [Admin\OrdersController::class, 'photo']);

$r->get('/admin/produits', [Admin\ProductsController::class, 'index']);
$r->get('/admin/produits/nouveau', [Admin\ProductsController::class, 'create']);
$r->post('/admin/produits/nouveau', [Admin\ProductsController::class, 'store']);
$r->get('/admin/produits/printful', [Admin\ProductsController::class, 'printfulList']);
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
$r->post('/admin/mise-a-jour/restaurer/{name}', [Admin\UpdateController::class, 'restore']);
$r->get('/admin/integrations', [Admin\SettingsController::class, 'integrations']);
$r->post('/admin/integrations/{action}', [Admin\SettingsController::class, 'integrationAction']);

return $r;
