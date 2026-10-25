<?php
// Version 2.0.0 : commande cadeau (message sur le bon de livraison) et relance des paniers abandonnés.
return function (PDO $pdo, string $driver): void {
    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $add = [
        'is_gift'          => 'TINYINT(1) NOT NULL DEFAULT 0',
        'gift_to'          => 'VARCHAR(100) NULL',
        'gift_message'     => 'VARCHAR(200) NULL',
        'recovery_sent_at' => 'DATETIME NULL',
    ];
    foreach ($add as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN $column $definition");
        }
    }
};
