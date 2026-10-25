<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Services\Catalog;

final class CategoriesController extends BaseController
{
    public function index(): void
    {
        $this->render('categories', ['pageTitle' => 'Catégories', 'categories' => Catalog::categories(false)]);
    }

    public function save(): void
    {
        $id = (int) input('id');
        $name = trim((string) input('name'));
        if ($name === '') {
            flash('error', 'Le nom est obligatoire.');
            redirect('admin/categories');
        }
        $slug = slugify((string) (input('slug') ?: $name));
        if (Database::value('SELECT id FROM categories WHERE slug = ? AND id <> ?', [$slug, $id])) {
            $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        }
        $data = [
            'name'        => mb_substr($name, 0, 120),
            'slug'        => $slug,
            'description' => (string) input('description'),
            'position'    => (int) input('position'),
            'active'      => input('active') ? 1 : 0,
        ];
        if ($id) {
            Catalog::rememberSlug((string) Database::value('SELECT slug FROM categories WHERE id = ?', [$id]), $slug);
            Database::update('categories', $data, 'id = :id', ['id' => $id]);
        } else {
            Database::insert('categories', $data);
        }
        flash('success', 'Catégorie enregistrée.');
        redirect('admin/categories');
    }

    public function destroy(string $id): void
    {
        Database::query('UPDATE products SET category_id = NULL WHERE category_id = ?', [(int) $id]);
        Database::query('DELETE FROM categories WHERE id = ?', [(int) $id]);
        flash('success', 'Catégorie supprimée (ses produits sont conservés, sans catégorie).');
        redirect('admin/categories');
    }
}
