<?php
declare(strict_types=1);

/**
 * Notifications : dans l'application (cloche) et, en option, par e-mail.
 * Chaque événement peut être désactivé globalement (paramètres) ou par utilisateur (profil).
 */

const NOTIFY_EVENTS = [
    'request_new'     => ['label' => 'Nouvelle demande d\'un centre',            'for' => 'admin'],
    'account_pending' => ['label' => 'Nouvelle demande de compte',               'for' => 'admin'],
    'request_refused' => ['label' => 'Ligne de demande refusée',                 'for' => 'user'],
    'po_created'      => ['label' => 'Demande validée (bon « à commander »)',    'for' => 'user'],
    'po_ordered'      => ['label' => 'Commande passée chez le fournisseur',      'for' => 'user'],
    'po_received'     => ['label' => 'Commande réceptionnée (partielle ou totale)', 'for' => 'both'],
    'po_cancelled'    => ['label' => 'Bon de commande annulé',                    'for' => 'user'],
    'budget_alert'    => ['label' => 'Seuil de budget atteint',                  'for' => 'admin'],
    'stock_low'       => ['label' => 'Stock sous le seuil d\'alerte',            'for' => 'both'],
];

function admin_ids(): array
{
    return array_map('intval', array_column(all("SELECT id FROM users WHERE role = 'admin' AND status = 'active'"), 'id'));
}

function notify_event_enabled(string $type): bool
{
    return setting('notif_' . $type, '1') === '1';
}

function user_notify_prefs(array $u): array
{
    $p = json_decode((string)($u['notify_prefs'] ?? ''), true);
    return is_array($p) ? $p : [];
}

/** Crée la notification pour chaque destinataire et envoie l'e-mail si autorisé. */
function notify(array $userIds, string $type, string $title, string $body = '', string $link = ''): void
{
    if (!notify_event_enabled($type)) {
        return;
    }
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if (!$userIds) {
        return;
    }
    $me = (int)(user()['id'] ?? 0);
    $mailOn = setting('mail_enabled', '0') === '1';
    foreach (all('SELECT * FROM users WHERE status = \'active\' AND id IN ' . in_list($userIds), $userIds) as $u) {
        if ((int)$u['id'] === $me && $type !== 'stock_low') {
            continue; // on ne se notifie pas de sa propre action
        }
        $prefs = user_notify_prefs($u);
        if (($prefs[$type] ?? true) === false) {
            continue;
        }
        try {
            insert('notifications', [
                'user_id' => $u['id'], 'type' => $type, 'title' => mb_substr($title, 0, 255),
                'body' => $body ?: null, 'link' => $link ?: null, 'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            error_log('[notify] ' . $e->getMessage());
        }
        if ($mailOn && (int)$u['notify_email'] === 1) {
            send_mail($u['email'], $title, mail_template($u, $title, $body, $link));
        }
    }
}

function unread_notifications(int $userId): int
{
    try {
        return (int)val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
    } catch (Throwable) {
        return 0;
    }
}

// ---------------------------------------------------------------- Événements métier

/** Demandeurs (distincts) des lignes liées à un bon. */
function po_requester_ids(int $poId): array
{
    return array_map('intval', array_column(all('SELECT DISTINCT r.user_id FROM request_lines rl JOIN requests r ON r.id = rl.request_id
                                                  WHERE rl.purchase_order_id = ?', [$poId]), 'user_id'));
}

function po_summary(int $poId): array
{
    return one('SELECT po.*, s.name AS supplier_name, c.name AS center_name FROM purchase_orders po
                JOIN suppliers s ON s.id = po.supplier_id JOIN centers c ON c.id = po.center_id WHERE po.id = ?', [$poId]) ?? [];
}

function notify_po(int $poId, string $type): void
{
    $po = po_summary($poId);
    if (!$po) {
        return;
    }
    $requesters = po_requester_ids($poId);
    $link = url('requests');
    switch ($type) {
        case 'po_created':
            notify($requesters, $type, 'Demande validée — ' . $po['supplier_name'],
                'Vos articles ' . $po['supplier_name'] . ' pour ' . $po['center_name'] . ' sont regroupés sur le bon ' . $po['po_number'] . ', prochainement commandé.', $link);
            break;
        case 'po_ordered':
            notify($requesters, $type, 'Commandé chez ' . $po['supplier_name'],
                'Le bon ' . $po['po_number'] . ' a été transmis au fournisseur' . ($po['expected_date'] ? ', livraison prévue le ' . date_fr($po['expected_date']) : '') . '. Pensez à le réceptionner à son arrivée.',
                url('reception', ['id' => $poId]));
            break;
        case 'po_received':
            $full = $po['status'] === 'recu';
            $t = ($full ? 'Livraison complète' : 'Livraison partielle') . ' — ' . $po['supplier_name'] . ' (' . $po['center_name'] . ')';
            $b = 'Le bon ' . $po['po_number'] . ($full ? ' a été entièrement réceptionné.' : ' a été partiellement réceptionné.');
            notify($requesters, $type, $t, $b, url('reception', ['id' => $poId]));
            notify(admin_ids(), $type, $t, $b, url('admin/order', ['id' => $poId]));
            break;
        case 'po_cancelled':
            notify($requesters, $type, 'Bon annulé — ' . $po['supplier_name'],
                'Le bon ' . $po['po_number'] . ' a été annulé par le service achats.', $link);
            break;
    }
}

function notify_stock_low(int $centerId, int $productId, int $qty, int $alert): void
{
    $p = one('SELECT p.name, c.name AS center_name FROM products p, centers c WHERE p.id = ? AND c.id = ?', [$productId, $centerId]);
    if (!$p) {
        return;
    }
    $users = array_map('intval', array_column(all('SELECT user_id FROM user_centers WHERE center_id = ?', [$centerId]), 'user_id'));
    $title = 'Stock bas : ' . $p['name'] . ' (' . $p['center_name'] . ')';
    $body = 'Il reste ' . $qty . ' unité(s) pour un seuil d\'alerte de ' . $alert . '.';
    notify(array_merge(admin_ids(), $users), 'stock_low', $title, $body, url('stock', ['c' => $centerId, 'filter' => 'low']));
}

// ---------------------------------------------------------------- E-mail

function app_base_url(): string
{
    $u = setting('app_url');
    if ($u) {
        return rtrim($u, '/') . '/';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https://' : 'http://') . $host . $dir . '/';
}

function mail_template(array $u, string $title, string $body, string $link): string
{
    $btn = $link ? '<p style="margin:24px 0"><a href="' . e(app_base_url() . $link) . '" style="background:#6366f1;color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none;font-weight:600">Ouvrir dans l\'application</a></p>' : '';
    return '<!doctype html><html><body style="margin:0;background:#f4f6fb;font-family:Arial,sans-serif;color:#1e2335">
      <div style="max-width:560px;margin:24px auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e6e9f2">
        <div style="background:linear-gradient(135deg,#6366f1,#8b5cf6,#ec4899);padding:20px 24px;color:#fff;font-weight:700;font-size:18px">' . e(app_name()) . '</div>
        <div style="padding:24px">
          <p style="margin:0 0 8px;color:#6b7290">Bonjour ' . e($u['first_name']) . ',</p>
          <h2 style="margin:0 0 12px;font-size:18px">' . e($title) . '</h2>
          <p style="margin:0;line-height:1.5">' . nl2br(e($body)) . '</p>' . $btn . '
          <p style="margin:24px 0 0;font-size:12px;color:#6b7290">Vous pouvez désactiver ces e-mails depuis « Mon profil ».</p>
        </div>
      </div></body></html>';
}

/** Envoie un e-mail HTML (SMTP si configuré, sinon fonction mail() de l'hébergement). */
function send_mail(string $to, string $subject, string $html): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $fromEmail = setting('mail_from') ?: ('no-reply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
    $fromName = setting('mail_from_name') ?: app_name();
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>',
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $fromEmail)[1] ?? 'localhost') . '>',
    ];
    $body = chunk_split(base64_encode($html));
    try {
        if (setting('smtp_host')) {
            return smtp_send($fromEmail, $to, $encSubject, $headers, $body);
        }
        return @mail($to, $encSubject, $body, implode("\r\n", $headers), '-f' . $fromEmail);
    } catch (Throwable $e) {
        error_log('[mail] ' . $e->getMessage());
        return false;
    }
}

/** Client SMTP minimal (SSL 465 ou STARTTLS 587, authentification LOGIN). */
function smtp_send(string $from, string $to, string $subject, array $headers, string $body): bool
{
    $host = (string)setting('smtp_host');
    $port = (int)(setting('smtp_port') ?: 587);
    $secure = setting('smtp_secure', 'tls');
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15);
    if (!$fp) {
        throw new RuntimeException("SMTP : connexion impossible ($errstr)");
    }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (string $c, array $ok) use ($fp, $read): string {
        if ($c !== '') {
            fwrite($fp, $c . "\r\n");
        }
        $r = $read();
        if (!in_array((int)substr($r, 0, 3), $ok, true)) {
            throw new RuntimeException('SMTP : réponse inattendue « ' . trim($r) . ' »');
        }
        return $r;
    };
    $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $cmd('', [220]);
    $cmd($ehlo, [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('SMTP : échec STARTTLS');
        }
        $cmd($ehlo, [250]);
    }
    if (setting('smtp_user')) {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode((string)setting('smtp_user')), [334]);
        $cmd(base64_encode((string)setting('smtp_pass')), [235]);
    }
    $cmd('MAIL FROM:<' . $from . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    $cmd('DATA', [354]);
    $msg = implode("\r\n", array_merge(['To: <' . $to . '>', 'Subject: ' . $subject], $headers)) . "\r\n\r\n" . $body;
    $msg = preg_replace('/^\./m', '..', $msg);
    $cmd($msg . "\r\n.", [250]);
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return true;
}
