# Sauvegarde et restauration

## Quoi sauvegarder

| Élément | Emplacement | Fréquence conseillée |
|---|---|---|
| Base MySQL | toutes les tables | quotidienne (+ rétention 30 j), avant chaque mise à jour |
| Fichiers privés | `storage/app/private/` (photos, documents, justificatifs) | quotidienne |
| Configuration | `.env` (coffre-fort, jamais dans Git) | à chaque changement |

## Sauvegarde automatique sur Google Drive (recommandée)

Depuis la version 2.20.0, l'application envoie elle-même une copie **chiffrée** sur Google Drive, hors de l'hébergement :

| Contenu | Fréquence | Conservation |
|---|---|---|
| Base de données (`jackcie-base-AAAA-MM-JJ-HHMM.sql.gz.jcbk`) | chaque nuit à partir de 3 h (rattrapée si le cron a manqué) | 30 jours |
| Photos, documents, justificatifs (`jackcie-fichiers-….zip.jcbk`) | chaque semaine | 5 dernières archives |

Les fichiers sont chiffrés avant l'envoi (libsodium, XChaCha20-Poly1305) : Google ne peut pas les lire.
Jack&Cie n'a accès qu'aux fichiers qu'il crée (autorisation Google « drive.file »), dans le dossier « Jack&Cie – sauvegardes ».
En cas d'échec, les super-administrateurs reçoivent une notification et un e-mail ; la surveillance prévient aussi
si aucune sauvegarde n'a réussi depuis 48 heures.

### Mise en route (une fois)

1. Google Cloud Console, projet du client OAuth déjà utilisé pour « Se connecter avec Google »
   (ou un nouveau projet) : **API et services › Bibliothèque › Google Drive API › Activer**.
2. **Identifiants › client OAuth (application Web)** : ajouter l'URI de redirection autorisée
   `https://app.jackcie.fr/admin/sauvegardes/google/retour` (adresse exacte affichée dans Administration › Sauvegardes).
3. **Écran de consentement OAuth** : ajouter le champ d'application `…/auth/drive.file` ; si l'application est en mode
   « Test », ajouter le compte Google qui recevra les sauvegardes dans les utilisateurs de test (sinon publier l'application :
   `drive.file` ne demande pas de vérification par Google).
4. L'identifiant et le secret du client sont dans Administration › Intégrations (Google).
5. Administration › **Sauvegardes** › **Connecter Google Drive**, choisir le compte, accepter.
6. **Noter la clé de restauration** affichée (elle commence par `jcbk_`) dans un gestionnaire de mots de passe ou
   sur papier, hors du serveur. Sans elle, les sauvegardes sont illisibles. Elle reste consultable dans la page
   (bouton « Afficher la clé de restauration », action tracée dans le journal).
7. Cliquer **Base, photos et documents** pour un premier envoi complet, recharger la page au bout d'une minute :
   l'historique doit indiquer « Envoyée » pour les deux lignes, et les fichiers apparaître dans le Drive.

### Restaurer depuis Google Drive

1. Télécharger depuis le Drive la sauvegarde voulue (`.sql.gz.jcbk` et, si besoin, `.zip.jcbk`).
2. La déchiffrer avec la clé de restauration (sur le serveur ou sur n'importe quelle installation de Jack&Cie) :
   ```bash
   php artisan backup:decrypt jackcie-base-2026-10-20-0305.sql.gz.jcbk --key=jcbk_…
   php artisan backup:decrypt jackcie-fichiers-2026-10-19-0305.zip.jcbk --key=jcbk_…
   ```
   Une clé erronée ou un fichier modifié est refusé (« mauvaise clé ou fichier modifié »).
3. Restaurer la base et les fichiers comme indiqué dans « Restauration » ci-dessous
   (`gunzip < … | mysql …`, puis décompresser l'archive des fichiers dans `storage/app/private`).

## Sauvegarde manuelle

```bash
mysqldump --single-transaction --routines --default-character-set=utf8mb4 \
  -u jackcie -p jackcie | gzip > jackcie-$(date +%F).sql.gz
tar czf fichiers-$(date +%F).tar.gz storage/app/private
```

`--single-transaction` garantit une copie cohérente sans bloquer l'application (InnoDB).
Chiffrer les archives et les stocker hors du serveur (autre fournisseur / région).
La plupart des hébergeurs mutualisés proposent aussi des sauvegardes automatiques : les vérifier.

## Restauration

```bash
php artisan down
gunzip < jackcie-AAAA-MM-JJ.sql.gz | mysql -u jackcie -p jackcie
tar xzf fichiers-AAAA-MM-JJ.tar.gz
php artisan migrate --force   # si la sauvegarde est antérieure à une version
php artisan optimize:clear && php artisan up
```

## Test de restauration (à faire au moins chaque trimestre)

1. Restaurer la dernière sauvegarde dans une base de recette (`DB_DATABASE=jackcie_recette`).
2. `php artisan migrate:status` doit être à jour ; se connecter avec un compte de test.
3. Ouvrir un cheval, télécharger un document, vérifier le tableau de bord admin.
4. Consigner date, durée et anomalies.
