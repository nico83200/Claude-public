<?php
// Version 2.1.0 : textes du site au tutoiement. Seuls les réglages restés à leur ancienne
// valeur par défaut et les articles de départ du Journal jamais modifiés sont mis à jour.
return function (PDO $pdo, string $driver): void {
    $old = [
        'announcement' => 'Nouveau : son portrait aquarelle en quelques secondes ✦|Livraison offerte dès 69 € d\'achat ♡|Personnalisation offerte : son nom, sa photo, son portrait|Chaque pièce est imprimée rien que pour vous',
        'hero_subtitle' => 'Vêtements, accessoires et cadeaux pour cavalières, à porter fièrement ou à personnaliser avec le nom, la photo ou le portrait de votre cheval.',
        'home_meta_description' => 'Crinière Club, le club des passionnées : vêtements et accessoires d\'équitation pour cavalières, cadeaux personnalisés avec le nom, la photo ou le portrait de votre cheval.',
        'popup_title' => 'Rejoignez le club ♡',
        'packing_slip_message' => 'Merci d\'avoir rejoint le club ! Votre article a été imprimé rien que pour vous. À très vite aux écuries ♡ Crinière Club',
    ];
    $update = $pdo->prepare('UPDATE settings SET value = ? WHERE name = ? AND value = ?');
    foreach ($old as $name => $value) {
        $update->execute([\App\Core\Settings::DEFAULTS[$name], $name, $value]);
    }

    $post = $pdo->prepare('UPDATE journal_posts SET title = ?, excerpt = ?, content = ?, meta_description = ?
                           WHERE slug = ? AND updated_at = created_at');
    foreach (require dirname(__DIR__) . '/journal-articles.php' as $a) {
        $post->execute([$a['title'], $a['excerpt'], $a['content'], $a['excerpt'], $a['slug']]);
    }

    // Pages d'information jamais modifiées (empreinte du texte d'origine, toutes versions)
    $original = [
        'guide-des-tailles' => ['ea22e9233593396eca6814e59497ae2afe47e333'],
        'a-propos'          => ['13d32986286b6f9c4f2a4f5cca8e964ae0de16f1', '4fbd495a08967fe3992f8ea4c911dd537ff44d57'],
        'livraison-retours' => ['623fa18cf8cbb3086986bc18d0cd0057d4dc2a9c'],
        'faq'               => ['ebed749ffc3d04fda39a6f8f96abaa6c0d99f124', '6317f3b4cca81b5faeacb7c34bcc4865a7700405', '542fef91ca9d995876bb02df1023a5bb7706d5ef'],
    ];
    require_once dirname(__DIR__) . '/seed.php';
    $page = $pdo->prepare('UPDATE pages SET content = ?, meta_description = ? WHERE slug = ?');
    foreach (seed_pages() as $p) {
        $current = $pdo->prepare('SELECT content FROM pages WHERE slug = ?');
        $current->execute([$p['slug']]);
        $content = $current->fetchColumn();
        if ($content !== false && in_array(sha1((string) $content), $original[$p['slug']] ?? [], true)) {
            $page->execute([$p['content'], $p['meta_description'], $p['slug']]);
        }
    }

    // Libellé de personnalisation laissé à sa valeur d'origine
    $pdo->exec("UPDATE products SET personalization = REPLACE(personalization, 'Nom de votre cheval', 'Nom de ton cheval')
                WHERE personalization LIKE '%Nom de votre cheval%'");
};
