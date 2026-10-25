<?php
// Version 2.2.0 : catalogue réorganisé en 4 univers (Mon cheval ma fierté, Tenues de cavalière,
// Pour son chéri, Petites attentions). Ne s'applique qu'à une boutique qui a encore les anciennes
// catégories par type d'article ; les anciennes adresses redirigent vers les nouvelles.
return function (PDO $pdo, string $driver): void {
    $find = static function (string $slug) use ($pdo): ?array {
        $q = $pdo->prepare('SELECT * FROM categories WHERE slug = ?');
        $q->execute([$slug]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    };
    $tshirts = $find('t-shirts');
    $conjoints = $find('pour-les-conjoints');
    if (!$tshirts || !$conjoints || $find('tenues-de-cavaliere')) {
        return; // autre catalogue (installation neuve) ou déjà réorganisé
    }

    // Anciennes catégories conservées et renommées (leurs images restent), dans l'ordre d'affichage
    $universes = [
        'mon-cheval-ma-fierte' => ['mon-cheval-ma-fierte', 'Mon cheval, ma fierté', 'Son nom, sa photo ou son portrait aquarelle : des créations uniques, imprimées rien que pour toi et ton cheval.'],
        'tenues-de-cavaliere'  => ['t-shirts', 'Tenues de cavalière', 'T-shirts et sweats à messages, pour celles qui passent leur vie à l\'écurie (et l\'assument).'],
        'pour-son-cheri'       => ['pour-les-conjoints', 'Pour son chéri', 'Pour celui qui partage sa vie… avec son cheval. Des cadeaux pleins d\'humour pour les conjoints de cavalières.'],
        'petites-attentions'   => ['accessoires', 'Petites attentions', 'Bonnets, casquettes, déco et petits cadeaux pour faire plaisir à une cavalière.'],
    ];
    // Anciennes catégories fusionnées dans un univers
    $merged = ['sweats-hoodies' => 'tenues-de-cavaliere', 'deco-maison' => 'petites-attentions'];
    // Articles classés à part de leur ancienne catégorie
    $products = [
        'cadre-elle-moi-et-son-cheval'       => 'pour-son-cheri',
        'decoration-en-bois-personnalisable' => 'mon-cheval-ma-fierte',
    ];

    $redirects = json_decode((string) ($pdo->query("SELECT value FROM settings WHERE name = 'category_redirects'")->fetchColumn() ?: ''), true) ?: [];
    $ids = [];
    $position = 1;
    foreach ($universes as $slug => [$old, $name, $description]) {
        $row = $find($old);
        $existing = $old !== $slug ? $find($slug) : null;
        if ($row && $existing) { // la nouvelle catégorie existe déjà : on y fusionne l'ancienne
            $pdo->prepare('UPDATE products SET category_id = ? WHERE category_id = ?')->execute([$existing['id'], $row['id']]);
            $pdo->prepare('UPDATE categories SET active = 0, position = 99 WHERE id = ?')->execute([$row['id']]);
            $row = null;
        }
        $row = $row ?? $existing;
        if ($row) {
            $pdo->prepare('UPDATE categories SET name = ?, slug = ?, description = ?, position = ?, active = 1 WHERE id = ?')
                ->execute([$name, $slug, $description, $position, $row['id']]);
            $ids[$slug] = (int) $row['id'];
        } else {
            $pdo->prepare('INSERT INTO categories (name, slug, description, position, active) VALUES (?, ?, ?, ?, 1)')
                ->execute([$name, $slug, $description, $position]);
            $ids[$slug] = (int) $pdo->lastInsertId();
        }
        if ($old !== $slug) {
            $redirects[$old] = $slug;
        }
        $position++;
    }
    // Les autres catégories éventuelles passent après les 4 univers
    $in = implode(',', array_map('intval', $ids));
    $pdo->exec("UPDATE categories SET position = position + 10 WHERE id NOT IN ($in) AND position < 10");
    foreach ($merged as $old => $target) {
        if ($row = $find($old)) {
            $pdo->prepare('UPDATE products SET category_id = ? WHERE category_id = ?')->execute([$ids[$target], $row['id']]);
            $pdo->prepare('UPDATE categories SET active = 0, position = 99 WHERE id = ?')->execute([$row['id']]);
            $redirects[$old] = $target;
        }
    }
    // Un vêtement homme rangé dans les tenues va chez les conjoints
    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(products)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (in_array('audience', $columns, true)) {
        $pdo->prepare("UPDATE products SET category_id = ? WHERE category_id = ? AND audience = 'lui'")
            ->execute([$ids['pour-son-cheri'], $ids['tenues-de-cavaliere']]);
    }
    foreach ($products as $slug => $target) {
        $pdo->prepare('UPDATE products SET category_id = ? WHERE slug = ?')->execute([$ids[$target], $slug]);
    }

    // Titres harmonisés, seulement s'ils n'ont pas été retouchés depuis
    $names = [
        'sweet à capuche "Désolée, j\'ai écurie"'           => 'Sweat à capuche "Désolée, j\'ai écurie"',
        'casquette "Crinière Club"'                          => 'Casquette "Crinière Club"',
        'T shirt femme "Désolé, j\'ai écurie" sur le coeur' => 'T-shirt femme "Désolée, j\'ai écurie" sur le cœur',
        'Sous verre "Crinière Club"'                         => 'Sous-verre "Crinière Club"',
    ];
    foreach ($names as $old => $new) {
        $pdo->prepare('UPDATE products SET name = ? WHERE name = ?')->execute([$new, $old]);
    }

    $json = json_encode($redirects, JSON_UNESCAPED_SLASHES);
    $exists = (int) $pdo->query("SELECT COUNT(*) FROM settings WHERE name = 'category_redirects'")->fetchColumn();
    $pdo->prepare($exists ? "UPDATE settings SET value = ? WHERE name = 'category_redirects'" : "INSERT INTO settings (name, value) VALUES ('category_redirects', ?)")
        ->execute([$json]);
};
