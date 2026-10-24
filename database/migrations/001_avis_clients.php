<?php
// Version 1.2.0 : avis clients vérifiés + suivi des demandes d'avis.
return function (PDO $pdo, string $driver): void {
    $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $fk = $driver === 'sqlite' ? 'INTEGER' : 'INT UNSIGNED';
    $options = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id $pk,
        product_id $fk NOT NULL,
        order_id $fk NULL,
        author_name VARCHAR(80) NOT NULL,
        rating TINYINT NOT NULL,
        title VARCHAR(120) NULL,
        body TEXT NULL,
        verified TINYINT(1) NOT NULL DEFAULT 1,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL,
        FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
    )$options");
    $pdo->exec('CREATE INDEX idx_reviews_product ON reviews (product_id, status)');
    $pdo->exec('CREATE INDEX idx_reviews_order ON reviews (order_id)');

    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('review_requested_at', $columns, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN review_requested_at DATETIME NULL');
    }
};
