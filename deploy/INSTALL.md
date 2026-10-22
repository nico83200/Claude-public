# Installation de Tapikard sur un serveur

Ce paquet est prêt à l'emploi : pas de `npm install` sur le serveur.
Testé pour un serveur Linux (Debian 12 / Ubuntu 22.04 ou 24.04), par exemple un VPS en Europe (OVH, Scaleway, Hetzner).

## 1. Prérequis

- **Node.js 22.13 ou plus récent** (SQLite intégré à Node) :
  ```bash
  curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
  sudo apt install -y nodejs nginx unzip certbot python3-certbot-nginx
  node -v   # doit afficher v22.13 ou plus
  ```
- Le domaine `tapikard.com` doit pointer vers l'adresse IP du serveur (enregistrements DNS A, et AAAA si IPv6).

## 2. Copier l'application

```bash
scp tapikard-*.zip utilisateur@serveur:/tmp/
ssh utilisateur@serveur
sudo useradd --system --home /opt/tapikard --shell /usr/sbin/nologin tapikard
sudo unzip /tmp/tapikard-*.zip -d /opt/          # crée /opt/tapikard
sudo mkdir -p /var/lib/tapikard /etc/tapikard
sudo chown -R tapikard:tapikard /var/lib/tapikard
```

## 3. Configurer les secrets

```bash
sudo cp /opt/tapikard/tapikard.env.example /etc/tapikard/tapikard.env
sudo nano /etc/tapikard/tapikard.env
sudo chmod 600 /etc/tapikard/tapikard.env
```

Valeurs **obligatoires** :

| Variable | Valeur |
|---|---|
| `APP_URL` | `https://tapikard.com` |
| `DATABASE_PATH` | `/var/lib/tapikard/tapikard.db` |
| `SESSION_SECRET` | résultat de `openssl rand -hex 32` |
| `ADMIN_EMAILS` | votre email (accès au back-office) |
| `SUN_K_META` | `openssl rand -hex 16` — **les mêmes que sur le poste d'encodage** |
| `SUN_MASTER_K1` | `openssl rand -hex 16` — idem |
| `ENCODING_API_KEY` | `openssl rand -hex 24` |

Laissez `SEED_DEMO` absent (pas de données de démonstration en production) et ne mettez **pas** `SUN_FACTORY_KEYS=1`.

⚠️ Sauvegardez `SUN_K_META` et `SUN_MASTER_K1` hors du serveur (coffre + papier). Si vous les perdez, aucune carte déjà vendue ne pourra plus être vérifiée.

**Emails (indispensable pour recevoir les liens de connexion)** : `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `EMAIL_FROM` — ou `RESEND_API_KEY`.

Optionnel : `STRIPE_SECRET_KEY` + `STRIPE_WEBHOOK_SECRET` (sinon le paiement est simulé).

## 4. Démarrer le service

```bash
sudo cp /opt/tapikard/tapikard.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now tapikard
sudo systemctl status tapikard          # doit être « active (running) »
curl -I http://127.0.0.1:3000           # HTTP/1.1 200
```

Journaux : `sudo journalctl -u tapikard -f`

## 5. HTTPS avec nginx

```bash
sudo cp /opt/tapikard/nginx-tapikard.conf /etc/nginx/sites-available/tapikard
sudo ln -s /etc/nginx/sites-available/tapikard /etc/nginx/sites-enabled/
sudo certbot --nginx -d tapikard.com -d www.tapikard.com
sudo nginx -t && sudo systemctl reload nginx
```

Ouvrez https://tapikard.com, connectez-vous avec l'email mis dans `ADMIN_EMAILS` : vous accédez au back-office.

## 6. Stripe (si activé)

Dans le tableau de bord Stripe → Développeurs → Webhooks : ajoutez l'URL
`https://tapikard.com/api/stripe/webhook`, événement `checkout.session.completed`,
puis copiez le secret de signature dans `STRIPE_WEBHOOK_SECRET` et redémarrez : `sudo systemctl restart tapikard`.

## 7. Sauvegardes

La base est un seul fichier. Sauvegarde quotidienne cohérente (même service en marche) :

```bash
sudo crontab -e
# Ajouter :
15 3 * * * sqlite3 /var/lib/tapikard/tapikard.db ".backup '/var/backups/tapikard-$(date +\%F).db'" && find /var/backups -name 'tapikard-*.db' -mtime +30 -delete
```
(`sudo apt install sqlite3`.) Copiez aussi ces sauvegardes hors du serveur.

## 8. Mettre à jour

```bash
sudo systemctl stop tapikard
sudo rm -rf /opt/tapikard && sudo unzip /tmp/tapikard-NOUVELLE.zip -d /opt/
sudo systemctl start tapikard
```
La base (`/var/lib/tapikard`) et la configuration (`/etc/tapikard`) ne sont pas touchées.

## Contenu du paquet

| Élément | Rôle |
|---|---|
| `server.js`, `.next/`, `node_modules/` | Application compilée (production) |
| `tapikard.env.example` | Modèle de configuration |
| `tapikard.service` | Service systemd |
| `nginx-tapikard.conf` | Proxy HTTPS |
| `outils/sun_verify.py`, `outils/encodage-ntag424.md` | Vérification SUN et guide pour le poste d'encodage |
