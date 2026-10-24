<?php
// Version 1.6.2 : garantit la présence du produit « Portrait aquarelle de votre cheval, encadré ».
// La migration 006 ne le créait que dans la collection « Mon cheval, ma fierté » : sur une boutique
// restée sur l'ancien catalogue (catégories d'origine + produits importés de Printful), il manquait,
// et la page /portrait-aquarelle répondait 404. La collection est créée si besoin ; rien n'est supprimé.
return function (PDO $pdo, string $driver): void {
    $exists = $pdo->query("SELECT COUNT(*) FROM products WHERE slug = 'portrait-aquarelle-de-votre-cheval-encadre' OR personalization LIKE '%\"ai_portrait\"%'")->fetchColumn();
    if ($exists) {
        return;
    }
    $category = $pdo->query("SELECT id FROM categories WHERE slug = 'mon-cheval-ma-fierte'")->fetchColumn();
    if (!$category) {
        $position = (int) $pdo->query('SELECT COALESCE(MAX(position), 0) + 1 FROM categories')->fetchColumn();
        $stmt = $pdo->prepare('INSERT INTO categories (name, slug, description, image, position, active) VALUES (?, ?, ?, ?, ?, 1)');
        $stmt->execute([
            'Mon cheval, ma fierté',
            'mon-cheval-ma-fierte',
            'Son nom, sa photo ou son portrait aquarelle : des créations uniques, imprimées rien que pour vous et votre cheval.',
            'assets/img/products/portrait-ia.jpg',
            $position,
        ]);
        $category = $pdo->lastInsertId();
    }
    require_once BASE_PATH . '/database/seed.php';
    seed_ai_portrait_product((int) $category);
};
