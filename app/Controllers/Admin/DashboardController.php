<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Services\Printful;

final class DashboardController extends BaseController
{
    private const PAID = "('paid','in_production','shipped')";

    public function index(): void
    {
        $monthStart = date('Y-m-01 00:00:00');
        $last30 = date('Y-m-d H:i:s', time() - 30 * 86400);

        $stats = [
            'revenue_month' => (int) Database::value('SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ' . self::PAID . ' AND paid_at >= ?', [$monthStart]),
            'orders_month'  => (int) Database::value('SELECT COUNT(*) FROM orders WHERE status IN ' . self::PAID . ' AND paid_at >= ?', [$monthStart]),
            'revenue_30'    => (int) Database::value('SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ' . self::PAID . ' AND paid_at >= ?', [$last30]),
            'orders_30'     => (int) Database::value('SELECT COUNT(*) FROM orders WHERE status IN ' . self::PAID . ' AND paid_at >= ?', [$last30]),
            'abandoned_30'  => (int) Database::value("SELECT COUNT(*) FROM orders WHERE status = 'canceled' AND paid_at IS NULL AND created_at >= ?", [$last30]),
            'subscribers'   => (int) Database::value('SELECT COUNT(*) FROM newsletter_subscribers'),
            'products'      => (int) Database::value('SELECT COUNT(*) FROM products WHERE active = 1'),
            'unread'        => (int) Database::value('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0'),
        ];
        $stats['aov'] = $stats['orders_30'] ? intdiv($stats['revenue_30'], $stats['orders_30']) : 0;

        // Chiffre d'affaires des 14 derniers jours (graphique)
        $daily = [];
        for ($i = 13; $i >= 0; $i--) {
            $daily[date('Y-m-d', strtotime("-$i days"))] = 0;
        }
        $rows = Database::all('SELECT paid_at, total FROM orders WHERE status IN ' . self::PAID . ' AND paid_at >= ?', [date('Y-m-d 00:00:00', strtotime('-13 days'))]);
        foreach ($rows as $r) {
            $day = substr($r['paid_at'], 0, 10);
            if (isset($daily[$day])) {
                $daily[$day] += (int) $r['total'];
            }
        }

        $this->render('dashboard', [
            'pageTitle'  => 'Tableau de bord',
            'stats'      => $stats,
            'daily'      => $daily,
            'attention'  => Database::all("SELECT * FROM orders WHERE (fulfillment_status IN ('error','draft','pending','artwork') AND status IN ('paid','in_production')) ORDER BY id DESC LIMIT 10"),
            'recent'     => Database::all("SELECT * FROM orders WHERE paid_at IS NOT NULL ORDER BY id DESC LIMIT 8"),
            'topProducts' => Database::all('SELECT i.product_name, SUM(i.quantity) AS qty, SUM(i.quantity * i.unit_price) AS revenue FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.status IN ' . self::PAID . ' AND o.paid_at >= ? GROUP BY i.product_name ORDER BY qty DESC LIMIT 5', [$last30]),
            'checklist'  => self::checklist(),
        ]);
    }

    public static function checklist(): array
    {
        $cronLast = @file_get_contents(STORAGE_PATH . '/cache/cron-last-run');
        $legalFilled = !str_contains((string) setting('company_siret'), '[');
        return [
            ['Paiement Stripe configuré', (string) config('stripe.secret_key') !== '' && (string) config('stripe.webhook_secret') !== '', 'admin/integrations'],
            ['Printful connecté', Printful::configured(), 'admin/integrations'],
            ['Produits reliés à Printful', (int) Database::value('SELECT COUNT(*) FROM product_variants WHERE active = 1 AND printful_variant_id IS NULL AND printful_sync_variant_id IS NULL') === 0, 'admin/produits'],
            ['E-mails configurés', (string) config('mail.username') !== '' || config('mail.transport') !== 'smtp', 'admin/integrations'],
            ['Tâche Cron active (< 1 h)', $cronLast && strtotime($cronLast) > time() - 3600, 'admin/integrations'],
            ['Mentions légales complétées', $legalFilled, 'admin/reglages'],
        ];
    }
}
