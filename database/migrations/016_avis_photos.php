<?php
// Version 2.1.0 : photo jointe aux avis clients (reviews.photo).
return function (PDO $pdo, string $driver): void {
    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(reviews)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM reviews')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('photo', $columns, true)) {
        $pdo->exec('ALTER TABLE reviews ADD COLUMN photo VARCHAR(190) NULL');
    }
};
