<?php
declare(strict_types=1);

/** Chatbot d'assistance (appel depuis la bulle d'aide). */
function api_support_ask(): void
{
    $u = require_login();
    $q = trim((string)input('q', ''));
    if (mb_strlen($q) < 2) {
        json_response(['answer' => 'Posez votre question en quelques mots, par exemple « comment scanner un code-barres ? ».', 'links' => [], 'source' => 'none', 'confident' => false]);
    }
    // Demande explicite d'un humain : on passe directement la main à l'équipe
    if (preg_match('/\b(humain|conseiller|quelqu.?un|une personne|vraie personne|parler a|parler avec|operateur|technicien|joindre|appeler)\b/u', search_normalize($q))) {
        json_response(['answer' => 'Bien sûr, je vous mets en relation avec l\'équipe ' . support_contact()['editor'] . '.', 'links' => [], 'source' => 'human',
            'confident' => false, 'human' => true, 'live' => support_live_enabled(), 'form' => url('support', ['q' => mb_substr($q, 0, 300)])]);
    }
    $r = support_answer($q, ($u['role'] ?? '') === 'admin', (string)input('page', ''));
    $r['live'] = support_live_enabled();
    $r['form'] = url('support', ['q' => mb_substr($q, 0, 300), 'page' => mb_substr((string)input('page', ''), 0, 120)]);
    json_response($r);
}

/** Ouvre une conversation sur le centre d'assistance et l'enregistre. Renvoie la conversation locale, ou null si le centre ne répond pas. */
function support_chat_start(array $u, ?array $center, string $message, array $transcript = []): ?array
{
    $r = support_hub('open', [], [
        'user' => ['name' => $u['first_name'] . ' ' . $u['last_name'], 'email' => $u['email'],
            'role' => ['admin' => 'Administrateur', 'manager' => 'Responsable de centre'][$u['role']] ?? 'Salarié', 'center' => $center['name'] ?? ''],
        'context' => support_context($u, $center, (string)input('page', '')),
        'transcript' => array_slice($transcript, -12),
        'message' => $message,
    ]);
    if ($r === null || empty($r['id'])) {
        return null;
    }
    insert('support_chats', ['user_id' => $u['id'], 'center_id' => $center['id'] ?? null, 'hub_id' => (int)$r['id'], 'token' => (string)$r['token'],
        'created_at' => now(), 'updated_at' => now()]);
    audit('Conversation avec l\'assistance', 'support', (int)$r['id']);
    return support_open_chat((int)$u['id']);
}

/** Conversation inconnue du centre d'assistance (clé NLapps changée, conversation supprimée) : on la clôt pour en ouvrir une nouvelle. */
function support_chat_orphan(?array &$chat): bool
{
    if ($chat && support_hub_last_code() === 404) {
        update('support_chats', ['status' => 'closed', 'rated' => 1, 'updated_at' => now()], 'id = ?', [$chat['id']]);
        $chat = null;
        return true;
    }
    return false;
}

/**
 * Conversation en direct avec l'équipe NLapps, relayée par le serveur de l'application
 * (la clé du centre d'assistance n'est jamais exposée au navigateur).
 * Actions : resume, open, send, poll, close.
 */
function api_support_live(): void
{
    $u = require_login();
    if (!support_live_enabled()) {
        json_response(['error' => 'La conversation en direct n\'est pas activée sur cette installation.', 'enabled' => false], 503);
    }
    $chat = support_open_chat((int)$u['id']);
    $action = (string)input('action', 'poll');
    $center = current_center();

    // Image d'une conversation (capture envoyée par l'utilisateur ou le conseiller), relayée sans exposer la clé
    if ($action === 'file') {
        $c = $chat ?: one('SELECT * FROM support_chats WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$u['id']]);
        $f = preg_replace('/[^a-z0-9.]/', '', (string)input('f', ''));
        $code = $c && $f !== '' ? support_hub_download('file', ['id' => $c['hub_id'], 'token' => $c['token'], 'f' => $f], null, $type, $body) : 404;
        if ($code !== 200 || !preg_match('#^image/(png|jpeg|webp)#', (string)$type)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $type);
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        echo $body;
        exit;
    }
    // Note de satisfaction après la clôture
    if ($action === 'rate') {
        $c = one('SELECT * FROM support_chats WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$u['id']]);
        $ok = $c && support_hub('rate', [], ['id' => $c['hub_id'], 'token' => $c['token'], 'rating' => max(1, min(5, input_int('rating'))),
            'comment' => mb_substr(trim((string)input('comment', '')), 0, 500)]) !== null;
        if ($c) {
            update('support_chats', ['rated' => 1], 'id = ?', [$c['id']]); // proposé une seule fois, même si l'envoi échoue
        }
        json_response(['ok' => $ok]);
    }
    // Capture d'écran jointe (ouvre la conversation si besoin)
    if ($action === 'attach') {
        $f = $_FILES['image'] ?? null;
        $data = $f && $f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name']) ? (string)file_get_contents($f['tmp_name']) : '';
        $mime = $data !== '' ? (getimagesizefromstring($data)['mime'] ?? '') : '';
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) || strlen($data) > 4 * 1024 * 1024) {
            json_response(['error' => 'Envoyez une image JPEG, PNG ou WebP de 4 Mo maximum.'], 422);
        }
        $text = mb_substr(trim((string)input('text', '')), 0, 1000);
        $sent = $chat ? support_hub('attach', [], ['id' => $chat['hub_id'], 'token' => $chat['token'], 'data' => base64_encode($data), 'text' => $text]) : null;
        if ($sent === null && (!$chat || support_chat_orphan($chat))) {
            $chat = support_chat_start($u, $center, $text ?: 'Capture d\'écran jointe');
            $sent = $chat ? support_hub('attach', [], ['id' => $chat['hub_id'], 'token' => $chat['token'], 'data' => base64_encode($data), 'text' => '']) : null;
        }
        if ($sent === null) {
            json_response(['error' => 'Envoi de l\'image impossible pour le moment. Réessayez ou utilisez le formulaire.'], 502);
        }
        $action = 'poll';
    }

    if ($action === 'open') {
        $text = trim((string)input('text', ''));
        if ($text === '') {
            json_response(['error' => 'Écrivez votre message.'], 422);
        }
        if ($chat) { // une conversation est déjà ouverte : on y ajoute le message
            $r = support_hub('send', [], ['id' => $chat['hub_id'], 'token' => $chat['token'], 'text' => $text]);
            if ($r === null && !support_chat_orphan($chat)) {
                json_response(['error' => 'Le centre d\'assistance ne répond pas. Réessayez ou utilisez le formulaire.'], 502);
            }
        }
        if (!$chat) {
            $transcript = json_decode((string)input('transcript', '[]'), true);
            $chat = support_chat_start($u, $center, $text, is_array($transcript) ? $transcript : []);
            if (!$chat) {
                json_response(['error' => 'Le centre d\'assistance ne répond pas. Réessayez ou utilisez le formulaire.'], 502);
            }
        }
    }
    if (!$chat) {
        $st = support_hub('status');
        // Conversation clôturée par le conseiller et pas encore notée : la bulle propose de la noter
        $lastChat = one("SELECT * FROM support_chats WHERE user_id = ? AND status = 'closed' AND updated_at >= ? ORDER BY id DESC LIMIT 1", [$u['id'], date('Y-m-d H:i:s', strtotime('-2 days'))]);
        json_response(['chat' => false, 'availability' => $st, 'messages' => [], 'rate' => $lastChat && !$lastChat['rated'] ? (int)$lastChat['id'] : null]);
    }
    if ($action === 'close') {
        support_hub('close', [], ['id' => $chat['hub_id'], 'token' => $chat['token']]);
        update('support_chats', ['status' => 'closed', 'updated_at' => now()], 'id = ?', [$chat['id']]);
        json_response(['chat' => false, 'closed' => true, 'rate' => (int)$chat['id']]);
    }
    $after = $action === 'resume' ? 0 : max(0, input_int('after'));
    $r = support_hub('poll', ['id' => $chat['hub_id'], 'token' => $chat['token'], 'after' => $after]);
    if ($r === null && support_chat_orphan($chat)) {
        json_response(['chat' => false, 'availability' => support_hub('status'), 'messages' => []]);
    }
    if ($r === null) {
        json_response(['error' => 'Connexion au centre d\'assistance perdue, nouvel essai dans un instant.', 'chat' => true], 502);
    }
    // Messages visibles par l'utilisateur (l'échange avec le chatbot reste côté NLapps)
    $msgs = array_values(array_filter($r['messages'] ?? [], fn($m) => in_array($m['from'], ['user', 'agent', 'system'], true)));
    $maxId = max([0, ...array_map(fn($m) => (int)$m['id'], $r['messages'] ?? [])]);
    $upd = ['updated_at' => now(), 'status' => ($r['status'] ?? 'open') === 'closed' ? 'closed' : 'open', 'rated' => !empty($r['rated']) ? 1 : 0];
    if ($maxId > (int)$chat['seen_id']) {
        $upd['seen_id'] = $maxId;
        $upd['notified_id'] = max($maxId, (int)$chat['notified_id']); // déjà vu dans la bulle : pas de notification en double
    }
    update('support_chats', $upd, 'id = ?', [$chat['id']]);
    json_response(['chat' => true, 'status' => $upd['status'], 'rated' => !empty($r['rated']), 'availability' => $r['availability'] ?? null, 'messages' => $msgs, 'last' => $maxId,
        'operator' => support_contact()['editor']]);
}

/** Page « Assistance » : conversation, formulaire, historique. */
function support_page(): void
{
    $u = require_login();
    $center = current_center();
    $c = support_contact();
    if (is_post()) {
        $category = isset(SUPPORT_CATEGORIES[(string)input('category')]) ? (string)input('category') : 'question';
        $subject = mb_substr(trim((string)input('subject', '')), 0, 200);
        $message = trim((string)input('message', ''));
        if ($subject === '' || mb_strlen($message) < 5) {
            flash('error', 'Indiquez un objet et décrivez votre demande.');
            redirect('support', ['q' => (string)input('bot_question', '')]);
        }
        $page = mb_substr((string)input('page', ''), 0, 255);
        $context = support_context($u, $center, $page);
        $id = insert('support_requests', [
            'user_id' => $u['id'], 'center_id' => $center['id'] ?? null, 'category' => $category, 'subject' => $subject,
            'message' => $message, 'page' => $page ?: null, 'bot_question' => mb_substr((string)input('bot_question', ''), 0, 2000) ?: null,
            'created_at' => now(),
        ]);
        $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1e2335;line-height:1.5">'
            . '<p><strong>' . e(SUPPORT_CATEGORIES[$category]) . ' — ' . e($subject) . '</strong></p>'
            . '<p>' . nl2br(e($message)) . '</p>'
            . (input('bot_question') ? '<p style="color:#666">Question posée à l\'assistant : ' . e((string)input('bot_question')) . '</p>' : '')
            . '<hr><pre style="font-size:12px;color:#555">' . e($context) . '</pre></div>';
        $sent = setting('mail_enabled', '0') === '1' && send_mail($c['email'], '[Approvia #' . $id . '] ' . $subject, $html);
        update('support_requests', ['sent_by' => $sent ? 'email' : null], 'id = ?', [$id]);
        audit('Demande d\'assistance', 'support', $id, $subject);
        if ($sent) {
            flash('success', 'Votre demande n°' . $id . ' a été transmise à ' . $c['editor'] . '. Vous serez recontacté(e) à ' . $u['email'] . '.');
            redirect('support');
        }
        // Envoi automatique désactivé : on propose d'envoyer le même message depuis la messagerie de l'ordinateur
        $_SESSION['support_pending'] = ['id' => $id, 'subject' => $subject, 'text' => $message . "\n\n" . $context];
        redirect('support', ['pending' => $id]);
    }
    $pending = null;
    if (input_int('pending') && ($_SESSION['support_pending']['id'] ?? 0) === input_int('pending')) {
        $p = $_SESSION['support_pending'];
        $pending = ['id' => $p['id'], 'mailto' => 'mailto:' . $c['email'] . '?subject=' . rawurlencode('[Approvia #' . $p['id'] . '] ' . $p['subject']) . '&body=' . rawurlencode($p['text'])];
    }
    $isAdmin = ($u['role'] ?? '') === 'admin';
    $history = all('SELECT r.*, u.first_name, u.last_name FROM support_requests r LEFT JOIN users u ON u.id = r.user_id'
        . ($isAdmin ? '' : ' WHERE r.user_id = ' . (int)$u['id']) . ' ORDER BY r.created_at DESC, r.id DESC LIMIT 20');
    render('support', [
        'title' => 'Assistance', 'contact' => $c, 'history' => $history, 'pending' => $pending, 'q' => (string)input('q', ''),
        'page' => (string)input('page', ''), 'isAdmin' => $isAdmin, 'live' => support_live_enabled(),
        'openChat' => support_open_chat((int)$u['id']) !== null || input('chat') === '1',
    ]);
}
