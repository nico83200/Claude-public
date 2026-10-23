<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\OrderService;

final class OrderController
{
    public function trackForm(): void
    {
        view('shop/track', ['pageTitle' => 'Suivre ma commande', 'noindex' => true]);
    }

    public function track(): void
    {
        $number = strtoupper(trim((string) input('number')));
        $email = mb_strtolower(trim((string) input('email')));
        $order = Database::one("SELECT * FROM orders WHERE number = ? AND email = ? AND status <> 'pending'", [$number, $email]);
        if (!$order) {
            remember_input(['number' => $number, 'email' => $email]);
            flash('error', 'Aucune commande ne correspond à ces informations.');
            redirect('suivi');
        }
        redirect(OrderService::trackingUrl($order));
    }

    public function show(string $number): void
    {
        $order = OrderService::findByNumber($number);
        if (!$order || !hash_equals($order['access_token'], (string) input('t', ''))) {
            abort(404);
        }
        view('shop/order', [
            'pageTitle' => 'Commande ' . $order['number'],
            'noindex'   => true,
            'order'     => $order,
            'items'     => OrderService::items((int) $order['id']),
            'address'   => json_field($order['shipping_address']),
        ]);
    }
}
