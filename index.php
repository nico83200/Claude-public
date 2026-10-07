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
    'admin/products/delete' => ['admin_catalog', 'admin_products_delete'],
    'admin/products/export' => ['admin_catalog', 'admin_products_export'],
    'admin/categories'      => ['admin_catalog', 'admin_categories'],
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

// Tâches de fond (e-mails, rappels, sauvegarde) exécutées après l'envoi de la page
cron_after_request();

[$file, $fn] = $routes[$route];
require_once APP . '/controllers/' . $file . '.php';
$fn();
