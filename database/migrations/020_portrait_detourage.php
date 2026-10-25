<?php
// Version 2.4.0 : détourage de la tête et de l'encolure avant le dessin (ai_portraits.step : isolate puis style).
return function (PDO $pdo, string $driver): void {
    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(ai_portraits)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM ai_portraits')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('step', $columns, true)) {
        $pdo->exec('ALTER TABLE ai_portraits ADD COLUMN step VARCHAR(10) NULL');
    }
};
