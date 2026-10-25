<?php
// Version 2.5.0 : illustration manga IA pour t-shirt (photo de la cavalière + photo du cheval, de face ou de dos).
return function (PDO $pdo, string $driver): void {
    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(ai_portraits)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM ai_portraits')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('photo2_file', $columns, true)) {
        $pdo->exec('ALTER TABLE ai_portraits ADD COLUMN photo2_file VARCHAR(190) NULL');
    }
    if (!in_array('view', $columns, true)) {
        $pdo->exec('ALTER TABLE ai_portraits ADD COLUMN view VARCHAR(10) NULL');
    }

    // T-shirt « illustration manga » créé hors ligne d'après un t-shirt existant (variantes Printful reprises)
    \App\Services\MangaIllustration::createProduct(false);
};
