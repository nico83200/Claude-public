# Sauvegarde et restauration

## Quoi sauvegarder

| Élément | Emplacement | Fréquence conseillée |
|---|---|---|
| Base MySQL | toutes les tables | quotidienne (+ rétention 30 j), avant chaque mise à jour |
| Fichiers privés | `storage/app/private/` (photos, documents, justificatifs) | quotidienne |
| Configuration | `.env` (coffre-fort, jamais dans Git) | à chaque changement |

## Sauvegarde

```bash
mysqldump --single-transaction --routines --default-character-set=utf8mb4 \
  -u equilibre -p equilibre | gzip > equilibre-$(date +%F).sql.gz
tar czf fichiers-$(date +%F).tar.gz storage/app/private
```

`--single-transaction` garantit une copie cohérente sans bloquer l'application (InnoDB).
Chiffrer les archives et les stocker hors du serveur (autre fournisseur / région).
La plupart des hébergeurs mutualisés proposent aussi des sauvegardes automatiques : les vérifier.

## Restauration

```bash
php artisan down
gunzip < equilibre-AAAA-MM-JJ.sql.gz | mysql -u equilibre -p equilibre
tar xzf fichiers-AAAA-MM-JJ.tar.gz
php artisan migrate --force   # si la sauvegarde est antérieure à une version
php artisan optimize:clear && php artisan up
```

## Test de restauration (à faire au moins chaque trimestre)

1. Restaurer la dernière sauvegarde dans une base de recette (`DB_DATABASE=equilibre_recette`).
2. `php artisan migrate:status` doit être à jour ; se connecter avec un compte de test.
3. Ouvrir un cheval, télécharger un document, vérifier le tableau de bord admin.
4. Consigner date, durée et anomalies.

## Après restauration — appareils hors ligne

Les appareils conservent leurs opérations en attente (identifiants uniques) : elles
seront rejouées sans doublon. Les opérations déjà enregistrées après la date de la
sauvegarde seront réappliquées si les appareils les ont encore en file ; sinon elles
sont perdues pour la période restaurée — prévenir les utilisateurs.
