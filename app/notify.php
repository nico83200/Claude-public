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
    'suggestion_new'  => ['label' => 'Article hors catalogue proposé',          'for' => 'admin'],
    'suggestion_done' => ['label' => 'Réponse à un article proposé',             'for' => 'user'],
    'approval_needed' => ['label' => 'Demande à valider (responsable de centre)', 'for' => 'manager'],
    'approval_done'   => ['label' => 'Décision du responsable sur une demande',  'for' => 'user'],
    'deadline_reminder' => ['label' => 'Rappel la veille d\'une date limite',    'for' => 'user'],
    'delivery_late'   => ['label' => 'Livraison en retard',                      'for' => 'both'],
    'price_increase'  => ['label' => 'Hausse de prix d\'un article',             'for' => 'admin'],
    'budget_alert'    => ['label' => 'Seuil de budget atteint',                  'for' => 'admin'],
    'stock_low'       => ['label' => 'Stock sous le seuil d\'alerte',            'for' => 'both'],
    'cycle_count'     => ['label' => 'Inventaire tournant de la semaine',         'for' => 'both'],
    'support_reply'   => ['label' => 'Réponse de l\'assistance NLapps',          'for' => 'both'],
];

/** E-mails envoyés en dehors des notifications, activables un par un. */
const MAIL_CASES = [
    'password_reset' => 'Mot de passe oublié (lien de réinitialisation)',
    'supplier_po'    => 'Envoi des bons de commande aux fournisseurs',
    'supplier_copy'  => 'Copie à l\'expéditeur des bons envoyés aux fournisseurs',
];

/** L'envoi d'e-mails est-il activé (interrupteur général + interrupteur du cas) ? */
function mail_case_enabled(string $case): bool
{
    return setting('mail_enabled', '0') === '1' && setting('mailev_' . $case, '1') === '1';
}

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
    $mailOn = mail_case_enabled($type);
    foreach (all('SELECT * FROM users WHERE status = \'active\' AND id IN ' . in_list($userIds), $userIds) as $u) {
        if ((int)$u['id'] === $me && !in_array($type, ['stock_low', 'support_reply'], true)) {
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
        ' . (($logo = brand_logo_url()) ? '<div style="padding:18px 24px;border-bottom:4px solid #6366f1"><img src="' . e(app_base_url() . $logo) . '" alt="' . e(setting('company_name') ?: app_name()) . '" style="max-height:56px;max-width:200px"></div>'
            : '<div style="background:linear-gradient(135deg,#6366f1,#8b5cf6,#ec4899);padding:20px 24px;color:#fff;font-weight:700;font-size:18px">' . e(app_name()) . '</div>') . '
        <div style="padding:24px">
          <p style="margin:0 0 8px;color:#6b7290">Bonjour ' . e($u['first_name']) . ',</p>
          <h2 style="margin:0 0 12px;font-size:18px">' . e($title) . '</h2>
          <p style="margin:0;line-height:1.5">' . nl2br(e($body)) . '</p>' . $btn . '
          <p style="margin:24px 0 0;font-size:12px;color:#6b7290">Vous pouvez désactiver ces e-mails depuis « Mon profil ».</p>
        </div>
      </div></body></html>';
}

/**
 * Met un e-mail en file d'attente : il est envoyé en arrière-plan (fin de requête ou tâche planifiée),
 * avec nouvelles tentatives en cas d'échec. $attachments : [['path' => ..., 'name' => ..., 'type' => ...]].
 */
function send_mail(string $to, string $subject, string $html, array $attachments = []): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || setting('mail_enabled', '0') !== '1') {
        return false;
    }
    try {
        insert('mail_queue', [
            'to_email' => $to, 'subject' => mb_substr($subject, 0, 255), 'html' => $html,
            'attachments' => $attachments ? json_encode($attachments, JSON_UNESCAPED_UNICODE) : null,
            'attempts' => 0, 'created_at' => now(),
        ]);
        $GLOBALS['mail_pending'] = true;
        return true;
    } catch (Throwable $e) {
        error_log('[mail] ' . $e->getMessage());
        return false;
    }
}

/** Envoi immédiat d'un e-mail HTML (SMTP si configuré, sinon fonction mail() de l'hébergement). */
function send_mail_now(string $to, string $subject, string $html, array $attachments = []): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $fromEmail = setting('mail_from') ?: ('no-reply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
    $fromName = setting('mail_from_name') ?: app_name();
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>',
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $fromEmail)[1] ?? 'localhost') . '>',
    ];
    $htmlPart = chunk_split(base64_encode($html));
    $files = array_filter($attachments, fn($a) => !empty($a['path']) && is_file($a['path']));
    if ($files) {
        $boundary = 'b_' . bin2hex(random_bytes(10));
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        $body = "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $htmlPart;
        foreach ($files as $a) {
            $name = '=?UTF-8?B?' . base64_encode((string)($a['name'] ?? basename($a['path']))) . '?=';
            $body .= "--$boundary\r\nContent-Type: " . ($a['type'] ?? 'application/octet-stream') . '; name="' . $name . "\"\r\n"
                . 'Content-Disposition: attachment; filename="' . $name . "\"\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode((string)file_get_contents($a['path'])));
        }
        $body .= "--$boundary--\r\n";
    } else {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $body = $htmlPart;
    }
    if (setting('smtp_host')) {
        return smtp_send($fromEmail, $to, $encSubject, $headers, $body);
    }
    if (!@mail($to, $encSubject, $body, implode("\r\n", $headers), '-f' . $fromEmail)) {
        throw new RuntimeException('La fonction mail() a refusé l\'envoi.');
    }
    return true;
}

/** Traite la file d'attente (jusqu'à $max e-mails) ; 5 tentatives espacées au maximum. */
function mail_queue_process(int $max = 20): int
{
    $sent = 0;
    $rows = all('SELECT * FROM mail_queue WHERE sent_at IS NULL AND attempts < 5 ORDER BY id LIMIT ' . (int)$max);
    foreach ($rows as $m) {
        // délai croissant entre deux tentatives (0, 5, 15, 45, 135 minutes)
        if ($m['attempts'] > 0 && strtotime((string)$m['created_at']) + 300 * (3 ** ($m['attempts'] - 1)) > time()) {
            continue;
        }
        $claimed = q('UPDATE mail_queue SET attempts = attempts + 1 WHERE id = ? AND attempts = ? AND sent_at IS NULL', [$m['id'], $m['attempts']])->rowCount();
        if (!$claimed) {
            continue; // déjà pris par un autre processus
        }
        try {
            send_mail_now($m['to_email'], $m['subject'], $m['html'], json_decode((string)$m['attachments'], true) ?: []);
            update('mail_queue', ['sent_at' => now(), 'last_error' => null], 'id = ?', [$m['id']]);
            $sent++;
        } catch (Throwable $e) {
            update('mail_queue', ['last_error' => mb_substr($e->getMessage(), 0, 255)], 'id = ?', [$m['id']]);
            error_log('[mail] ' . $e->getMessage());
        }
    }
    return $sent;
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
        $cmd(base64_encode(decrypt_secret(setting('smtp_pass'))), [235]);
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
