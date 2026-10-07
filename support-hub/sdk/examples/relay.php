<?php
declare(strict_types=1);

/**
 * Exemple de relais entre le widget (nlapps-chat.js) et le centre d'assistance, à adapter à votre application :
 * authentification de l'utilisateur, stockage de la conversation (ici en session), contexte transmis au conseiller.
 *
 * Le navigateur appelle ce fichier ; seul votre serveur connaît la clé NLapps.
 */
require __DIR__ . '/../NlappsSupport.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. Votre application : utilisateur connecté (à remplacer par votre propre contrôle)
$user = $_SESSION['user'] ?? null;
if (!$user) {
    http_response_code(401);
    exit(json_encode(['error' => 'Connexion requise.']));
}

// 2. Votre configuration
$nl = new NlappsSupport('https://nlapps.fr/assistance/api.php', getenv('NLAPPS_KEY') ?: 'nlh_…', 'mon-appli');

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$chat = $_SESSION['nlapps_chat'] ?? null; // ['id' => …, 'token' => …]
$action = (string)($in['action'] ?? $_GET['action'] ?? 'poll');

switch ($action) {
    case 'send':
        $text = trim((string)($in['text'] ?? ''));
        if ($text === '') {
            exit(json_encode(['error' => 'Message vide.']));
        }
        if (!$chat) {
            $r = $nl->open(['name' => $user['name'], 'email' => $user['email'], 'role' => $user['role'] ?? '', 'center' => ''], $text,
                'Application : mon-appli · page : ' . mb_substr((string)($in['page'] ?? ''), 0, 200));
            if (!$r) {
                http_response_code(502);
                exit(json_encode(['error' => 'Assistance injoignable.']));
            }
            $_SESSION['nlapps_chat'] = $chat = ['id' => $r['id'], 'token' => $r['token']];
        } else {
            $nl->send($chat['id'], $chat['token'], $text);
        }
        // puis renvoie les messages, comme pour « poll »
    case 'poll':
        if (!$chat) {
            exit(json_encode(['chat' => false, 'availability' => $nl->status()]));
        }
        $r = $nl->poll($chat['id'], $chat['token'], (int)($_GET['after'] ?? $in['after'] ?? 0));
        if (($r['status'] ?? '') === 'closed') {
            unset($_SESSION['nlapps_chat']);
        }
        // Les messages internes au chatbot ne sont pas renvoyés à l'utilisateur
        $r['messages'] = array_values(array_filter($r['messages'] ?? [], fn($m) => in_array($m['from'], ['user', 'agent', 'system'], true)));
        exit(json_encode(['chat' => true] + ($r ?? [])));
    case 'close':
        if ($chat) {
            $nl->close($chat['id'], $chat['token']);
            unset($_SESSION['nlapps_chat']);
        }
        exit(json_encode(['ok' => true]));
}
