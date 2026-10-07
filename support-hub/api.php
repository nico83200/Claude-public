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
 */
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
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
        out(['status' => $c['status'], 'availability' => hub_status(), 'messages' => hub_messages((int)$c['id'], (int)($_GET['after'] ?? 0))]);

    case 'close':
        $c = conv_or_fail($client, $in['id'] ?? 0, $in['token'] ?? '');
        hq("UPDATE conversations SET status = 'closed', updated_at = ? WHERE id = ?", [hnow(), $c['id']]);
        hub_add_message((int)$c['id'], 'system', 'Conversation terminée par l\'utilisateur.');
        out(['ok' => true]);

    default:
        out(['error' => 'Action inconnue.'], 400);
}
