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
    $r = support_answer($q, ($u['role'] ?? '') === 'admin', (string)input('page', ''));
    $center = current_center();
    $r['whatsapp'] = support_whatsapp_url("Bonjour NLapps, j'ai besoin d'aide sur ScanAppro.\n\nMa question : " . mb_substr($q, 0, 500) . "\n\n" . support_context($u, $center, (string)input('page', '')));
    $r['form'] = url('support', ['q' => mb_substr($q, 0, 300), 'page' => mb_substr((string)input('page', ''), 0, 120)]);
    json_response($r);
}

/** Page « Contacter l'assistance » : formulaire, WhatsApp, historique. */
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
        $sent = setting('mail_enabled', '0') === '1'
            && send_mail($c['email'], '[ScanAppro #' . $id . '] ' . $subject, $html);
        update('support_requests', ['sent_by' => $sent ? 'email' : null], 'id = ?', [$id]);
        audit('Demande d\'assistance', 'support', $id, $subject);
        if ($sent) {
            flash('success', 'Votre demande n°' . $id . ' a été transmise à ' . $c['editor'] . '. Vous serez recontacté(e) à ' . $u['email'] . '.');
            redirect('support');
        }
        // Envoi par l'application désactivé : on propose d'envoyer le même message par e-mail ou WhatsApp
        $_SESSION['support_pending'] = ['id' => $id, 'subject' => $subject, 'text' => $message . "\n\n" . $context];
        redirect('support', ['pending' => $id]);
    }
    $pending = null;
    if (input_int('pending') && ($_SESSION['support_pending']['id'] ?? 0) === input_int('pending')) {
        $p = $_SESSION['support_pending'];
        $pending = [
            'id' => $p['id'],
            'mailto' => 'mailto:' . $c['email'] . '?subject=' . rawurlencode('[ScanAppro #' . $p['id'] . '] ' . $p['subject']) . '&body=' . rawurlencode($p['text']),
            'whatsapp' => support_whatsapp_url('Bonjour NLapps, demande #' . $p['id'] . ' : ' . $p['subject'] . "\n\n" . $p['text']),
        ];
    }
    $isAdmin = ($u['role'] ?? '') === 'admin';
    $history = all('SELECT r.*, u.first_name, u.last_name FROM support_requests r LEFT JOIN users u ON u.id = r.user_id'
        . ($isAdmin ? '' : ' WHERE r.user_id = ' . (int)$u['id']) . ' ORDER BY r.created_at DESC, r.id DESC LIMIT 20');
    $q = (string)input('q', '');
    render('support', [
        'title' => 'Assistance', 'contact' => $c, 'history' => $history, 'pending' => $pending, 'q' => $q,
        'page' => (string)input('page', ''), 'isAdmin' => $isAdmin,
        'whatsapp' => support_whatsapp_url("Bonjour NLapps, j'ai besoin d'aide sur ScanAppro.\n\n" . ($q ? "Ma question : $q\n\n" : '') . support_context($u, $center)),
    ]);
}
