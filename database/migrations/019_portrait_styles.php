<?php
// Version 2.3.0 : portrait IA en 5 styles (ai_portraits.style) avec signes particuliers (ai_portraits.marks).
return function (PDO $pdo, string $driver): void {
    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(ai_portraits)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM ai_portraits')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('style', $columns, true)) {
        $pdo->exec('ALTER TABLE ai_portraits ADD COLUMN style VARCHAR(20) NULL');
    }
    if (!in_array('marks', $columns, true)) {
        $pdo->exec('ALTER TABLE ai_portraits ADD COLUMN marks VARCHAR(200) NULL');
    }

    // Le produit d'origine n'est plus seulement « aquarelle » : textes mis à jour s'ils n'ont pas été retouchés
    $texts = [
        'name' => ['Portrait aquarelle de votre cheval, encadré', 'Portrait de ton cheval, encadré'],
        'short_description' => ['Sa photo redessinée au trait fin et à l\'aquarelle, avec son nom et son année de naissance.',
            'Sa photo redessinée dans le style de ton choix, avec son nom et son année de naissance.'],
    ];
    foreach ($texts as $column => [$old, $new]) {
        $pdo->prepare("UPDATE products SET $column = ? WHERE slug = 'portrait-aquarelle-de-votre-cheval-encadre' AND $column = ?")->execute([$new, $old]);
    }

    // Bandeau d'annonce : le message d'origine sur le portrait aquarelle annonce désormais les 5 styles
    $old = 'Nouveau : son portrait aquarelle en quelques secondes ✦';
    $pdo->prepare("UPDATE settings SET value = REPLACE(value, ?, ?) WHERE name = 'announcement' AND value LIKE ?")
        ->execute([$old, 'Nouveau : son portrait en 5 styles, créé en quelques secondes ✦', $old . '%']);
};
