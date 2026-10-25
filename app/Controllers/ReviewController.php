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
            'pageTitle' => 'Donne ton avis',
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
            // Photo facultative (l'article porté, ou avec son cheval) : réduite et sans métadonnées
            $photo = null;
            $f = $_FILES['review_photo'] ?? null;
            if ($f && isset($f['error'][$productId]) && $f['error'][$productId] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $photo = \App\Services\HomeImages::store([
                        'error' => $f['error'][$productId], 'size' => $f['size'][$productId], 'tmp_name' => $f['tmp_name'][$productId], 'name' => $f['name'][$productId],
                    ], 'uploads/reviews', 1600);
                } catch (\RuntimeException $e) {
                    flash('error', 'Ta photo n\'a pas pu être ajoutée : ' . $e->getMessage());
                }
            }
            $saved += Reviews::submit(
                $order,
                (int) $productId,
                (int) $data['rating'],
                (string) input('author_name', Reviews::displayName($order['customer_name'])),
                (string) ($data['title'] ?? ''),
                (string) ($data['body'] ?? ''),
                $photo
            ) ? 1 : 0;
        }
        if ($saved) {
            OrderService::event((int) $order['id'], 'review', $saved . ' avis reçu(s).');
            flash('success', 'Merci infiniment pour ton avis ! Il sera publié après une rapide vérification.');
        } else {
            flash('error', 'Choisis au moins une note (de 1 à 5 étoiles).');
        }
        redirect(Reviews::reviewUrl($order));
    }
}
