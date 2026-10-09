<?php
declare(strict_types=1);

/**
 * Tutoriels vidéo : page « Tutoriels vidéo » (menu Aide), suggestion par le chatbot d'aide.
 *
 * Deux origines :
 *  - « hub »   : vidéos publiées par NLapps sur le centre d'assistance, transmises avec la licence (comme la FAQ partagée)
 *                puis téléchargées en arrière-plan (empreinte SHA-256 vérifiée) ;
 *  - « local » : vidéos déposées par un administrateur de l'installation.
 *
 * Chaque vidéo peut avoir des chapitres (« 4:12 Réceptionner une livraison | réception colis ») : le chatbot propose
 * la vidéo et le chapitre qui correspondent le mieux à la question, et la lecture démarre au bon moment.
 * Les fichiers sont rangés dans storage/videos (non accessible directement) et diffusés par video_stream()
 * avec prise en charge des requêtes partielles (avance rapide dans le lecteur).
 */

const VIDEO_MAX_MB = 500;

function videos_dir(): string
{
    $d = ROOT . '/storage/videos';
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

/** « 4:12 Titre | mots-clés » par ligne → [['t' => 252, 'title' => 'Titre', 'k' => 'mots-clés'], …] (triés). */
function video_chapters_parse(string $text): array
{
    $out = [];
    foreach (preg_split('/\R/', $text) as $line) {
        if (!preg_match('/^\s*(?:(\d+):)?(\d{1,3}):(\d{2})\s+(.+?)\s*(?:\|\s*(.*))?$/u', $line, $m)) {
            continue;
        }
        $t = (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
        $out[] = ['t' => $t, 'title' => mb_substr(trim($m[4]), 0, 120), 'k' => mb_substr(trim($m[5] ?? ''), 0, 300)];
    }
    usort($out, fn($a, $b) => $a['t'] <=> $b['t']);
    return $out;
}

function video_chapters_text(array $chapters): string
{
    return implode("\n", array_map(fn($c) => video_time((int)$c['t']) . ' ' . $c['title'] . ($c['k'] !== '' ? ' | ' . $c['k'] : ''), $chapters));
}

/** 252 → « 4:12 » ; 3725 → « 1:02:05 ». */
function video_time(int $s): string
{
    return $s >= 3600 ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60) : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
}

function video_decode(array $v): array
{
    $v['chapters'] = json_decode((string)($v['chapters'] ?? ''), true) ?: [];
    $v['ready'] = !empty($v['file']) && is_file(videos_dir() . '/' . basename((string)$v['file']));
    return $v;
}

/** Vidéos visibles par l'utilisateur (prêtes à être lues). */
function video_list(bool $isAdmin, bool $readyOnly = true): array
{
    $rows = array_map('video_decode', all('SELECT * FROM videos WHERE active = 1 ORDER BY position, id'));
    return array_values(array_filter($rows, fn($v) => ($isAdmin || $v['audience'] !== 'admin') && (!$readyOnly || $v['ready'])));
}

function video_find(string $uid): ?array
{
    $v = one('SELECT * FROM videos WHERE uid = ?', [$uid]);
    return $v ? video_decode($v) : null;
}

function video_url(array $v, ?int $t = null): string
{
    return url('videos', ['v' => $v['uid']] + ($t ? ['t' => $t] : []));
}

/**
 * Mots significatifs d'une question ou d'un chapitre. Les verbes d'action (« commander », « réceptionner »…) sont gardés :
 * contrairement à la recherche d'articles, ce sont eux qui désignent le bon passage.
 */
function video_tokens(string $s): array
{
    static $stop = ['le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou', 'au', 'aux', 'en', 'je', 'on', 'il', 'elle', 'est', 'pas', 'ne', 'que', 'qui',
        'quoi', 'comment', 'pour', 'par', 'sur', 'dans', 'avec', 'mon', 'ma', 'mes', 'ce', 'cet', 'cette', 'se', 'vous', 'nous', 'tu', 'te', 'me', 'puis', 'bonjour',
        'merci', 'svp', 'stp', 'y', 'a', 'peut', 'peux', 'faut', 'quel', 'quelle', 'quels', 'quelles', 'ai', 'avez', 'un', 'son', 'sa', 'ses', 'leur', 'leurs', 'ca', 'cela'];
    return array_values(array_unique(array_filter(search_tokens($s, false), fn($w) => mb_strlen($w) > 1 && !in_array($w, $stop, true))));
}

/**
 * Vidéo (et chapitre) qui répond le mieux à une question du chatbot.
 * Renvoie ['video', 'chapter' (ou null), 'score', 'url', 'label'] ou null.
 */
function video_match(string $question, bool $isAdmin): ?array
{
    $norm = search_normalize($question);
    $wantsVideo = (bool)preg_match('/\b(video|videos|tuto|tutos|tutoriel|tutoriels|film|demonstration|demo|montre|montrez|voir comment)\b/', $norm);
    // Mots qui disent « je veux voir / apprendre » sans désigner le sujet : ils ne comptent pas dans la comparaison
    $neutral = ['video', 'videos', 'tuto', 'tutos', 'tutoriel', 'tutoriels', 'film', 'demonstration', 'demo', 'montre', 'montrez', 'montrer', 'moi',
        'voir', 'faire', 'fait', 'explique', 'expliquer', 'expliquez', 'apprendre', 'savoir', 'sais', 'besoin', 'aide', 'aider', 'veux', 'voudrais'];
    $q = array_values(array_diff(video_tokens($question), $neutral));
    if (!$q) {
        return null;
    }
    $score = function (array $words) use ($q): float {
        $words = array_unique($words);
        $s = 0.0;
        foreach ($q as $t) {
            $best = 0.0;
            foreach ($words as $w) {
                $best = max($best, search_word_match($t, $w));
            }
            $s += $best;
        }
        return $s / max(2, count($q));
    };
    $best = null;
    foreach (video_list($isAdmin) as $v) {
        $base = array_merge(video_tokens($v['title']), video_tokens((string)$v['keywords']));
        // La vidéo entière ne l'emporte sur un chapitre que si elle correspond nettement mieux : un chapitre précis aide davantage
        $cands = [['chapter' => null, 's' => $score(array_merge($base, video_tokens((string)$v['description']))) * ($v['chapters'] ? 0.8 : 1)]];
        foreach ($v['chapters'] as $c) {
            // Un chapitre est jugé sur son titre et ses mots-clés
            $cands[] = ['chapter' => $c, 's' => $score(array_merge(video_tokens($c['title']), video_tokens($c['k'])))];
        }
        foreach ($cands as $c) {
            $s = $c['s'] + ($wantsVideo ? 0.15 : 0);
            if (!$best || $s > $best['score']) {
                $best = ['video' => $v, 'chapter' => $c['chapter'], 'score' => round($s, 2)];
            }
        }
    }
    if (!$best || $best['score'] < 0.4) {
        return null;
    }
    $c = $best['chapter'];
    $best['url'] = video_url($best['video'], $c ? (int)$c['t'] : null);
    $best['label'] = $c ? $c['title'] . ' (' . $best['video']['title'] . ', à ' . video_time((int)$c['t']) . ')' : $best['video']['title'];
    return $best;
}

/** Diffuse le fichier avec prise en charge de « Range » (avance rapide, lecture sur iPhone/iPad). */
function video_stream(string $path): never
{
    $size = filesize($path);
    $start = 0;
    $end = $size - 1;
    header('Content-Type: video/mp4');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    if (preg_match('/bytes=(\d*)-(\d*)/', (string)($_SERVER['HTTP_RANGE'] ?? ''), $m)) {
        if ($m[1] === '' && $m[2] !== '') {
            $start = max(0, $size - (int)$m[2]);
        } else {
            $start = (int)$m[1];
            $end = $m[2] !== '' ? min((int)$m[2], $size - 1) : $end;
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Length: ' . ($end - $start + 1));
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $fh = fopen($path, 'rb');
    fseek($fh, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fh) && !connection_aborted()) {
        $chunk = fread($fh, (int)min(1 << 20, $left));
        echo $chunk;
        flush();
        $left -= strlen($chunk);
    }
    fclose($fh);
    exit;
}

/** Enregistre la liste des vidéos publiées par NLapps (reçue avec la vérification de licence). */
function videos_sync_remote(array $items): void
{
    $seen = [];
    foreach ($items as $it) {
        $uid = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)($it['uid'] ?? '')));
        if ($uid === '' || empty($it['title'])) {
            continue;
        }
        $seen[] = $uid;
        $chapters = array_values(array_filter(array_map(fn($c) => is_array($c) ? ['t' => max(0, (int)($c['t'] ?? 0)), 'title' => mb_substr((string)($c['title'] ?? ''), 0, 120), 'k' => mb_substr((string)($c['k'] ?? ''), 0, 300)] : null,
            (array)($it['chapters'] ?? [])), fn($c) => $c && $c['title'] !== ''));
        $data = [
            'title' => mb_substr((string)$it['title'], 0, 150), 'description' => mb_substr((string)($it['desc'] ?? ''), 0, 2000) ?: null,
            'keywords' => mb_substr((string)($it['k'] ?? ''), 0, 400) ?: null, 'chapters' => json_encode($chapters, JSON_UNESCAPED_UNICODE),
            'audience' => ($it['audience'] ?? '') === 'admin' ? 'admin' : 'all', 'size' => (int)($it['size'] ?? 0) ?: null,
            'sha256' => preg_replace('/[^a-f0-9]/', '', strtolower((string)($it['sha256'] ?? ''))) ?: null, 'duration' => (int)($it['duration'] ?? 0) ?: null,
            'position' => (int)($it['position'] ?? 0), 'welcome' => !empty($it['welcome']) ? 1 : 0, 'updated_at' => now(),
        ];
        $cur = one("SELECT * FROM videos WHERE uid = ? AND source = 'hub'", [$uid]);
        if ($cur) {
            if ($cur['sha256'] !== $data['sha256'] && $cur['file']) {
                @unlink(videos_dir() . '/' . basename((string)$cur['file']));  // nouvelle version du fichier : on la retélécharge
                $data['file'] = null;
            }
            update('videos', $data, 'id = ?', [$cur['id']]);
        } else {
            insert('videos', $data + ['uid' => $uid, 'source' => 'hub', 'active' => 1, 'created_at' => now()]);
        }
    }
    // Vidéos retirées par NLapps
    foreach (all("SELECT * FROM videos WHERE source = 'hub'") as $v) {
        if (!in_array($v['uid'], $seen, true)) {
            if ($v['file']) {
                @unlink(videos_dir() . '/' . basename((string)$v['file']));
            }
            q('DELETE FROM videos WHERE id = ?', [$v['id']]);
        }
    }
}

/** Télécharge les vidéos NLapps pas encore présentes (une par passage, la tâche planifiée reprend ensuite). */
function videos_download_pending(int $max = 1): string
{
    if (!licence_managed()) {
        return 'sans clé NLapps';
    }
    $todo = all("SELECT * FROM videos WHERE source = 'hub' AND active = 1 AND (file IS NULL OR file = '') ORDER BY position, id");
    $done = 0;
    foreach (array_slice($todo, 0, $max) as $v) {
        @set_time_limit(1800);
        $name = $v['uid'] . '-' . bin2hex(random_bytes(4)) . '.mp4';
        $tmp = videos_dir() . '/' . $name . '.part';
        $code = support_hub_download('video', ['id' => $v['uid']], $tmp, $type, $body, 1800);
        if ($code !== 200 || !is_file($tmp) || ($v['sha256'] && !hash_equals((string)$v['sha256'], (string)hash_file('sha256', $tmp)))) {
            @unlink($tmp);
            error_log('[videos] téléchargement de ' . $v['uid'] . ' impossible (HTTP ' . $code . ')');
            continue;
        }
        rename($tmp, videos_dir() . '/' . $name);
        update('videos', ['file' => $name, 'size' => filesize(videos_dir() . '/' . $name), 'updated_at' => now()], 'id = ?', [$v['id']]);
        $done++;
    }
    $left = count($todo) - $done;
    return $done ? $done . ' vidéo(s) téléchargée(s)' . ($left ? ', ' . $left . ' en attente' : '') : ($left ? $left . ' en attente' : 'à jour');
}

/**
 * Vidéo d'accueil montrée en fenêtre à la première connexion des salariés (jusqu'à « Ne plus afficher »).
 * Réglage « welcome_video » : '' = automatique (vidéo désignée par NLapps), 'none' = aucune, sinon l'identifiant d'une vidéo.
 */
function video_welcome(): ?array
{
    $choice = (string)setting('welcome_video', '');
    if ($choice === 'none') {
        return null;
    }
    foreach (video_list(false) as $v) {
        if ($choice !== '' ? $v['uid'] === $choice : (int)$v['welcome'] === 1) {
            return $v;
        }
    }
    return null;
}

/** La fenêtre d'accueil doit-elle s'ouvrir pour cet utilisateur (une fois par connexion, tant qu'il ne l'a pas désactivée) ? */
function video_welcome_due(array $u): ?array
{
    if (is_admin($u) || !empty($u['welcome_video_off']) || !empty($_SESSION['welcome_shown'])) {
        return null;
    }
    $v = video_welcome();
    if ($v) {
        $_SESSION['welcome_shown'] = 1;
    }
    return $v;
}

/** Empreinte de la liste NLapps déjà reçue (le centre d'assistance n'envoie la liste que si elle a changé). */
function videos_remote_hash(): string
{
    return (string)setting('videos_remote_hash', '');
}
