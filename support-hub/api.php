<?php
declare(strict_types=1);

/**
 * API appelée par les serveurs des applications clientes (jamais directement par le navigateur).
 * Authentification : en-tête X-Api-Key (clé créée dans la console, une par client).
 *
 *   GET  api.php?a=status
 *   POST api.php?a=open   {user:{name,email,role,center}, context, transcript:[{from,text}], message}
 *   POST api.php?a=send   {id, token, text}
 *   GET  api.php?a=poll&id=&token=&after=
 *   POST api.php?a=close  {id, token}
 *   POST api.php?a=check  {app, version, url, php, stats:{users,centers}, faq_hash}  → licence, dernière version, FAQ partagée, tutoriels vidéo
 *   GET  api.php?a=download&v=1.2.3                                                  → paquet de mise à jour (licence valide)
 *   POST api.php?a=attach {id, token, data (image en base64), text}                   → capture d'écran jointe
 *   GET  api.php?a=file&id=&token=&f=                                                 → image d'une conversation
 *   POST api.php?a=rate   {id, token, rating (1-5), comment}                          → satisfaction après clôture
 *   GET  api.php?a=video&id=…                                                         → fichier d'un tutoriel vidéo (licence à jour)
 *   GET  api.php?a=faq                                                                → FAQ partagée seule
 *   POST api.php?a=billing                                                            → lien de la page de paiement (abonnement en ligne)
 *   POST api.php?a=stripe                                                             → webhook Stripe (signature vérifiée, sans clé)
 *
 * Console de la plateforme Centriva (en-tête X-Console-Key, clé de liaison créée dans Réglages → Console Centriva) :
 *   POST api.php?a=console_link    {app, console_url}         → l'application est désormais gérée par la console (pages masquées ici)
 *   GET  api.php?a=console_export&app=centriva                → historique à reprendre : versions, vidéos, FAQ, clients, paiements, tarifs
 *   GET  api.php?a=console_file&kind=release|video&id=        → paquet d'une version ou fichier d'une vidéo
 *   POST api.php?a=console_client  {app, slug, name, url, active, hub_id, new_key} → crée ou met à jour l'accès d'un espace (clé en clair si créée)
 */
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Notifications de paiement envoyées par Stripe : authentifiées par leur signature, pas par une clé client
if (($_GET['a'] ?? '') === 'stripe') {
    require HUB . '/views/stripe-webhook.php';
    exit;
}

function out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Console de la plateforme Centriva : gestion des clients et reprise de l'historique
if (str_starts_with((string)($_GET['a'] ?? ''), 'console_')) {
    if (!hub_console_key_ok((string)($_SERVER['HTTP_X_CONSOLE_KEY'] ?? ''))) {
        out(['error' => 'Clé de liaison invalide.'], 401);
    }
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $app = hub_app_slug(preg_replace('/[^a-z0-9_-]/', '', strtolower((string)($in['app'] ?? $_GET['app'] ?? 'centriva'))) ?: 'centriva');
    switch ((string)$_GET['a']) {
        case 'console_link':
            $url = (string)($in['console_url'] ?? '');
            if (!preg_match('#^https?://#', $url)) {
                out(['error' => 'Adresse de la console invalide.'], 422);
            }
            if (!hub_app($app)) {
                hq('INSERT INTO apps (slug, name, created_at) VALUES (?, ?, ?)', [$app, ucfirst($app), hnow()]);
            }
            hq('UPDATE apps SET console_url = ? WHERE slug = ?', [mb_substr($url, 0, 255), $app]);
            out(['ok' => true, 'hub_version' => hub_version(), 'operator' => hcfg('operator_name')]);

        case 'console_export':
            $a = hub_app($app) ?? ['price_base' => 0, 'price_ai' => 0];
            $clients = hall('SELECT * FROM clients WHERE app = ? ORDER BY id', [$app]);
            foreach ($clients as &$c) {
                $c['events'] = hall('SELECT id, stripe_id, type, amount, label, url, created_at FROM billing_events WHERE client_id = ? ORDER BY id', [$c['id']]);
                unset($c['prev_key_hash'], $c['prev_key_until']);
            }
            unset($c);
            out([
                'settings' => ['price_base' => (float)$a['price_base'], 'price_ai' => (float)$a['price_ai'], 'vat' => hub_vat_rate(), 'grace_days' => hub_grace_days(),
                    'operator_name' => (string)hcfg('operator_name'), 'operator_email' => (string)hcfg('notify_email'), 'stripe_secret_key' => hub_stripe_key()],
                'releases' => hall('SELECT id, version, notes, sha256, size, published, created_at FROM releases WHERE app = ? ORDER BY id', [$app]),
                'videos' => hall("SELECT id, uid, title, description, keywords, chapters, audience, sha256, size, duration, position, published, welcome, created_at FROM videos WHERE app IN ('*', ?) ORDER BY position, id", [$app]),
                'faq' => hall("SELECT id, question, keywords, answer, link_label, link_route, admin_only, active FROM faq WHERE app IN ('*', ?) ORDER BY id", [$app]),
                'clients' => $clients,
            ]);

        case 'console_file':
            $kind = (string)($_GET['kind'] ?? '');
            $row = $kind === 'release' ? hone('SELECT file FROM releases WHERE id = ?', [(int)($_GET['id'] ?? 0)])
                : ($kind === 'video' ? hone('SELECT file FROM videos WHERE id = ?', [(int)($_GET['id'] ?? 0)]) : null);
            $path = $row ? ($kind === 'release' ? hub_releases_dir() : hub_videos_dir()) . '/' . basename((string)$row['file']) : '';
            if (!$row || !is_file($path)) {
                out(['error' => 'Fichier introuvable.'], 404);
            }
            @set_time_limit(0);
            header_remove('Content-Type');
            header('Content-Type: application/octet-stream');
            header('Content-Length: ' . filesize($path));
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            readfile($path);
            exit;

        case 'console_client':
            $slug = preg_replace('/[^a-z0-9-]/', '', (string)($in['slug'] ?? ''));
            $c = !empty($in['hub_id']) ? hone('SELECT * FROM clients WHERE id = ? AND app = ?', [(int)$in['hub_id'], $app]) : null;
            $c ??= $slug !== '' ? hone('SELECT * FROM clients WHERE console_slug = ? AND app = ?', [$slug, $app]) : null;
            $key = null;
            if (!$c) {
                if (trim((string)($in['name'] ?? '')) === '') {
                    out(['error' => 'Nom du client manquant.'], 422);
                }
                $key = hub_create_client((string)$in['name'], (string)($in['url'] ?? ''));
                $c = hone('SELECT * FROM clients WHERE id = ?', [(int)hdb()->lastInsertId()]);
                hq('UPDATE clients SET app = ? WHERE id = ?', [$app, $c['id']]);
            } elseif (!empty($in['new_key'])) {
                $key = hub_rotate_key((int)$c['id']);
            }
            hq('UPDATE clients SET console_slug = ?, active = ?, name = COALESCE(?, name), site = COALESCE(?, site), instance_url = COALESCE(?, instance_url) WHERE id = ?', [
                $slug ?: $c['console_slug'], array_key_exists('active', $in) ? (int)(bool)$in['active'] : (int)$c['active'],
                trim((string)($in['name'] ?? '')) !== '' ? mb_substr((string)$in['name'], 0, 120) : null,
                !empty($in['url']) ? mb_substr((string)$in['url'], 0, 200) : null, !empty($in['url']) ? mb_substr((string)$in['url'], 0, 255) : null, $c['id'],
            ]);
            out(['ok' => true, 'id' => (int)$c['id']] + ($key ? ['key' => $key] : []));
    }
    out(['error' => 'Action inconnue.'], 400);
}

$client = hub_client_from_key((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
if (!$client) {
    out(['error' => 'Clé d\'accès invalide.'], 401);
}
hq('UPDATE clients SET last_seen = ? WHERE id = ?', [hnow(), $client['id']]);

$a = (string)($_GET['a'] ?? '');
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$str = fn(mixed $v, int $max) => mb_substr(trim((string)$v), 0, $max);

/** Conversation du client, vérifiée par son jeton. */
function conv_or_fail(array $client, mixed $id, mixed $token): array
{
    $c = hone('SELECT * FROM conversations WHERE id = ? AND client_id = ?', [(int)$id, $client['id']]);
    if (!$c || !hash_equals($c['token'], (string)$token)) {
        out(['error' => 'Conversation introuvable.'], 404);
    }
    return $c;
}

switch ($a) {
    case 'status':
        out(hub_status());

    case 'open':
        $u = (array)($in['user'] ?? []);
        $message = $str($in['message'] ?? '', 4000);
        if ($message === '') {
            out(['error' => 'Message vide.'], 422);
        }
        $token = bin2hex(random_bytes(16));
        hq('INSERT INTO conversations (client_id, token, user_name, user_email, user_role, center, context, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)', [
            $client['id'], $token, $str($u['name'] ?? '', 120), $str($u['email'] ?? '', 190), $str($u['role'] ?? '', 40),
            $str($u['center'] ?? '', 150), $str($in['context'] ?? '', 2000), hnow(), hnow(),
        ]);
        $id = (int)hdb()->lastInsertId();
        // Échange avec le chatbot conservé pour le contexte
        foreach (array_slice((array)($in['transcript'] ?? []), -12) as $t) {
            $from = ($t['from'] ?? '') === 'user' ? 'user_bot' : 'bot';
            if ($txt = $str($t['text'] ?? '', 1500)) {
                hub_add_message($id, $from, $txt);
            }
        }
        hub_add_message($id, 'user', $message);
        $conv = hone('SELECT * FROM conversations WHERE id = ?', [$id]);
        hub_alert($conv, 'Nouvelle conversation — ' . $client['name'], ($conv['user_name'] ?: 'Utilisateur') . ($conv['center'] ? ' (' . $conv['center'] . ')' : '') . " :\n" . $message);
        out(['id' => $id, 'token' => $token, 'status' => hub_status(), 'messages' => hub_messages($id)]);

    case 'send':
        $c = conv_or_fail($client, $in['id'] ?? 0, $in['token'] ?? '');
        $text = $str($in['text'] ?? '', 4000);
        if ($text === '') {
            out(['error' => 'Message vide.'], 422);
        }
        $mid = hub_add_message((int)$c['id'], 'user', $text);
        hub_alert($c, 'Nouveau message — ' . $client['name'], ($c['user_name'] ?: 'Utilisateur') . " :\n" . $text);
        out(['ok' => true, 'id' => $mid]);

    case 'poll':
        $c = conv_or_fail($client, $_GET['id'] ?? 0, $_GET['token'] ?? '');
        out(['status' => $c['status'], 'rated' => $c['rating'] !== null, 'availability' => hub_status(), 'messages' => hub_messages((int)$c['id'], (int)($_GET['after'] ?? 0))]);

    case 'close':
        $c = conv_or_fail($client, $in['id'] ?? 0, $in['token'] ?? '');
        hq("UPDATE conversations SET status = 'closed', updated_at = ? WHERE id = ?", [hnow(), $c['id']]);
        hub_add_message((int)$c['id'], 'system', 'Conversation terminée par l\'utilisateur.');
        out(['ok' => true]);

    case 'attach':
        $c = conv_or_fail($client, $in['id'] ?? 0, $in['token'] ?? '');
        try {
            $file = hub_store_image((string)base64_decode((string)($in['data'] ?? ''), true));
        } catch (RuntimeException $e) {
            out(['error' => $e->getMessage()], 422);
        }
        $mid = hub_add_message((int)$c['id'], 'user', $str($in['text'] ?? '', 1000) ?: 'Capture d\'écran', $file);
        hub_alert($c, 'Capture d\'écran — ' . $client['name'], ($c['user_name'] ?: 'Utilisateur') . ' a envoyé une image.');
        out(['ok' => true, 'id' => $mid]);

    case 'file':
        $c = conv_or_fail($client, $_GET['id'] ?? 0, $_GET['token'] ?? '');
        $f = hone('SELECT file FROM messages WHERE conversation_id = ? AND file = ?', [$c['id'], basename((string)($_GET['f'] ?? ''))]);
        $path = $f ? hub_files_dir() . '/' . $f['file'] : '';
        if (!$f || !is_file($path)) {
            out(['error' => 'Fichier introuvable.'], 404);
        }
        header('Content-Type: ' . (getimagesize($path)['mime'] ?? 'application/octet-stream'));
        header('Cache-Control: private, max-age=86400');
        readfile($path);
        exit;

    case 'rate':
        $c = conv_or_fail($client, $in['id'] ?? 0, $in['token'] ?? '');
        $rating = max(1, min(5, (int)($in['rating'] ?? 0)));
        hq('UPDATE conversations SET rating = ?, rating_comment = ? WHERE id = ?', [$rating, $str($in['comment'] ?? '', 500) ?: null, $c['id']]);
        hub_add_message((int)$c['id'], 'system', 'Satisfaction : ' . str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) . (($in['comment'] ?? '') ? ' — « ' . $str($in['comment'], 500) . ' »' : ''));
        out(['ok' => true]);

    case 'faq':
        out(['items' => hub_faq_for((string)($client['app'] ?: 'centriva'))]);

    case 'check':
        // Inventaire du parc : version installée, adresse, statistiques d'usage (sans donnée personnelle)
        $app = hub_app_slug(preg_replace('/[^a-z0-9_-]/', '', strtolower((string)($in['app'] ?? $client['app'] ?? 'centriva'))) ?: 'centriva');
        hq('UPDATE clients SET app = ?, app_version = ?, instance_url = ?, php_version = ?, stats = ?, last_check = ? WHERE id = ?', [
            $app, $str($in['version'] ?? '', 30), $str($in['url'] ?? '', 255), $str($in['php'] ?? '', 20),
            json_encode(array_map('intval', array_slice((array)($in['stats'] ?? []), 0, 10))), hnow(), $client['id'],
        ]);
        $client = hone('SELECT * FROM clients WHERE id = ?', [$client['id']]);
        $lic = hub_licence($client);
        $latest = hub_latest_release($app);
        $faq = hub_faq_for($app);
        $faqHash = substr(sha1(json_encode($faq)), 0, 16);
        $videos = hub_videos_for($app);
        $videosHash = substr(sha1(json_encode($videos)), 0, 16);
        out([
            'licence' => $lic,
            'latest' => $latest ? ['version' => $latest['version'], 'notes' => $latest['notes'], 'date' => substr($latest['created_at'], 0, 10),
                'size' => (int)$latest['size'], 'sha256' => $latest['sha256'], 'downloadable' => in_array($lic['status'], ['active', 'grace'], true)] : null,
            'faq' => ['hash' => $faqHash] + (($in['faq_hash'] ?? '') !== $faqHash ? ['items' => $faq] : []),
            'videos' => ['hash' => $videosHash] + (($in['videos_hash'] ?? '') !== $videosHash ? ['items' => $videos] : []),
            'billing' => hub_billing_summary($client),
            'status' => hub_status(),
        ]);

    case 'billing':
        if (!hub_stripe_ready()) {
            out(['error' => 'Le paiement en ligne n\'est pas ouvert : contactez ' . hcfg('operator_name') . '.'], 409);
        }
        out(['url' => hub_pay_url($client)] + hub_billing_summary($client));

    case 'download':
        $lic = hub_licence($client);
        if (!in_array($lic['status'], ['active', 'grace'], true)) {
            out(['error' => 'Licence ' . ($lic['status'] === 'suspended' ? 'suspendue' : 'expirée') . ' : mise à jour indisponible. Contactez ' . hcfg('operator_name') . '.'], 402);
        }
        $r = hone('SELECT * FROM releases WHERE app = ? AND version = ? AND published = 1', [$client['app'] ?: 'centriva', (string)($_GET['v'] ?? '')]);
        $path = $r ? hub_releases_dir() . '/' . basename($r['file']) : '';
        if (!$r || !is_file($path)) {
            out(['error' => 'Version introuvable.'], 404);
        }
        header_remove('Content-Type');
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($path));
        header('X-Sha256: ' . $r['sha256']);
        header('Content-Disposition: attachment; filename="' . $r['app'] . '-' . $r['version'] . '.zip"');
        readfile($path);
        exit;

    case 'video':
        // Tutoriel vidéo, téléchargé une fois par chaque installation (en arrière-plan)
        $lic = hub_licence($client);
        if (!in_array($lic['status'], ['active', 'grace'], true)) {
            out(['error' => 'Licence non à jour.'], 402);
        }
        $v = hone("SELECT * FROM videos WHERE uid = ? AND published = 1 AND (app = '*' OR app = ?)", [(string)($_GET['id'] ?? ''), $client['app'] ?: 'centriva']);
        $path = $v ? hub_videos_dir() . '/' . basename($v['file']) : '';
        if (!$v || !is_file($path)) {
            out(['error' => 'Vidéo introuvable.'], 404);
        }
        @set_time_limit(0);
        header_remove('Content-Type');
        header('Content-Type: video/mp4');
        header('Content-Length: ' . filesize($path));
        header('X-Sha256: ' . $v['sha256']);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($path);
        exit;

    default:
        out(['error' => 'Action inconnue.'], 400);
}
