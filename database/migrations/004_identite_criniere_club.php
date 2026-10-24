<?php
// Version 1.2.0 : passage à l'identité Crinière Club.
// Remplace l'ancien catalogue de démonstration UNIQUEMENT s'il n'a jamais été modifié ni commandé.
return function (PDO $pdo, string $driver): void {
    $q = static fn (string $sql) => $pdo->query($sql)->fetchColumn();

    $products = (int) $q('SELECT COUNT(*) FROM products');
    $demoVariants = (int) $q("SELECT COUNT(*) FROM product_variants WHERE sku LIKE 'DEMO-%'");
    $allVariants = (int) $q('SELECT COUNT(*) FROM product_variants');
    $ordered = (int) $q('SELECT COUNT(*) FROM order_items');
    $oldDemo = (int) $q("SELECT COUNT(*) FROM categories WHERE slug IN ('sweats-hoodies', 't-shirts', 'accessoires', 'deco-maison')");

    if ($products > 0 && $demoVariants === $allVariants && $ordered === 0 && $oldDemo > 0) {
        $pdo->exec('DELETE FROM product_variants');
        $pdo->exec('DELETE FROM products');
        $pdo->exec('DELETE FROM categories');
        require_once BASE_PATH . '/database/seed.php';
        seed_demo_catalog();
    }

    // Nom de la boutique enregistré par l'ancien installateur
    $stmt = $pdo->prepare("UPDATE settings SET value = 'Crinière Club' WHERE name = 'shop_name' AND value = 'Crinière & Co'");
    $stmt->execute();
};
