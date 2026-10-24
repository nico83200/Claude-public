<?php
// Version 1.2.0 : photo du cheval et portrait illustré sur les articles commandés.
return function (PDO $pdo, string $driver): void {
    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(order_items)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM order_items')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('personalization_mode', $columns, true)) {
        $pdo->exec('ALTER TABLE order_items ADD COLUMN personalization_mode VARCHAR(20) NULL');
    }
    if (!in_array('photo_file', $columns, true)) {
        $pdo->exec('ALTER TABLE order_items ADD COLUMN photo_file VARCHAR(190) NULL');
    }
};
