<?php
// Version 1.9.2 : réinitialisation du mot de passe administrateur par e-mail.
return function (PDO $pdo, string $driver): void {
    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(admins)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM admins')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('reset_token_hash', $columns, true)) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN reset_token_hash VARCHAR(64) NULL');
    }
    if (!in_array('reset_expires_at', $columns, true)) {
        $pdo->exec('ALTER TABLE admins ADD COLUMN reset_expires_at DATETIME NULL');
    }
};
