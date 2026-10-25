<?php
// Version 2.1.0 : « Pour elle / Pour lui » (champ products.audience), déduit du nom pour les articles existants.
return function (PDO $pdo, string $driver): void {
    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(products)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('audience', $columns, true)) {
        $pdo->exec('ALTER TABLE products ADD COLUMN audience VARCHAR(10) NULL');
    }
    $rows = $pdo->query('SELECT p.id, p.name, c.name AS category FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.audience IS NULL')->fetchAll(PDO::FETCH_ASSOC);
    $update = $pdo->prepare('UPDATE products SET audience = ? WHERE id = ?');
    foreach ($rows as $r) {
        $update->execute([App\Services\Catalog::guessAudience((string) $r['name'], (string) $r['category']), $r['id']]);
    }
};
