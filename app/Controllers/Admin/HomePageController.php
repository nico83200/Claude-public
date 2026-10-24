<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Settings;
use App\Services\Catalog;
use App\Services\HomeImages;
use App\Services\Upload;

final class HomePageController extends BaseController
{
    public function index(): void
    {
        $this->render('home-page', [
            'pageTitle'  => 'Page d\'accueil',
            'categories' => Catalog::categories(false),
            'portraitDefault' => $this->portraitProductImage(),
        ]);
    }

    public function update(): void
    {
        $errors = [];
        foreach (array_keys(HomeImages::SLOTS) as $slot) {
            Settings::set('home_alt_' . $slot, mb_substr(trim((string) input('alt_' . $slot)), 0, 200));
            $current = (string) setting('home_img_' . $slot, '');
            if (input('reset_' . $slot) === '1') {
                HomeImages::discard($current);
                Settings::set('home_img_' . $slot, '');
                continue;
            }
            $file = Upload::files('image_' . $slot)[0] ?? null;
            if ($file) {
                try {
                    Settings::set('home_img_' . $slot, HomeImages::store($file));
                    HomeImages::discard($current);
                } catch (\RuntimeException $e) {
                    $errors[] = HomeImages::SLOTS[$slot]['label'] . ' : ' . $e->getMessage();
                }
            }
        }
        // Visuels des collections (image de la catégorie)
        foreach (Catalog::categories(false) as $c) {
            $key = 'category_' . $c['id'];
            if (input('reset_' . $key) === '1') {
                HomeImages::discard($c['image']);
                Database::update('categories', ['image' => null], 'id = :id', ['id' => $c['id']]);
                continue;
            }
            $file = Upload::files('image_' . $key)[0] ?? null;
            if ($file) {
                try {
                    Database::update('categories', ['image' => HomeImages::store($file)], 'id = :id', ['id' => $c['id']]);
                    HomeImages::discard($c['image']);
                } catch (\RuntimeException $e) {
                    $errors[] = 'Collection « ' . $c['name'] . ' » : ' . $e->getMessage();
                }
            }
        }
        $errors ? flash('error', implode(' · ', $errors)) : flash('success', 'Page d\'accueil mise à jour.');
        redirect('admin/accueil');
    }

    private function portraitProductImage(): ?string
    {
        $images = Database::value("SELECT images FROM products WHERE active = 1 AND personalization LIKE '%\"ai_portrait\"%' ORDER BY id LIMIT 1");
        return json_field($images ?: null)[0] ?? null;
    }
}
