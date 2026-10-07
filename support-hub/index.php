<?php
declare(strict_types=1);

/** Console de l'opérateur NLapps : conversations de toutes les applications clientes. */
require __DIR__ . '/lib.php';

session_name('nlapps_hub');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

$_SESSION['csrf'] ??= bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if ($post && !hash_equals($csrf, (string)($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? ''))) {
    http_response_code(419);
    exit('Session expirée : rechargez la page.');
}
$hash = hsetting('password_hash');
$error = null;

// --- Première connexion : choix du mot de passe ; ensuite, connexion
if (!($_SESSION['hub_ok'] ?? false)) {
    if ($post) {
        $pw = (string)($_POST['password'] ?? '');
        if (!$hash) {
            if (mb_strlen($pw) < 10 || $pw !== (string)($_POST['password2'] ?? '')) {
                $error = 'Choisissez un mot de passe d\'au moins 10 caractères, saisi deux fois à l\'identique.';
            } else {
                hset('password_hash', password_hash($pw, PASSWORD_DEFAULT));
                $_SESSION['hub_ok'] = true;
            }
        } else {
            $fails = (int)hsetting('login_fails', '0');
            $lockUntil = (int)hsetting('login_lock', '0');
            if ($lockUntil > time()) {
                $error = 'Trop d\'essais : réessayez dans quelques minutes.';
            } elseif (password_verify($pw, $hash)) {
                hset('login_fails', '0');
                session_regenerate_id(true);
                $_SESSION['hub_ok'] = true;
            } else {
                hset('login_fails', (string)($fails + 1));
                if ($fails + 1 >= 5) {
                    hset('login_lock', (string)(time() + 600));
                    hset('login_fails', '0');
                }
                $error = 'Mot de passe incorrect.';
            }
        }
        if ($_SESSION['hub_ok'] ?? false) {
            header('Location: index.php' . (isset($_GET['c']) ? '?c=' . (int)$_GET['c'] : ''));
            exit;
        }
    }
    page_head('Connexion');
    ?>
    <main class="login"><form method="post" class="card">
      <img src="assets/nlapps-mark.svg" alt="" width="52" height="52">
      <h1>Assistance NLapps</h1>
      <p class="muted"><?= $hash ? 'Connectez-vous pour répondre aux utilisateurs.' : 'Première connexion : choisissez le mot de passe de la console.' ?></p>
      <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="password" name="password" placeholder="Mot de passe" required autofocus autocomplete="<?= $hash ? 'current-password' : 'new-password' ?>">
      <?php if (!$hash): ?><input type="password" name="password2" placeholder="Confirmez le mot de passe" required autocomplete="new-password"><?php endif; ?>
      <button class="btn primary" type="submit"><?= $hash ? 'Se connecter' : 'Créer l\'accès' ?></button>
    </form></main>
    <?php
    page_foot();
    exit;
}

// --- Actions
$action = (string)($_POST['action'] ?? '');
$flash = null;
if ($post) {
    switch ($action) {
        case 'reply':
            $cid = (int)$_POST['c'];
            $text = trim((string)$_POST['text']);
            if ($text !== '' && hone('SELECT id FROM conversations WHERE id = ?', [$cid])) {
                $id = hub_add_message($cid, 'agent', $text);
                hq("UPDATE conversations SET unread = 0, status = 'open' WHERE id = ?", [$cid]);
                if (!empty($_SERVER['HTTP_X_CSRF'])) {
                    echo json_encode(['ok' => true, 'id' => $id]);
                    exit;
                }
            }
            header('Location: index.php?c=' . $cid);
            exit;
        case 'close':
        case 'reopen':
            hq('UPDATE conversations SET status = ?, unread = 0 WHERE id = ?', [$action === 'close' ? 'closed' : 'open', (int)$_POST['c']]);
            if ($action === 'close') {
                hub_add_message((int)$_POST['c'], 'system', 'Conversation clôturée par ' . hcfg('operator_name') . '.');
            }
            header('Location: index.php?c=' . (int)$_POST['c']);
            exit;
        case 'availability':
            hset('online', ($_POST['online'] ?? '') === '1' ? '1' : '0');
            if (isset($_POST['away_message'])) {
                hset('away_message', mb_substr(trim((string)$_POST['away_message']), 0, 300));
            }
            header('Location: ' . ($_POST['back'] ?? 'index.php'));
            exit;
        case 'client_add':
            $name = trim((string)$_POST['name']);
            if ($name !== '') {
                $_SESSION['new_key'] = ['name' => $name, 'key' => hub_create_client($name, (string)($_POST['site'] ?? ''))];
            }
            header('Location: index.php?p=clients');
            exit;
        case 'client_toggle':
            hq('UPDATE clients SET active = 1 - active WHERE id = ?', [(int)$_POST['id']]);
            header('Location: index.php?p=clients');
            exit;
        case 'logout':
            session_destroy();
            header('Location: index.php');
            exit;
    }
}

// --- Flux JSON pour l'actualisation en direct
if (isset($_GET['feed'])) {
    header('Content-Type: application/json');
    $c = (int)($_GET['c'] ?? 0);
    $resp = ['unread' => (int)(hone("SELECT COALESCE(SUM(unread),0) n FROM conversations WHERE status = 'open'")['n'] ?? 0)];
    if ($c) {
        $resp['messages'] = hub_messages($c, (int)($_GET['after'] ?? 0));
        hq('UPDATE conversations SET unread = 0 WHERE id = ?', [$c]);
    }
    $resp['list'] = conv_list();
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    exit;
}

function conv_list(): array
{
    return hall("SELECT c.id, c.user_name, c.center, c.status, c.unread, c.updated_at, cl.name AS client,
                   (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last
                 FROM conversations c JOIN clients cl ON cl.id = c.client_id
                 ORDER BY (c.status = 'open') DESC, c.unread > 0 DESC, c.updated_at DESC LIMIT 60");
}

$st = hub_status();
$p = (string)($_GET['p'] ?? '');
$cid = (int)($_GET['c'] ?? 0);
$conv = $cid ? hone('SELECT c.*, cl.name AS client, cl.site FROM conversations c JOIN clients cl ON cl.id = c.client_id WHERE c.id = ?', [$cid]) : null;
if ($conv) {
    hq('UPDATE conversations SET unread = 0 WHERE id = ?', [$cid]);
}
page_head($conv ? 'Conversation #' . $cid : 'Conversations');
?>
<header class="top">
  <a class="brand" href="index.php"><img src="assets/nlapps-mark.svg" alt="" width="30" height="30"> Assistance <b>NLapps</b></a>
  <form method="post" class="avail">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="availability"><input type="hidden" name="back" value="<?= h($_SERVER['REQUEST_URI'] ?? 'index.php') ?>">
    <button name="online" value="<?= $st['online'] ? '0' : '1' ?>" class="pill <?= $st['online'] ? 'on' : 'off' ?>" title="Changer de disponibilité"><?= $st['online'] ? '● Disponible' : '○ Absent' ?></button>
  </form>
  <nav><a href="index.php" class="<?= $p === '' ? 'act' : '' ?>">Conversations</a><a href="index.php?p=clients" class="<?= $p === 'clients' ? 'act' : '' ?>">Applications clientes</a><a href="index.php?p=settings" class="<?= $p === 'settings' ? 'act' : '' ?>">Réglages</a></nav>
</header>

<?php if ($p === 'clients'): ?>
  <main class="wrap">
    <h1>Applications clientes</h1>
    <p class="muted">Chaque installation (par exemple Approvia chez un client) reçoit sa propre clé, à reporter dans son <code>config.php</code>.</p>
    <?php if ($nk = $_SESSION['new_key'] ?? null): unset($_SESSION['new_key']); ?>
      <div class="card keybox"><b>Clé de « <?= h($nk['name']) ?> »</b> (affichée une seule fois) :
        <pre>'support_hub_url' => '<?= h(preg_replace('/index\.php$/', 'api.php', hub_base_url())) ?>',
'support_hub_key' => '<?= h($nk['key']) ?>',</pre>
        <small class="muted">À ajouter dans le fichier config.php de l'installation du client.</small></div>
    <?php endif; ?>
    <form method="post" class="card row">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="client_add">
      <input name="name" placeholder="Nom du client (ex : Groupe IMSS — Approvia)" required>
      <input name="site" placeholder="Adresse du site (facultatif)">
      <button class="btn primary">Créer une clé</button>
    </form>
    <div class="card"><table>
      <tr><th>Client</th><th>Clé</th><th>Dernier contact</th><th></th></tr>
      <?php foreach (hall('SELECT * FROM clients ORDER BY active DESC, name') as $c): ?>
        <tr class="<?= $c['active'] ? '' : 'dim' ?>"><td><b><?= h($c['name']) ?></b><br><small class="muted"><?= h($c['site']) ?></small></td><td><code><?= h($c['key_hint']) ?></code></td>
          <td><small><?= h($c['last_seen'] ?? 'jamais') ?></small></td>
          <td><form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="client_toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn sm"><?= $c['active'] ? 'Désactiver' : 'Réactiver' ?></button></form></td></tr>
      <?php endforeach; ?>
    </table></div>
  </main>

<?php elseif ($p === 'settings'): ?>
  <main class="wrap">
    <h1>Réglages</h1>
    <form method="post" class="card">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="availability"><input type="hidden" name="back" value="index.php?p=settings">
      <label class="check"><input type="checkbox" name="online" value="1" <?= $st['online'] ? 'checked' : '' ?>> Je suis disponible pour répondre en direct</label>
      <label>Message affiché quand vous êtes absent</label>
      <textarea name="away_message" rows="3"><?= h($st['away_message']) ?></textarea>
      <button class="btn primary">Enregistrer</button>
    </form>
    <div class="card">
      <h2>Alertes</h2>
      <p>E-mail à chaque nouvelle conversation ou nouveau message : <b><?= h((string)hcfg('notify_email') ?: 'désactivé') ?></b></p>
      <p>Notification sur le téléphone : <b><?= hcfg('ntfy_url') ? h((string)hcfg('ntfy_url')) : 'non configurée' ?></b></p>
      <p class="muted">Pour être alerté instantanément sur votre téléphone : installez l'application gratuite <b>ntfy</b>, abonnez-vous à un sujet secret (ex. <code>nlapps-<?= h(substr(hash('sha256', (string)hsetting('password_hash')), 0, 10)) ?></code>) et indiquez <code>'ntfy_url' => 'https://ntfy.sh/ce-sujet'</code> dans <code>config.php</code>. Gardez aussi cette console ouverte : elle sonne et affiche une notification à chaque message.</p>
    </div>
    <form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="logout"><button class="btn">Se déconnecter</button></form>
  </main>

<?php else: ?>
  <main class="inbox <?= $conv ? 'has-conv' : '' ?>">
    <aside class="list" data-list>
      <?php foreach (conv_list() as $c): ?>
        <a href="index.php?c=<?= (int)$c['id'] ?>" class="item <?= (int)$c['id'] === $cid ? 'sel' : '' ?> <?= $c['status'] === 'closed' ? 'dim' : '' ?>">
          <span class="who"><?= h($c['user_name'] ?: 'Utilisateur') ?><?php if ($c['unread']): ?><i class="badge"><?= (int)$c['unread'] ?></i><?php endif; ?></span>
          <small><?= h($c['client']) ?><?= $c['center'] ? ' · ' . h($c['center']) : '' ?></small>
          <span class="last"><?= h(mb_substr((string)$c['last'], 0, 70)) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!conv_list()): ?><p class="muted pad">Aucune conversation pour l'instant. Elles apparaîtront ici dès qu'un utilisateur demandera à parler à un conseiller.</p><?php endif; ?>
    </aside>
    <section class="conv">
      <?php if ($conv): ?>
        <div class="conv-head">
          <a href="index.php" class="back">←</a>
          <div><b><?= h($conv['user_name'] ?: 'Utilisateur') ?></b> <small class="muted"><?= h($conv['user_role']) ?></small><br>
            <small><?= h($conv['client']) ?><?= $conv['center'] ? ' · ' . h($conv['center']) : '' ?><?= $conv['user_email'] ? ' · ' . h($conv['user_email']) : '' ?></small></div>
          <form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="c" value="<?= $cid ?>"><input type="hidden" name="action" value="<?= $conv['status'] === 'open' ? 'close' : 'reopen' ?>"><button class="btn sm"><?= $conv['status'] === 'open' ? 'Clôturer' : 'Rouvrir' ?></button></form>
        </div>
        <?php if ($conv['context']): ?><details class="ctx"><summary>Contexte technique</summary><pre><?= h($conv['context']) ?></pre></details><?php endif; ?>
        <div class="msgs" data-msgs data-c="<?= $cid ?>">
          <?php $last = 0; foreach (hub_messages($cid) as $m): $last = $m['id']; ?>
            <div class="m <?= h($m['from']) ?>"><div><?= nl2br(h($m['text'])) ?></div><small><?= ['bot' => 'Chatbot', 'user_bot' => 'Question au chatbot', 'user' => 'Utilisateur', 'agent' => 'Vous', 'system' => ''][$m['from']] ?? '' ?> · <?= h(substr($m['at'], 11, 5)) ?></small></div>
          <?php endforeach; ?>
        </div>
        <form class="reply" method="post" data-reply data-last="<?= $last ?>">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="c" value="<?= $cid ?>"><input type="hidden" name="action" value="reply">
          <textarea name="text" rows="2" placeholder="Votre réponse…" title="Entrée pour envoyer, Maj+Entrée pour un retour à la ligne" required></textarea>
          <button class="btn primary">Envoyer</button>
        </form>
      <?php else: ?>
        <div class="empty"><img src="assets/nlapps-mark.svg" alt="" width="56" height="56"><p>Sélectionnez une conversation.</p>
          <p class="muted"><?= $st['online'] ? 'Vous êtes affiché comme disponible : les utilisateurs peuvent vous écrire en direct.' : 'Vous êtes affiché comme absent : les messages sont gardés et les utilisateurs prévenus de votre réponse.' ?></p></div>
      <?php endif; ?>
    </section>
  </main>
<?php endif; ?>
<script>
const CSRF = <?= json_encode($csrf) ?>;
(function () {
  const msgs = document.querySelector('[data-msgs]');
  const form = document.querySelector('[data-reply]');
  const list = document.querySelector('[data-list]');
  let last = form ? +form.dataset.last : 0, unreadBefore = null;
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const label = { bot: 'Chatbot', user_bot: 'Question au chatbot', user: 'Utilisateur', agent: 'Vous', system: '' };
  const add = (m) => { const d = document.createElement('div'); d.className = 'm ' + m.from; d.innerHTML = '<div>' + esc(m.text).replace(/\n/g, '<br>') + '</div><small>' + label[m.from] + ' · ' + m.at.slice(11, 16) + '</small>'; msgs.appendChild(d); msgs.scrollTop = msgs.scrollHeight; };
  if (msgs) msgs.scrollTop = msgs.scrollHeight;
  const beep = () => { try { const a = new AudioContext(), o = a.createOscillator(), g = a.createGain(); o.connect(g); g.connect(a.destination); o.frequency.value = 880; g.gain.value = .08; o.start(); o.stop(a.currentTime + .18); } catch (e) {} };
  async function tick() {
    try {
      const r = await (await fetch('index.php?feed=1' + (msgs ? '&c=' + msgs.dataset.c + '&after=' + last : ''), { cache: 'no-store' })).json();
      if (msgs && r.messages) r.messages.forEach((m) => { if (m.id > last) { last = m.id; add(m); if (m.from === 'user') { beep(); notifyMe('Nouveau message', m.text); } } });
      document.title = (r.unread ? '(' + r.unread + ') ' : '') + 'Assistance NLapps';
      if (unreadBefore !== null && r.unread > unreadBefore && !msgs) { beep(); notifyMe('Nouveau message', 'Une conversation attend votre réponse.'); }
      unreadBefore = r.unread;
      if (list && r.list) list.innerHTML = r.list.map((c) => '<a href="index.php?c=' + c.id + '" class="item ' + (msgs && +msgs.dataset.c === c.id ? 'sel ' : '') + (c.status === 'closed' ? 'dim' : '') + '"><span class="who">' + esc(c.user_name || 'Utilisateur') + (c.unread > 0 ? '<i class="badge">' + c.unread + '</i>' : '') + '</span><small>' + esc(c.client) + (c.center ? ' · ' + esc(c.center) : '') + '</small><span class="last">' + esc((c.last || '').slice(0, 70)) + '</span></a>').join('') || list.innerHTML;
    } catch (e) {}
  }
  function notifyMe(t, b) { if (document.hidden && 'Notification' in window && Notification.permission === 'granted') new Notification(t, { body: b, icon: 'assets/nlapps-mark.svg' }); }
  if ('Notification' in window && Notification.permission === 'default') document.addEventListener('click', () => Notification.requestPermission(), { once: true });
  setInterval(tick, 4000);
  if (form) {
    const ta = form.querySelector('textarea');
    ta.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
    form.addEventListener('submit', async (e) => {
      e.preventDefault(); const text = ta.value.trim(); if (!text) return; ta.value = '';
      const body = new URLSearchParams({ csrf: CSRF, action: 'reply', c: msgs.dataset.c, text });
      await fetch('index.php', { method: 'POST', body, headers: { 'X-CSRF': CSRF } }); tick();
    });
    ta.focus();
  }
})();
</script>
<?php
page_foot();

function page_head(string $title): void
{
    ?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · Assistance NLapps</title><link rel="icon" href="assets/nlapps-mark.svg"><meta name="theme-color" content="#1e1b4b">
<style>
:root { --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --bg: #f5f7fb; --brand: linear-gradient(135deg, #0ea5e9, #4f46e5 55%, #7c3aed); --indigo: #4f46e5; }
* { box-sizing: border-box; } html, body { height: 100%; } body { display: flex; flex-direction: column; margin: 0; font-family: Inter, system-ui, sans-serif; background: var(--bg); color: var(--ink); font-size: 15px; }
a { color: var(--indigo); text-decoration: none; } .muted { color: var(--muted); } code { background: #eef2ff; padding: 1px 5px; border-radius: 5px; font-size: .88em; }
.btn { border: 1px solid var(--line); background: #fff; border-radius: 10px; padding: .55rem 1rem; font: inherit; font-weight: 600; cursor: pointer; } .btn.primary { background: var(--brand); color: #fff; border: 0; } .btn.sm { padding: .3rem .7rem; font-size: .85rem; }
input, textarea { font: inherit; padding: .6rem .8rem; border: 1px solid var(--line); border-radius: 10px; width: 100%; background: #fff; }
.card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 1.1rem 1.2rem; margin-bottom: 1rem; }
.login { min-height: 100vh; display: grid; place-items: center; padding: 1rem; background: linear-gradient(160deg, #1e1b4b, #312e81 60%, #4c1d95); }
.login .card { width: min(380px, 100%); display: grid; gap: .7rem; text-align: center; } .login h1 { margin: .2rem 0 0; font-size: 1.4rem; } .err { background: #fee2e2; color: #991b1b; padding: .5rem; border-radius: 8px; }
.top { display: flex; align-items: center; gap: 1rem; padding: .7rem 1.2rem; background: #1e1b4b; color: #fff; flex-wrap: wrap; }
.top .brand { color: #fff; display: flex; align-items: center; gap: .55rem; font-weight: 600; } .top nav { margin-left: auto; display: flex; gap: .3rem; }
.top nav a { color: rgba(255,255,255,.75); padding: .35rem .7rem; border-radius: 8px; } .top nav a.act { background: rgba(255,255,255,.14); color: #fff; }
.pill { border: 0; border-radius: 99px; padding: .3rem .8rem; font: inherit; font-size: .85rem; font-weight: 700; cursor: pointer; } .pill.on { background: #16a34a; color: #fff; } .pill.off { background: #64748b; color: #fff; }
.wrap { max-width: 900px; margin: 1.5rem auto; padding: 0 1rem; } .row { display: flex; gap: .6rem; flex-wrap: wrap; } .row input { flex: 1; min-width: 200px; }
table { width: 100%; border-collapse: collapse; } td, th { padding: .6rem .4rem; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; } tr.dim, .dim { opacity: .55; }
.keybox pre { background: #0f172a; color: #e2e8f0; padding: .8rem; border-radius: 10px; overflow-x: auto; font-size: .85rem; }
.check { display: flex; gap: .5rem; align-items: center; margin-bottom: .8rem; } .check input { width: auto; } .card textarea { margin: .4rem 0 .8rem; }
.inbox { display: grid; grid-template-columns: 320px 1fr; flex: 1; min-height: 0; } .wrap, .login { flex: 1; }
.list { border-right: 1px solid var(--line); overflow-y: auto; background: #fff; min-height: 0; } .conv { min-height: 0; } .pad { padding: 1rem; }
.item { display: grid; gap: .1rem; padding: .8rem 1rem; border-bottom: 1px solid var(--line); color: var(--ink); } .item.sel { background: #eef2ff; } .item:hover { background: #f8fafc; }
.who { font-weight: 700; display: flex; justify-content: space-between; } .badge { background: #ef4444; color: #fff; font-style: normal; border-radius: 99px; font-size: .72rem; padding: 0 .45rem; line-height: 1.5rem; }
.item small { color: var(--muted); } .last { color: var(--muted); font-size: .88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.conv { display: flex; flex-direction: column; min-width: 0; } .conv-head { display: flex; gap: .8rem; align-items: center; padding: .8rem 1.1rem; background: #fff; border-bottom: 1px solid var(--line); }
.conv-head > div { flex: 1; } .back { display: none; font-size: 1.3rem; }
.ctx { padding: .4rem 1.1rem; background: #fff; border-bottom: 1px solid var(--line); font-size: .85rem; } .ctx pre { white-space: pre-wrap; color: var(--muted); }
.msgs { flex: 1; overflow-y: auto; padding: 1rem 1.2rem; display: flex; flex-direction: column; gap: .55rem; }
.m { max-width: 72%; padding: .6rem .85rem; border-radius: 14px; background: #fff; border: 1px solid var(--line); align-self: flex-start; } .m small { display: block; color: var(--muted); font-size: .72rem; margin-top: .25rem; }
.m.agent { align-self: flex-end; background: var(--indigo); color: #fff; border: 0; } .m.agent small { color: rgba(255,255,255,.75); }
.m.bot, .m.user_bot { opacity: .7; font-size: .9rem; background: #f1f5f9; } .m.user_bot { border-style: dashed; }
.m.system { align-self: center; background: transparent; border: 0; color: var(--muted); font-size: .82rem; }
.reply { display: flex; gap: .6rem; padding: .8rem 1rem; background: #fff; border-top: 1px solid var(--line); } .reply textarea { resize: none; }
.empty { margin: auto; text-align: center; padding: 2rem; color: var(--ink); }
@media (max-width: 760px) { .top { gap: .5rem; padding: .55rem .8rem; } .top nav a { font-size: .85rem; padding: .3rem .5rem; } .inbox { grid-template-columns: 1fr; } .inbox.has-conv .list { display: none; } .inbox:not(.has-conv) .conv { display: none; } .back { display: block; } .m { max-width: 88%; } .top nav { margin-left: 0; width: 100%; } }
</style></head><body><?php
}

function page_foot(): void
{
    echo '</body></html>';
}
