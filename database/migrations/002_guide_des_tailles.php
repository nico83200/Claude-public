<?php
// Version 1.2.0 : page « Guide des tailles » affichée sur les fiches textiles.
return function (PDO $pdo, string $driver): void {
    $exists = $pdo->query("SELECT COUNT(*) FROM pages WHERE slug = 'guide-des-tailles'")->fetchColumn();
    if ($exists) {
        return;
    }
    require_once BASE_PATH . '/database/seed.php';
    $page = seed_size_guide();
    $stmt = $pdo->prepare('INSERT INTO pages (slug, title, content, meta_description, in_footer, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$page['slug'], $page['title'], $page['content'], $page['meta_description'], $page['in_footer'], date('Y-m-d H:i:s')]);
};
