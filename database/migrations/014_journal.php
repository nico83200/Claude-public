<?php
// Version 2.0.0 : rubrique Journal (articles de conseils et d'idées cadeaux) + 3 articles de départ.
return function (PDO $pdo, string $driver): void {
    $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $options = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $pdo->exec("CREATE TABLE IF NOT EXISTS journal_posts (
        id $pk,
        slug VARCHAR(190) NOT NULL UNIQUE,
        title VARCHAR(190) NOT NULL,
        tag VARCHAR(60) NULL,
        excerpt VARCHAR(400) NULL,
        content MEDIUMTEXT NULL,
        cover_image VARCHAR(500) NULL,
        product_ids TEXT NULL,
        meta_description VARCHAR(300) NULL,
        published TINYINT(1) NOT NULL DEFAULT 0,
        published_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    )$options");
    App\Services\Journal::installStarter();
};
