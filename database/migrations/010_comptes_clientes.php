<?php
// Version 1.7.0 : comptes clientes (table customers) + rattachement des commandes (orders.customer_id).
return function (PDO $pdo, string $driver): void {
    $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $options = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id $pk,
        email VARCHAR(190) NOT NULL UNIQUE,
        name VARCHAR(120) NOT NULL DEFAULT '',
        password_hash VARCHAR(255) NOT NULL,
        email_verified_at DATETIME NULL,
        token_hash VARCHAR(64) NULL,
        token_expires_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        last_login_at DATETIME NULL
    )$options");
    try {
        $pdo->exec('CREATE INDEX idx_customers_token ON customers (token_hash)');
    } catch (PDOException) {
        // index déjà présent
    }

    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('customer_id', $columns, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN customer_id INT UNSIGNED NULL');
        $pdo->exec('CREATE INDEX idx_orders_customer ON orders (customer_id)');
    }
};
