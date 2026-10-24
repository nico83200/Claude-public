<?php
// Version 1.6.6 : code de bienvenue personnel (5 €, utilisable une fois) mémorisé pour chaque inscrite.
return function (PDO $pdo, string $driver): void {
    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(newsletter_subscribers)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM newsletter_subscribers')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('welcome_code', $columns, true)) {
        $pdo->exec('ALTER TABLE newsletter_subscribers ADD COLUMN welcome_code VARCHAR(40) NULL');
    }
    $pdo->prepare("UPDATE settings SET value = '5 € offerts' WHERE name = 'welcome_discount' AND value = '-10 %'")->execute();
    App\Core\Settings::flush();
};
