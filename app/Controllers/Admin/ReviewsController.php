<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;

final class ReviewsController extends BaseController
{
    public function index(): void
    {
        $status = in_array(input('statut'), ['pending', 'approved', 'rejected'], true) ? input('statut') : 'pending';
        $this->render('reviews', [
            'pageTitle' => 'Avis clients',
            'status'    => $status,
            'counts'    => array_column(Database::all('SELECT status, COUNT(*) AS n FROM reviews GROUP BY status'), 'n', 'status'),
            'reviews'   => Database::all(
                'SELECT r.*, p.name AS product_name, p.slug AS product_slug, o.number AS order_number
                 FROM reviews r JOIN products p ON p.id = r.product_id LEFT JOIN orders o ON o.id = r.order_id
                 WHERE r.status = ? ORDER BY r.id DESC LIMIT 200',
                [$status]
            ),
        ]);
    }

    public function action(string $id, string $action): void
    {
        match ($action) {
            'publier' => Database::update('reviews', ['status' => 'approved'], 'id = :id', ['id' => (int) $id]),
            'refuser' => Database::update('reviews', ['status' => 'rejected'], 'id = :id', ['id' => (int) $id]),
            'supprimer' => Database::query('DELETE FROM reviews WHERE id = ?', [(int) $id]),
            default => abort(404),
        };
        flash('success', 'Avis mis à jour.');
        back('/admin/avis');
    }
}
