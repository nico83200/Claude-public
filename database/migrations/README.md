# Migrations de base de données

Quand une nouvelle version modifie la structure de la base :

1. mettez à jour `database/schema.sql` (utilisé pour les installations neuves) ;
2. ajoutez ici un fichier `NNN_description.php` (numéro croissant, ex. `002_ajout_avis_clients.php`) qui fait passer une base existante au nouveau schéma :

```php
<?php
return function (PDO $pdo, string $driver): void {
    $pdo->exec('ALTER TABLE products ADD COLUMN material VARCHAR(120) NULL');
};
```

Les migrations en attente s'exécutent automatiquement après l'import d'un ZIP dans *Administration › Mise à jour* (et sont marquées comme faites lors d'une installation neuve). Une migration doit fonctionner sous MySQL et SQLite (utilisez `$driver` si nécessaire).
