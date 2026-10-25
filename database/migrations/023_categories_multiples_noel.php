<?php
// Version 2.8.0 : plusieurs catégories par article (une principale), catégorie « Noël »,
// coût Printful par variante (calcul des prix) et 20 nouveaux articles hors ligne.
return function (PDO $pdo, string $driver): void {
    $tables = $driver === 'sqlite'
        ? array_column($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_map('current', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM));
    if (!in_array('product_categories', $tables, true)) {
        $pdo->exec($driver === 'sqlite'
            ? 'CREATE TABLE product_categories (product_id INTEGER NOT NULL, category_id INTEGER NOT NULL, PRIMARY KEY (product_id, category_id))'
            : 'CREATE TABLE product_categories (product_id INT UNSIGNED NOT NULL, category_id INT UNSIGNED NOT NULL, PRIMARY KEY (product_id, category_id),
               FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE, FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE)
               ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE INDEX idx_product_categories_category ON product_categories (category_id)');
        // La catégorie actuelle de chaque article devient sa catégorie principale
        $pdo->exec('INSERT INTO product_categories (product_id, category_id) SELECT id, category_id FROM products WHERE category_id IS NOT NULL');
    }
    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(product_variants)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM product_variants')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('cost', $columns, true)) {
        $pdo->exec('ALTER TABLE product_variants ADD COLUMN cost INT NULL');
    }

    // Catégorie « Noël »
    $exists = $pdo->query("SELECT COUNT(*) FROM categories WHERE slug = 'noel'")->fetchColumn();
    if (!$exists) {
        $position = (int) $pdo->query('SELECT COALESCE(MAX(position), 0) FROM categories WHERE active = 1 AND position < 90')->fetchColumn() + 1;
        $pdo->prepare('INSERT INTO categories (name, slug, description, position, active) VALUES (?, ?, ?, ?, 1)')->execute([
            'Noël', 'noel', 'Les cadeaux qui font briller les yeux des cavalières (et de leur cheval) sous le sapin.', $position,
        ]);
    }

    \App\Services\CatalogPack::install(dirname(__DIR__) . '/catalogue-noel-2026.json');
};
