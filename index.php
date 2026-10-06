<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

/** Table de routage : route => [fichier contrôleur, fonction]. */
$routes = [
    // Authentification
    'login'                 => ['auth', 'auth_login'],
    'register'              => ['auth', 'auth_register'],
    'logout'                => ['auth', 'auth_logout'],
    'profile'               => ['auth', 'auth_profile'],

    // Espace salarié (par centre)
    'dashboard'             => ['user', 'user_dashboard'],
    'catalog'               => ['catalog', 'catalog_index'],
    'product'               => ['catalog', 'catalog_product'],
    'api/search'            => ['catalog', 'api_search'],
    'api/ai-search'         => ['catalog', 'api_ai_search'],
    'favorite/toggle'       => ['catalog', 'favorite_toggle'],
    'cart'                  => ['cart', 'cart_index'],
    'cart/add'              => ['cart', 'cart_add_action'],
    'cart/update'           => ['cart', 'cart_update_action'],
    'cart/remove'           => ['cart', 'cart_remove_action'],
    'cart/submit'           => ['cart', 'cart_submit_action'],
    'requests'              => ['user', 'user_requests'],
    'request/cancel-line'   => ['user', 'user_cancel_line'],
    'request/reorder'       => ['user', 'user_reorder'],
    'receptions'            => ['reception', 'reception_index'],
    'reception'             => ['reception', 'reception_view'],
    'reception/save'        => ['reception', 'reception_save'],

    // Espace administrateur
    'admin'                 => ['admin_dashboard', 'admin_dashboard'],
    'admin/requests'        => ['admin_orders', 'admin_requests'],
    'admin/requests/refuse' => ['admin_orders', 'admin_refuse_line'],
    'admin/po/create'       => ['admin_orders', 'admin_po_create'],
    'admin/orders'          => ['admin_orders', 'admin_orders'],
    'admin/order'           => ['admin_orders', 'admin_order'],
    'admin/order/lines'     => ['admin_orders', 'admin_order_lines'],
    'admin/order/add-line'  => ['admin_orders', 'admin_order_add_line'],
    'admin/order/status'    => ['admin_orders', 'admin_order_status'],
    'admin/order/print'     => ['admin_orders', 'admin_order_print'],
    'admin/order/csv'       => ['admin_orders', 'admin_order_csv'],
    'admin/suppliers'       => ['admin_catalog', 'admin_suppliers'],
    'admin/supplier'        => ['admin_catalog', 'admin_supplier_edit'],
    'admin/products'        => ['admin_catalog', 'admin_products'],
    'admin/product'         => ['admin_catalog', 'admin_product_edit'],
    'admin/product/toggle'  => ['admin_catalog', 'admin_product_toggle'],
    'admin/products/import' => ['admin_catalog', 'admin_products_import'],
    'admin/products/export' => ['admin_catalog', 'admin_products_export'],
    'admin/categories'      => ['admin_catalog', 'admin_categories'],
    'admin/centers'         => ['admin_settings', 'admin_centers'],
    'admin/center'          => ['admin_settings', 'admin_center_edit'],
    'admin/users'           => ['admin_settings', 'admin_users'],
    'admin/user'            => ['admin_settings', 'admin_user_edit'],
    'admin/deadlines'       => ['admin_settings', 'admin_deadlines'],
    'admin/settings'        => ['admin_settings', 'admin_settings'],
];

$route = (string)($_GET['r'] ?? '');
if ($route === '') {
    $route = !user() ? 'login' : (is_admin() ? 'admin' : 'dashboard');
}
if (!isset($routes[$route])) {
    abort(404);
}

if (is_post()) {
    csrf_check();
}

[$file, $fn] = $routes[$route];
require APP . '/controllers/' . $file . '.php';
$fn();
