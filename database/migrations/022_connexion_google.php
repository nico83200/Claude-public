<?php
// Version 2.7.0 : connexion et création de compte avec Google (customers.google_sub).
return function (PDO $pdo, string $driver): void {
    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(customers)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM customers')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('google_sub', $columns, true)) {
        $pdo->exec('ALTER TABLE customers ADD COLUMN google_sub VARCHAR(64) NULL');
        $pdo->exec('CREATE INDEX idx_customers_google ON customers (google_sub)');
    }
};
