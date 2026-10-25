<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Services\HomeImages;
use App\Services\Journal;
use App\Services\RichText;
use App\Services\Upload;

final class JournalController extends BaseController
{
    public function index(): void
    {
        $this->render('journal/index', [
            'pageTitle' => 'Journal',
            'posts'     => Database::all('SELECT * FROM journal_posts ORDER BY COALESCE(published_at, created_at) DESC, id DESC'),
        ]);
    }

    public function edit(string $id): void
    {
        $post = $id === 'nouveau'
            ? ['id' => null, 'slug' => '', 'title' => '', 'tag' => 'Conseils', 'excerpt' => '', 'content' => '', 'cover_image' => null,
               'product_ids' => '[]', 'meta_description' => '', 'published' => 0, 'published_at' => null]
            : (Database::one('SELECT * FROM journal_posts WHERE id = ?', [(int) $id]) ?? abort(404));
        $this->render('journal/form', [
            'pageTitle' => $post['id'] ? 'Modifier « ' . $post['title'] . ' »' : 'Nouvel article',
            'post'      => $post,
            'products'  => Database::all('SELECT id, name FROM products WHERE active = 1 ORDER BY name'),
        ]);
    }

    public function update(string $id): void
    {
        $postId = $id === 'nouveau' ? 0 : (int) $id;
        $existing = $postId ? (Database::one('SELECT * FROM journal_posts WHERE id = ?', [$postId]) ?? abort(404)) : null;
        $title = trim((string) input('title'));
        if ($title === '') {
            flash('error', 'Le titre est obligatoire.');
            redirect('admin/journal/' . $id);
        }
        $slug = slugify((string) (input('slug') ?: $title));
        if (Database::value('SELECT id FROM journal_posts WHERE slug = ? AND id <> ?', [$slug, $postId])) {
            $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        }
        $cover = $existing['cover_image'] ?? null;
        if (input('remove_cover') === '1') {
            HomeImages::discard($cover);
            $cover = null;
        }
        if ($file = Upload::files('cover')[0] ?? null) {
            try {
                $new = HomeImages::store($file);
                HomeImages::discard($cover);
                $cover = $new;
            } catch (\RuntimeException $e) {
                flash('error', 'Image : ' . $e->getMessage());
            }
        }
        $published = input('published') === '1';
        $date = trim((string) input('published_at'));
        $publishedAt = $date !== '' && strtotime($date) ? date('Y-m-d H:i:s', strtotime($date)) : ($existing['published_at'] ?? null);
        if ($published && !$publishedAt) {
            $publishedAt = now();
        }
        $tag = (string) input('tag');
        $data = [
            'title'            => mb_substr($title, 0, 190),
            'slug'             => $slug,
            'tag'              => in_array($tag, Journal::TAGS, true) ? $tag : null,
            'excerpt'          => mb_substr(trim((string) input('excerpt')), 0, 400),
            'content'          => RichText::render((string) input('content')),
            'cover_image'      => $cover,
            'product_ids'      => json_encode(array_values(array_map('intval', (array) ($_POST['product_ids'] ?? [])))),
            'meta_description' => mb_substr(trim((string) input('meta_description')), 0, 300),
            'published'        => $published ? 1 : 0,
            'published_at'     => $publishedAt,
            'updated_at'       => now(),
        ];
        if ($postId) {
            Database::update('journal_posts', $data, 'id = :id', ['id' => $postId]);
        } else {
            $postId = Database::insert('journal_posts', $data + ['created_at' => now()]);
        }
        flash('success', $published ? 'Article enregistré et publié.' : 'Brouillon enregistré (non visible sur le site).');
        redirect('admin/journal/' . $postId);
    }

    public function destroy(string $id): void
    {
        $post = Database::one('SELECT * FROM journal_posts WHERE id = ?', [(int) $id]) ?? abort(404);
        HomeImages::discard($post['cover_image']);
        Database::query('DELETE FROM journal_posts WHERE id = ?', [(int) $id]);
        flash('success', 'Article supprimé.');
        redirect('admin/journal');
    }
}
