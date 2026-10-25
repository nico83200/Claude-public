<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Journal;

final class JournalController
{
    public function index(): void
    {
        $tag = (string) input('theme', '');
        $tag = in_array($tag, Journal::TAGS, true) ? $tag : '';
        view('shop/journal/index', [
            'pageTitle'       => $tag !== '' ? $tag . ' — Le journal' : 'Le journal du club : conseils et idées cadeaux',
            'metaDescription' => 'Conseils de cavalières, idées cadeaux, entretien de tes vêtements et coulisses du club : le journal de ' . setting('shop_name') . '.',
            'canonical'       => url('journal', array_filter(['theme' => $tag])),
            'posts'           => Journal::published($tag ?: null),
            'tag'             => $tag,
        ]);
    }

    public function show(string $slug): void
    {
        $preview = !empty($_SESSION['admin_id']); // l'administratrice voit aussi les brouillons
        $post = Journal::find($slug, !$preview) ?? abort(404);
        $url = url('journal/' . $post['slug']);
        view('shop/journal/show', [
            'pageTitle'       => $post['title'],
            'metaDescription' => $post['meta_description'] ?: $post['excerpt'],
            'canonical'       => $url,
            'ogImage'         => $post['cover_image'] ? image_url($post['cover_image']) : null,
            'post'            => $post,
            'products'        => Journal::products($post),
            'others'          => Journal::published(null, 3, (int) $post['id']),
            'jsonLd'          => [
                '@context'      => 'https://schema.org',
                '@type'         => 'BlogPosting',
                'headline'      => $post['title'],
                'description'   => $post['excerpt'],
                'image'         => $post['cover_image'] ? image_url($post['cover_image']) : null,
                'datePublished' => date('c', strtotime((string) $post['published_at'])),
                'dateModified'  => date('c', strtotime((string) $post['updated_at'])),
                'mainEntityOfPage' => $url,
                'author'        => ['@type' => 'Organization', 'name' => setting('shop_name')],
                'publisher'     => ['@type' => 'Organization', 'name' => setting('shop_name'), 'logo' => ['@type' => 'ImageObject', 'url' => url('assets/img/brand/logo.svg')]],
            ],
        ]);
    }
}
