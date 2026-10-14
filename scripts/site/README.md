# Site vitrine jackcie.fr

Site statique (HTML, CSS, un peu de JavaScript et un script PHP pour le formulaire de contact), hébergé chez Hostinger.
Le dossier `site/` est **le site prêt à publier** ; il est généré à partir de `scripts/site/pages/`.

## Modifier une page
1. Éditer `scripts/site/pages/<page>.html` (le bloc `<!-- {…} -->` en tête contient titre, description, image de partage).
   En-tête et pied de page communs : `scripts/site/build.py` (fonction `layout`).
2. Régénérer : `python3 scripts/site/build.py` (met aussi à jour `site/sitemap.xml`).
3. Aperçu local avec les adresses propres : `php -S 127.0.0.1:8090 -t site scripts/site/serve.php` puis http://127.0.0.1:8090

Raccourcis dans les pages : `{{icon:nom}}`, `{{signup:source}}` (lien d'inscription suivi : `?src=site-source`),
`{{login}}`, `{{app}}`, `{{email}}`, `{{instagram}}`, `{{year}}`.

## Captures d'écran de l'application
Base de **démonstration locale** uniquement :
1. `php artisan tinker --execute "require 'scripts/site/demo-ecurie.php';"` (pensionnaires de démo, une seule fois) ;
2. lancer l'app sur :8012 (avec `scripts/nouveautes-2.13/mock_claude.py` pour l'assistant) ;
3. `EMERGENCY_TOKEN=… node scripts/site/capture.mjs` puis convertir en WebP (voir l'historique : `ffmpeg … -quality 80`).
Images de partage : `node scripts/site/og.mjs` (aperçu du site lancé sur :8090).

## Publier sur Hostinger
1. hPanel › Sites web › jackcie.fr › Gestionnaire de fichiers › `public_html` ;
2. téléverser le contenu de `dist/jackcie-site.zip` et l'extraire **directement dans `public_html`**
   (le fichier caché `.htaccess` doit s'y trouver : HTTPS, adresses sans `.html`, cache, en-têtes de sécurité) ;
3. hPanel › E-mails : la boîte `contact@jackcie.fr` doit exister (expéditeur et destinataire du formulaire).
   Vérifier aussi que les enregistrements SPF/DKIM du domaine sont actifs pour une bonne délivrabilité.

## À compléter
- Mentions légales : forme juridique, capital, SIRET, adresse, directeur de la publication (repérés en orange).
- Dans l'app (Administration › Codes › Sources) : déclarer les sources `site-…` pour lire les inscriptions venant du site.
