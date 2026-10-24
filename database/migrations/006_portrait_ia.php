<?php
// Version 1.5.0 : portrait redessiné par IA (table ai_portraits) + année de naissance sous le nom.
return function (PDO $pdo, string $driver): void {
    $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $options = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_portraits (
        id $pk,
        token CHAR(32) NOT NULL UNIQUE,
        session_hash CHAR(40) NOT NULL,
        ip_hash CHAR(40) NOT NULL,
        provider VARCHAR(20) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'queued',
        photo_file VARCHAR(190) NOT NULL,
        result_file VARCHAR(190) NULL,
        prediction_id VARCHAR(120) NULL,
        master_file VARCHAR(190) NULL,
        master_prediction_id VARCHAR(120) NULL,
        error VARCHAR(500) NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    )$options");
    $pdo->exec('CREATE INDEX idx_ai_portraits_session ON ai_portraits (session_hash, created_at)');
    $pdo->exec('CREATE INDEX idx_ai_portraits_ip ON ai_portraits (ip_hash, created_at)');
    $pdo->exec('CREATE INDEX idx_ai_portraits_created ON ai_portraits (created_at)');

    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(order_items)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM order_items')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('personalization_year', $columns, true)) {
        $pdo->exec('ALTER TABLE order_items ADD COLUMN personalization_year VARCHAR(10) NULL');
    }
    if (!in_array('ai_portrait', $columns, true)) {
        $pdo->exec('ALTER TABLE order_items ADD COLUMN ai_portrait CHAR(32) NULL');
    }

    // Nouveau produit « Portrait aquarelle de votre cheval, encadré » dans « Mon cheval, ma fierté »
    $category = $pdo->query("SELECT id FROM categories WHERE slug = 'mon-cheval-ma-fierte'")->fetchColumn();
    $exists = $pdo->query("SELECT COUNT(*) FROM products WHERE slug = 'portrait-aquarelle-de-votre-cheval-encadre' OR personalization LIKE '%\"ai_portrait\"%'")->fetchColumn();
    if ($category && !$exists) {
        require_once BASE_PATH . '/database/seed.php';
        seed_ai_portrait_product((int) $category);
    }

    // FAQ et politique de confidentialité : ajout des paragraphes sur le portrait IA (sans toucher au reste)
    $page = static fn (string $slug) => $pdo->query('SELECT id, content FROM pages WHERE slug = ' . $pdo->quote($slug))->fetch(PDO::FETCH_ASSOC) ?: null;
    $save = static function (int $id, string $content) use ($pdo): void {
        $stmt = $pdo->prepare('UPDATE pages SET content = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$content, date('Y-m-d H:i:s'), $id]);
    };
    $faq = $page('faq');
    if ($faq && !str_contains((string) $faq['content'], 'portrait aquarelle')) {
        $save((int) $faq['id'], $faq['content'] . "\n<h2>Comment fonctionne le portrait aquarelle par IA ?</h2>\n<p>Sur la fiche du portrait encadré, ajoutez une photo de votre cheval et cliquez sur « Créer mon portrait » : notre IA le redessine en quelques secondes dans notre style « Encre &amp; Poudre » (trait fin bordeaux et aquarelle poudrée). Vous voyez l'aperçu dans son cadre, avec son nom et son année de naissance, avant de commander. Vous pouvez créer plusieurs versions et garder votre préférée.</p>");
    }
    $privacy = $page('confidentialite');
    if ($privacy && !str_contains((string) $privacy['content'], 'portraits IA')) {
        $save((int) $privacy['id'], $privacy['content'] . "\n<h2>Photos de votre cheval et portraits IA</h2>\n<p>La photo que vous envoyez sert uniquement à fabriquer votre article. Pour le portrait aquarelle, elle est transmise, avec votre accord, à notre prestataire de dessin par IA (OpenAI ou Replicate, selon le service activé), uniquement pour créer le dessin. Photos et portraits non commandés sont supprimés automatiquement après 30 jours ; ceux des articles commandés sont conservés avec la commande.</p>");
    }
};
