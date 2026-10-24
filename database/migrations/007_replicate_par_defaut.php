<?php
// Version 1.6.0 : Replicate devient le service de dessin du portrait IA (avec agrandissement avant
// impression) et l'annonce du site présente le portrait aquarelle. Les valeurs personnalisées sont gardées.
return function (PDO $pdo, string $driver): void {
    $update = $pdo->prepare('UPDATE settings SET value = ? WHERE name = ? AND value = ?');
    $update->execute(['replicate', 'config.ai.provider', 'demo']);
    $update->execute(['1', 'config.ai.upscale', '0']);
    $update->execute([
        'Nouveau : son portrait aquarelle en quelques secondes ✦|Livraison offerte dès 69 € d\'achat ♡|Personnalisation offerte : son nom, sa photo, son portrait|Chaque pièce est imprimée rien que pour vous',
        'announcement',
        'Livraison offerte dès 69 € d\'achat ♡|Personnalisation offerte : son nom, sa photo, son portrait|Chaque pièce est imprimée rien que pour vous',
    ]);
    App\Core\Settings::flush();
};
