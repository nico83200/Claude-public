<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;

final class PagesController extends BaseController
{
    public function index(): void
    {
        $this->render('pages/index', ['pageTitle' => 'Pages', 'pages' => Database::all('SELECT * FROM pages ORDER BY id')]);
    }

    public function edit(string $id): void
    {
        $page = $id === 'nouvelle'
            ? ['id' => null, 'slug' => '', 'title' => '', 'content' => '', 'meta_description' => '', 'in_footer' => 1]
            : (Database::one('SELECT * FROM pages WHERE id = ?', [(int) $id]) ?? abort(404));
        $this->render('pages/form', ['pageTitle' => $page['id'] ? 'Modifier « ' . $page['title'] . ' »' : 'Nouvelle page', 'page' => $page]);
    }

    public function update(string $id): void
    {
        $title = trim((string) input('title'));
        if ($title === '') {
            flash('error', 'Le titre est obligatoire.');
            redirect('admin/pages/' . $id);
        }
        $pageId = $id === 'nouvelle' ? 0 : (int) $id;
        $slug = slugify((string) (input('slug') ?: $title));
        if (Database::value('SELECT id FROM pages WHERE slug = ? AND id <> ?', [$slug, $pageId])) {
            flash('error', 'Cette adresse (slug) est déjà utilisée.');
            redirect('admin/pages/' . $id);
        }
        $data = [
            'title'            => mb_substr($title, 0, 190),
            'slug'             => $slug,
            'content'          => \App\Services\RichText::render((string) input('content')),
            'meta_description' => mb_substr((string) input('meta_description'), 0, 300),
            'in_footer'        => input('in_footer') ? 1 : 0,
            'updated_at'       => now(),
        ];
        if ($pageId) {
            Database::update('pages', $data, 'id = :id', ['id' => $pageId]);
        } else {
            $pageId = Database::insert('pages', $data);
        }
        flash('success', 'Page enregistrée.');
        redirect('admin/pages/' . $pageId);
    }
}
