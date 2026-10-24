<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\OrderService;
use App\Services\Reviews;

final class ReviewController
{
    private function order(string $number): array
    {
        $order = OrderService::findByNumber($number);
        if (!$order || !hash_equals($order['access_token'], (string) input('t', '')) || !in_array($order['status'], ['in_production', 'shipped', 'paid'], true)) {
            abort(404);
        }
        return $order;
    }

    public function form(string $number): void
    {
        $order = $this->order($number);
        view('shop/review', [
            'pageTitle' => 'Donnez votre avis',
            'noindex'   => true,
            'order'     => $order,
            'products'  => Reviews::orderProducts($order),
            'author'    => Reviews::displayName($order['customer_name']),
        ]);
    }

    public function submit(string $number): void
    {
        $order = $this->order($number);
        $saved = 0;
        foreach ((array) ($_POST['reviews'] ?? []) as $productId => $data) {
            if (!is_array($data) || empty($data['rating'])) {
                continue;
            }
            $saved += Reviews::submit(
                $order,
                (int) $productId,
                (int) $data['rating'],
                (string) input('author_name', Reviews::displayName($order['customer_name'])),
                (string) ($data['title'] ?? ''),
                (string) ($data['body'] ?? '')
            ) ? 1 : 0;
        }
        if ($saved) {
            OrderService::event((int) $order['id'], 'review', $saved . ' avis reçu(s).');
            flash('success', 'Merci infiniment pour votre avis ! Il sera publié après une rapide vérification.');
        } else {
            flash('error', 'Choisissez au moins une note (de 1 à 5 étoiles).');
        }
        redirect(Reviews::reviewUrl($order));
    }
}
