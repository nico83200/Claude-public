<?php
declare(strict_types=1);

/**
 * Module d'intégration NLapps — à copier dans n'importe quelle application PHP (8.1+) pour la brancher
 * sur le centre d'assistance : licence, mises à jour, FAQ partagée et conversation en direct.
 *
 *   $nl = new NlappsSupport('https://nlapps.fr/assistance/api.php', 'nlh_…', 'mon-appli');
 *   $lic = $nl->check('1.0.0', ['users' => 12]);           // licence, dernière version, FAQ
 *   if (!$lic['licence']['ai']) { … }                       // option IA non souscrite
 *
 * Les appels se font de serveur à serveur : la clé ne doit jamais être envoyée au navigateur
 * (voir examples/relay.php pour relayer la conversation vers le widget).
 */
final class NlappsSupport
{
    public int $lastCode = 0;

    public function __construct(private string $url, private string $key, private string $app = 'app', private int $timeout = 8)
    {
    }

    /** Licence (status, ai, paid_until…), dernière version publiée et FAQ partagée (si $faqHash a changé). */
    public function check(string $version, array $stats = [], string $faqHash = '', string $instanceUrl = ''): ?array
    {
        return $this->call('check', [], ['app' => $this->app, 'version' => $version, 'url' => $instanceUrl, 'php' => PHP_VERSION, 'stats' => $stats, 'faq_hash' => $faqHash]);
    }

    /** Disponibilité de l'équipe : ['online' => bool, 'operator' => 'NLapps', 'away_message' => '…']. */
    public function status(): ?array
    {
        return $this->call('status');
    }

    /** Ouvre une conversation. $user = ['name', 'email', 'role', 'center'] ; renvoie ['id', 'token', 'messages']. À conserver côté serveur. */
    public function open(array $user, string $message, string $context = '', array $transcript = []): ?array
    {
        return $this->call('open', [], ['user' => $user, 'message' => $message, 'context' => $context, 'transcript' => $transcript]);
    }

    public function send(int $id, string $token, string $text): ?array
    {
        return $this->call('send', [], ['id' => $id, 'token' => $token, 'text' => $text]);
    }

    /** Nouveaux messages après $after : ['status' => open|pending|closed, 'rated', 'availability', 'messages' => [{id, from, text, at, file}]]. */
    public function poll(int $id, string $token, int $after = 0): ?array
    {
        return $this->call('poll', ['id' => $id, 'token' => $token, 'after' => $after]);
    }

    public function close(int $id, string $token): ?array
    {
        return $this->call('close', [], ['id' => $id, 'token' => $token]);
    }

    public function rate(int $id, string $token, int $rating, string $comment = ''): ?array
    {
        return $this->call('rate', [], ['id' => $id, 'token' => $token, 'rating' => $rating, 'comment' => $comment]);
    }

    /** Joint une image (contenu binaire JPEG/PNG/WebP, 4 Mo max). */
    public function attach(int $id, string $token, string $imageBytes, string $text = ''): ?array
    {
        return $this->call('attach', [], ['id' => $id, 'token' => $token, 'data' => base64_encode($imageBytes), 'text' => $text]);
    }

    /** FAQ partagée : [{q, k, a, link: [libellé, route]|null, admin}]. */
    public function faq(): array
    {
        return $this->call('faq')['items'] ?? [];
    }

    /** Télécharge une version publiée dans $dest et vérifie son empreinte. Renvoie true si le paquet est intact. */
    public function download(string $version, string $dest, string $sha256 = ''): bool
    {
        $ch = curl_init($this->endpoint('download', ['v' => $version]));
        $fh = fopen($dest, 'wb');
        curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_HTTPHEADER => ['X-Api-Key: ' . $this->key], CURLOPT_TIMEOUT => 120]);
        curl_exec($ch);
        $this->lastCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fh);
        $ok = $this->lastCode === 200 && ($sha256 === '' || hash_equals($sha256, (string)hash_file('sha256', $dest)));
        if (!$ok) {
            @unlink($dest);
        }
        return $ok;
    }

    private function endpoint(string $action, array $query = []): string
    {
        return $this->url . (str_contains($this->url, '?') ? '&' : '?') . http_build_query(['a' => $action] + $query);
    }

    private function call(string $action, array $query = [], ?array $body = null): ?array
    {
        $ch = curl_init($this->endpoint($action, $query));
        $headers = ['X-Api-Key: ' . $this->key, 'Accept: application/json'];
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $this->timeout, CURLOPT_CONNECTTIMEOUT => 4];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts += [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE)];
        }
        curl_setopt_array($ch, $opts + [CURLOPT_HTTPHEADER => $headers]);
        $raw = curl_exec($ch);
        $this->lastCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($data) && $this->lastCode < 400 ? $data : null;
    }
}
