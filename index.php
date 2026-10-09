<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

/** Table de routage : route => [fichier contrôleur, fonction]. */
$routes = [
    // Authentification
    'login'                 => ['auth', 'auth_login'],
    'register'              => ['auth', 'auth_register'],
    'logout'                => ['auth', 'auth_logout'],
    'forgot'                => ['auth', 'auth_forgot'],
    'reset'                 => ['auth', 'auth_reset'],
    'profile'               => ['auth', 'auth_profile'],
    'login/2fa'             => ['auth', 'auth_2fa'],

    // Espace salarié (par centre)
    'dashboard'             => ['user', 'user_dashboard'],
    'catalog'               => ['catalog', 'catalog_index'],
    'product'               => ['catalog', 'catalog_product'],
    'labels'                => ['catalog', 'product_labels'],
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
    'stock'                 => ['stock', 'stock_index'],
    'stock/save'            => ['stock', 'stock_save'],
    'stock/exit'            => ['stock', 'stock_exit'],
    'stock/add'             => ['stock', 'stock_add'],
    'stock/remove'          => ['stock', 'stock_remove'],
    'stock/history'         => ['stock', 'stock_history'],
    'stock/advice/apply'    => ['stock', 'stock_advice_apply'],
    'stock/cycle'           => ['stock', 'stock_cycle'],
    'stock/location'        => ['stock', 'stock_location'],
    'stock/move/edit'       => ['stock', 'stock_move_edit'],
    'stock/move/delete'     => ['stock', 'stock_move_delete_action'],
    'stock/transfer'        => ['stock', 'stock_transfer_action'],
    'api/barcode'           => ['stock', 'api_barcode'],
    'notifications'         => ['stock', 'notifications_index'],
    'notifications/open'    => ['stock', 'notifications_open'],
    'api/notifications'     => ['stock', 'api_notifications'],
    'suggest'               => ['suggestions', 'suggest_form'],
    'suggest/cart'          => ['suggestions', 'suggest_update_cart'],
    'approvals'             => ['manager', 'approvals_index'],
    'approvals/decide'      => ['manager', 'approval_decide'],
    'kits'                  => ['kits', 'kits_index'],
    'kit'                   => ['kits', 'kit_view'],
    'kit/save'              => ['kits', 'kit_save'],
    'kit/to-cart'           => ['kits', 'kit_to_cart'],
    'kit/delete'            => ['kits', 'kit_delete'],
    'kit/from-cart'         => ['kits', 'kit_from_cart'],
    'stock/reorder'         => ['kits', 'stock_reorder'],
    'stock/quick'           => ['kits', 'stock_quick'],
    'stock/count-one'       => ['kits', 'stock_count_one'],
    'support'               => ['support', 'support_page'],
    'api/support'           => ['support', 'api_support_ask'],
    'api/support/live'      => ['support', 'api_support_live'],
    'videos'                => ['videos', 'videos_page'],
    'video/file'            => ['videos', 'video_file'],
    'api/welcome-video'     => ['videos', 'api_welcome_video'],
    'receptions'            => ['reception', 'reception_index'],
    'reception'             => ['reception', 'reception_view'],
    'reception/save'        => ['reception', 'reception_save'],

    // Espace administrateur
    'admin'                 => ['admin_dashboard', 'admin_dashboard'],
    'admin/direction'       => ['admin_dashboard', 'admin_direction'],
    'admin/rgpd'            => ['admin_dashboard', 'admin_rgpd'],
    'admin/videos'          => ['videos', 'admin_videos'],
    'admin/direction/pdf'   => ['admin_dashboard', 'admin_direction_pdf'],
    'admin/requests'        => ['admin_orders', 'admin_requests'],
    'admin/requests/transfer' => ['admin_orders', 'admin_request_transfer'],
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
    'admin/products/delete' => ['admin_catalog', 'admin_products_delete'],
    'admin/products/export' => ['admin_catalog', 'admin_products_export'],
    'admin/categories'      => ['admin_catalog', 'admin_categories'],
    'admin/contracts'       => ['contracts', 'admin_contracts'],
    'admin/contract'        => ['contracts', 'admin_contract'],
    'admin/contract/file'   => ['contracts', 'admin_contract_file'],
    'admin/centers'         => ['admin_settings', 'admin_centers'],
    'admin/center'          => ['admin_settings', 'admin_center_edit'],
    'admin/users'           => ['admin_settings', 'admin_users'],
    'admin/user'            => ['admin_settings', 'admin_user_edit'],
    'admin/users/delete'    => ['admin_settings', 'admin_users_delete'],
    'admin/cleanup'         => ['admin_tools', 'admin_cleanup'],
    'admin/deadlines'       => ['admin_settings', 'admin_deadlines'],
    'admin/settings'        => ['admin_settings', 'admin_settings'],
    'admin/suggestions'     => ['suggestions', 'admin_suggestions'],
    'admin/suggestion'      => ['suggestions', 'admin_suggestion'],
    'admin/suggestion/add'  => ['suggestions', 'admin_suggestion_add'],
    'admin/suggestion/link' => ['suggestions', 'admin_suggestion_link'],
    'admin/suggestion/reject' => ['suggestions', 'admin_suggestion_reject'],
    'admin/compare'         => ['admin_purchasing', 'admin_compare'],
    'admin/requests/switch' => ['admin_purchasing', 'admin_request_switch'],
    'admin/requests/optimize' => ['admin_purchasing', 'admin_requests_optimize'],
    'admin/po/create-group' => ['admin_purchasing', 'admin_po_create_group'],
    'admin/order-group'     => ['admin_purchasing', 'admin_order_group'],
    'admin/order-group/ordered' => ['admin_purchasing', 'admin_order_group_ordered'],
    'admin/order/pdf'       => ['admin_purchasing', 'admin_order_pdf'],
    'admin/order/eml'       => ['admin_purchasing', 'admin_order_eml'],
    'admin/order/send'      => ['admin_purchasing', 'admin_order_send'],
    'admin/order/invoice'   => ['admin_purchasing', 'admin_order_invoice'],
    'admin/order/invoice-file' => ['admin_purchasing', 'admin_order_invoice_file'],
    'admin/order/invoice-ai' => ['admin_purchasing', 'admin_order_invoice_ai'],
    'admin/invoices'        => ['admin_purchasing', 'admin_invoices'],
    'admin/exports'         => ['admin_purchasing', 'admin_exports'],
    'admin/exports/download' => ['admin_purchasing', 'admin_exports_download'],
    'admin/audit'           => ['admin_purchasing', 'admin_audit'],
    'admin/backup-daily'    => ['admin_purchasing', 'admin_backup_daily'],
    'admin/mail-queue'      => ['admin_purchasing', 'admin_mail_queue'],
    'admin/budgets'         => ['admin_tools', 'admin_budgets'],
    'admin/stocks'          => ['admin_tools', 'admin_stocks'],
    'admin/updates'         => ['admin_tools', 'admin_updates'],
    'admin/updates/upload'  => ['admin_tools', 'admin_updates_upload'],
    'admin/updates/remote'  => ['admin_tools', 'admin_updates_remote'],
    'admin/updates/rollback'=> ['admin_tools', 'admin_updates_rollback'],
    'admin/updates/backup'  => ['admin_tools', 'admin_updates_backup'],
    'admin/updates/download'=> ['admin_tools', 'admin_updates_download'],
    'admin/updates/delete'  => ['admin_tools', 'admin_updates_delete'],
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

// Licence expirée ou suspendue : accès coupé immédiatement, déconnexion forcée de tous les utilisateurs
if (user() && licence_blocked_now()) {
    audit('Déconnexion forcée (licence ' . (licence_status() === 'suspended' ? 'suspendue' : 'expirée') . ')', 'user', (int)user()['id']);
    logout_user();
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        json_response(['error' => 'Licence ' . (licence_status() === 'suspended' ? 'suspendue' : 'expirée') . ' : vous avez été déconnecté.', 'logout' => true], 401);
    }
    redirect('login');
}
if (!user() && $route === 'login' && licence_blocked_now()) {
    http_response_code(403);
    render('licence_blocked', ['title' => 'Accès coupé', 'notice' => licence_notice(), 'status' => licence_status()], 'layout_auth');
    exit;
}

// Double authentification exigée des administrateurs : configuration obligatoire avant toute autre page
if (is_admin() && admin_2fa_required() && !user_has_2fa(user()) && !in_array($route, ['profile', 'logout'], true)) {
    flash('info', 'Par sécurité, la double authentification est obligatoire pour les administrateurs : configurez-la ci-dessous (2 minutes).');
    redirect('profile', ['_' => 'security']);
}

// Tâches de fond (e-mails, rappels, sauvegarde) exécutées après l'envoi de la page
cron_after_request();

// Plusieurs clients partagent le code : les mises à jour sont installées par NLapps depuis sa console
if (current_instance() && str_starts_with($route, 'admin/updates')) {
    abort(403, 'Les mises à jour de votre espace sont installées par NLapps, pour tous ses clients à la fois. Votre base est sauvegardée chaque nuit.');
}

// Organisation et paramètres : réservés à l'administrateur (l'acheteur gère tout le reste du service achats)
if (preg_match('#^admin/(centers?|users?(/delete)?|settings|rgpd|audit|cleanup|updates(/.*)?|backup-daily|mail-queue|videos)$#', $route)) {
    require_superadmin();
}

[$file, $fn] = $routes[$route];
require_once APP . '/controllers/' . $file . '.php';
$fn();
