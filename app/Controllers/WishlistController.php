<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Account;
use App\Services\Wishlist;

final class WishlistController
{
    /** Page « Mes favoris » (ou liste partagée : /favoris?ids=…&partage=1&de=Léa). */
    public function show(): void
    {
        $customer = Account::current();
        $shared = input('partage') === '1';
        $ids = input('ids') !== null ? Wishlist::sanitize((string) input('ids')) : [];
        if (!$shared && $customer) {
            $ids = $ids ? Wishlist::merge((int) $customer['id'], $ids) : Wishlist::ids((int) $customer['id']);
        }
        $from = mb_substr(trim(strip_tags((string) input('de', ''))), 0, 40);
        view('shop/wishlist', [
            'pageTitle' => $shared ? 'Une liste d\'envies' . ($from !== '' ? ' de ' . $from : '') : 'Mes favoris',
            'noindex'   => true,
            'products'  => Wishlist::products($ids),
            'shared'    => $shared,
            'from'      => $from,
            'loaded'    => input('ids') !== null || $customer !== null,
        ]);
    }

    /** Synchronisation (JSON) : la liste du navigateur rejoint celle du compte. */
    public function sync(): void
    {
        $customer = Account::current();
        if (!$customer) {
            json_response(['ok' => false, 'ids' => null]);
        }
        $ids = Wishlist::merge((int) $customer['id'], Wishlist::sanitize((array) ($_POST['ids'] ?? [])));
        foreach (Wishlist::sanitize((array) ($_POST['removed'] ?? [])) as $id) {
            Wishlist::remove((int) $customer['id'], $id);
        }
        json_response(['ok' => true, 'ids' => Wishlist::ids((int) $customer['id'])]);
    }
}
