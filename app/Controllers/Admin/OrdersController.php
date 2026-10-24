<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Services\AiPortrait;
use App\Services\OrderService;
use App\Services\Printful;
use App\Services\Stripe;
use App\Services\Upload;

final class OrdersController extends BaseController
{
    private function query(): array
    {
        $status = (string) input('statut', '');
        $q = trim((string) input('q', ''));
        $where = [];
        $params = [];
        if ($status === 'abandoned') {
            $where[] = "o.status IN ('pending','canceled') AND o.paid_at IS NULL";
        } elseif ($status === 'canceled') {
            $where[] = "o.status = 'canceled' AND o.paid_at IS NOT NULL";
        } elseif ($status === 'attention') {
            $where[] = "o.fulfillment_status IN ('error','draft','pending','artwork') AND o.status IN ('paid','in_production')";
        } elseif ($status !== '') {
            $where[] = 'o.status = ?';
            $params[] = $status;
        } else {
            $where[] = 'o.paid_at IS NOT NULL';
        }
        if ($q !== '') {
            $where[] = '(o.number LIKE ? OR o.email LIKE ? OR o.customer_name LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        return [implode(' AND ', $where), $params, $status, $q];
    }

    public function index(): void
    {
        [$where, $params, $status, $q] = $this->query();
        $page = max(1, (int) input('page', 1));
        $total = (int) Database::value("SELECT COUNT(*) FROM orders o WHERE $where", $params);
        $orders = Database::all(
            "SELECT o.*, (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS item_count
             FROM orders o WHERE $where ORDER BY o.id DESC LIMIT 50 OFFSET " . (($page - 1) * 50),
            $params
        );
        $this->render('orders/index', [
            'pageTitle' => 'Commandes',
            'orders'    => $orders,
            'status'    => $status,
            'q'         => $q,
            'page'      => $page,
            'pages'     => (int) ceil($total / 50),
            'total'     => $total,
        ]);
    }

    public function export(): void
    {
        [$where, $params] = $this->query();
        $rows = Database::all("SELECT * FROM orders o WHERE $where ORDER BY o.id DESC", $params);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="commandes-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Numéro', 'Date paiement', 'Statut', 'Client', 'E-mail', 'Pays', 'Sous-total', 'Réduction', 'Livraison', 'Total', 'Printful', 'Suivi'], ';');
        foreach ($rows as $o) {
            $addr = json_field($o['shipping_address']);
            fputcsv($out, [
                $o['number'], $o['paid_at'], order_status_label($o['status']), $o['customer_name'], $o['email'], $addr['country'] ?? '',
                number_format($o['subtotal'] / 100, 2, ',', ''), number_format($o['discount'] / 100, 2, ',', ''),
                number_format($o['shipping'] / 100, 2, ',', ''), number_format($o['total'] / 100, 2, ',', ''),
                $o['printful_order_id'], $o['tracking_number'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    private function order(string $id): array
    {
        return OrderService::find((int) $id) ?? abort(404);
    }

    public function show(string $id): void
    {
        $order = $this->order($id);
        $this->render('orders/show', [
            'pageTitle' => 'Commande ' . $order['number'],
            'order'     => $order,
            'items'     => OrderService::items((int) $order['id']),
            'events'    => OrderService::events((int) $order['id']),
            'address'   => json_field($order['shipping_address']),
        ]);
    }

    public function fulfill(string $id): void
    {
        $order = $this->order($id);
        if (!in_array($order['status'], ['paid', 'in_production'], true)) {
            flash('error', 'Seule une commande payée peut être transmise.');
            redirect('admin/commandes/' . $id);
        }
        Database::update('orders', ['fulfillment_status' => 'pending', 'fulfillment_attempts' => 0], 'id = :id AND fulfillment_status IN (\'error\',\'pending\')', ['id' => $order['id']]);
        OrderService::event((int) $order['id'], 'admin', 'Transmission à Printful relancée manuellement.');
        OrderService::fulfill((int) $order['id'])
            ? flash('success', 'Commande transmise à Printful.')
            : flash('error', 'Échec : ' . (OrderService::find((int) $order['id'])['last_error'] ?? 'voir l\'historique'));
        redirect('admin/commandes/' . $id);
    }

    public function confirmPrintful(string $id): void
    {
        $order = $this->order($id);
        try {
            Printful::fromConfig()->confirmOrder('@' . $order['number']);
            Database::update('orders', ['fulfillment_status' => 'submitted', 'updated_at' => now()], 'id = :id', ['id' => $order['id']]);
            OrderService::event((int) $order['id'], 'fulfillment', 'Brouillon Printful confirmé : production lancée.');
            flash('success', 'Commande confirmée chez Printful, la production démarre.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/commandes/' . $id);
    }

    public function sync(string $id): void
    {
        $order = $this->order($id);
        try {
            OrderService::syncFromPrintful($order);
            flash('success', 'Statut synchronisé avec Printful.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/commandes/' . $id);
    }

    public function ship(string $id): void
    {
        $order = $this->order($id);
        OrderService::markShipped($order, [
            'carrier'         => mb_substr((string) input('carrier'), 0, 100),
            'tracking_number' => mb_substr((string) input('tracking_number'), 0, 190) ?: null,
            'tracking_url'    => filter_var(input('tracking_url'), FILTER_VALIDATE_URL) ?: null,
        ]);
        flash('success', 'Commande marquée comme expédiée, le client a été prévenu par e-mail.');
        redirect('admin/commandes/' . $id);
    }

    public function refund(string $id): void
    {
        $order = $this->order($id);
        if (!$order['stripe_payment_intent']) {
            flash('error', 'Aucun paiement Stripe associé.');
            redirect('admin/commandes/' . $id);
        }
        $amount = input('amount') !== '' && input('amount') !== null ? to_cents(input('amount')) : null;
        try {
            Stripe::fromConfig()->refund($order['stripe_payment_intent'], $amount);
            OrderService::event((int) $order['id'], 'refund', 'Remboursement demandé à Stripe' . ($amount ? ' : ' . money($amount) : ' (total)') . '.');
            if ($amount === null || $amount >= (int) $order['total']) {
                Database::update('orders', ['status' => 'refunded', 'updated_at' => now()], 'id = :id', ['id' => $order['id']]);
            }
            flash('success', 'Remboursement effectué. Pensez à annuler la commande chez Printful si elle n\'est pas encore produite.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/commandes/' . $id);
    }

    public function cancel(string $id): void
    {
        $order = $this->order($id);
        try {
            $order = OrderService::cancel((int) $order['id'], input('refund') === '1', input('notify') === '1', mb_substr(trim((string) input('reason')), 0, 300));
            flash('success', 'Commande ' . $order['number'] . ' annulée' . ($order['status'] === 'refunded' ? ' et remboursée' : '') . '.');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/commandes/' . $id);
    }

    public function destroy(string $id): void
    {
        $order = $this->order($id);
        try {
            OrderService::delete((int) $order['id']);
            flash('success', 'Commande ' . $order['number'] . ' supprimée.');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('admin/commandes/' . $id);
        }
        redirect('admin/commandes?statut=abandoned');
    }

    public function purgeAbandoned(): void
    {
        $count = OrderService::purgeAbandoned(max(0, (int) input('days', 7)));
        flash('success', $count . ' panier(s) abandonné(s) supprimé(s).');
        redirect('admin/commandes?statut=abandoned');
    }

    /** Dépôt du portrait illustré final pour un article ; la commande part en fabrication quand tout est prêt. */
    public function artwork(string $id, string $itemId): void
    {
        $order = $this->order($id);
        $item = Database::one("SELECT * FROM order_items WHERE id = ? AND order_id = ? AND personalization_mode = 'portrait'", [(int) $itemId, $order['id']]) ?? abort(404);
        $file = Upload::files('artwork')[0] ?? null;
        if (!$file) {
            flash('error', 'Choisissez le fichier du portrait (PNG ou JPG haute définition).');
            redirect('admin/commandes/' . $id);
        }
        try {
            $path = Upload::printFile($file);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('admin/commandes/' . $id);
        }
        Database::update('order_items', ['print_file_url' => url($path)], 'id = :id', ['id' => $item['id']]);
        OrderService::event((int) $order['id'], 'fulfillment', 'Portrait illustré déposé pour « ' . $item['product_name'] . ' ».');

        $missing = (int) Database::value("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND personalization_mode = 'portrait' AND (print_file_url IS NULL OR print_file_url = '')", [$order['id']]);
        if ($missing === 0 && $order['fulfillment_status'] === 'artwork') {
            Database::update('orders', ['fulfillment_status' => 'pending'], 'id = :id', ['id' => $order['id']]);
            OrderService::fulfill((int) $order['id'])
                ? flash('success', 'Portrait enregistré : la commande est transmise à Printful.')
                : flash('error', 'Portrait enregistré, mais la transmission a échoué : ' . (OrderService::find((int) $order['id'])['last_error'] ?? 'voir l\'historique'));
        } else {
            flash('success', 'Portrait enregistré.' . ($missing ? " Encore $missing portrait(s) à déposer." : ''));
        }
        redirect('admin/commandes/' . $id);
    }

    /** Photo originale envoyée par la cliente (pour l'illustratrice). */
    public function photo(string $name): void
    {
        $path = Upload::customerPhotoPath($name) ?? abort(404);
        if (input('vignette') === '1') {
            \App\Controllers\CartController::sendThumbnail($path, 240);
        }
        header('Content-Type: image/jpeg');
        header('Content-Disposition: attachment; filename="photo-cheval-' . $name . '"');
        readfile($path);
        exit;
    }

    /** Dessin produit par l'IA pour un portrait commandé (contrôle avant impression, retouche éventuelle). */
    public function aiArtwork(string $token): void
    {
        $row = AiPortrait::find($token) ?? abort(404);
        $path = AiPortrait::artworkPath($row) ?? abort(404);
        if (input('vignette') === '1') {
            $img = imagecreatefrompng($path);
            $thumb = gd_resize($img, 240);
            header('Content-Type: image/jpeg');
            header('Cache-Control: private, max-age=3600');
            imagejpeg($thumb, null, 82);
            exit;
        }
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="portrait-ia-' . $row['token'] . '.png"');
        readfile($path);
        exit;
    }

    public function note(string $id): void
    {
        $order = $this->order($id);
        $note = trim((string) input('note'));
        if ($note !== '') {
            OrderService::event((int) $order['id'], 'note', mb_substr($note, 0, 2000) . ' — ' . $this->admin['email']);
        }
        redirect('admin/commandes/' . $id);
    }
}
