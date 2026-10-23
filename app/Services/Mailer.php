<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\View;

/**
 * Envoi d'e-mails transactionnels : SMTP authentifié (Hostinger), mail() natif, ou journal (dev).
 */
final class Mailer
{
    /** @var resource|null */
    private $socket = null;

    public static function sendTemplate(string $to, string $subject, string $template, array $data = [], ?string $replyTo = null): bool
    {
        $data += ['subject' => $subject];
        $html = View::render('emails/' . $template, $data, 'emails/layout');
        return (new self())->send($to, $subject, $html, null, $replyTo);
    }

    public static function notifyAdmin(string $subject, string $template, array $data = []): bool
    {
        $admin = (string) (config('mail.admin_email') ?: config('mail.from_email'));
        return $admin !== '' && self::sendTemplate($admin, $subject, $template, $data);
    }

    public function send(string $to, string $subject, string $html, ?string $text = null, ?string $replyTo = null): bool
    {
        $to = self::clean($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            log_message('mail', 'Destinataire invalide', ['to' => $to]);
            return false;
        }
        $subject = self::clean($subject);
        $text ??= self::htmlToText($html);
        $fromEmail = self::clean((string) (config('mail.from_email') ?: 'no-reply@' . (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost')));
        $fromName = self::clean((string) config('mail.from_name', config('app.name')));

        $boundary = 'b_' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encodeHeader($fromName) . ' <' . $fromEmail . '>',
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . (explode('@', $fromEmail)[1] ?? 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        if ($replyTo && filter_var(self::clean($replyTo), FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . self::clean($replyTo);
        }
        $body = "--$boundary\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($text) . "\r\n"
            . "--$boundary\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($html) . "\r\n"
            . "--$boundary--\r\n";

        try {
            switch (config('mail.transport', 'smtp')) {
                case 'log':
                    log_message('mail', "À: $to | Objet: $subject\n$text");
                    return true;
                case 'mail':
                    return mail($to, self::encodeHeader($subject), $body, implode("\r\n", $headers), '-f' . $fromEmail);
                default:
                    $headers[] = 'To: <' . $to . '>';
                    $headers[] = 'Subject: ' . self::encodeHeader($subject);
                    $this->smtp($fromEmail, $to, implode("\r\n", $headers) . "\r\n\r\n" . $body);
                    return true;
            }
        } catch (\Throwable $e) {
            log_message('mail', 'Échec envoi', ['to' => $to, 'subject' => $subject, 'error' => $e->getMessage()]);
            return false;
        } finally {
            if ($this->socket) {
                @fclose($this->socket);
                $this->socket = null;
            }
        }
    }

    private function smtp(string $from, string $to, string $data): void
    {
        $host = (string) config('mail.host');
        $port = (int) config('mail.port', 465);
        $encryption = (string) config('mail.encryption', 'ssl');
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;

        $this->socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
        if (!$this->socket) {
            throw new \RuntimeException("Connexion SMTP impossible ($errno) : $errstr");
        }
        stream_set_timeout($this->socket, 20);

        $this->expect(220);
        $ehlo = 'EHLO ' . (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost');
        $this->command($ehlo, 250);

        if ($encryption === 'tls') {
            $this->command('STARTTLS', 220);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new \RuntimeException('Échec de la négociation TLS.');
            }
            $this->command($ehlo, 250);
        }

        $user = (string) config('mail.username');
        if ($user !== '') {
            $this->command('AUTH LOGIN', 334);
            $this->command(base64_encode($user), 334);
            $this->command(base64_encode((string) config('mail.password')), 235);
        }

        $this->command("MAIL FROM:<$from>", 250);
        $this->command("RCPT TO:<$to>", [250, 251]);
        $this->command('DATA', 354);
        // Dot-stuffing (RFC 5321 §4.5.2)
        $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $data));
        $this->command($data . "\r\n.", 250);
        $this->command('QUIT', 221);
    }

    private function command(string $line, int|array $expected): string
    {
        fwrite($this->socket, $line . "\r\n");
        return $this->expect($expected);
    }

    private function expect(int|array $expected): string
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, (array) $expected, true)) {
            throw new \RuntimeException('Réponse SMTP inattendue : ' . trim($response));
        }
        return $response;
    }

    private static function clean(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    private static function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(style|head)[^>]*>.*?</\1>#si', '', $html);
        $html = preg_replace('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#si', '$2 ($1)', $html);
        $html = preg_replace('#<(br|/p|/tr|/h[1-6]|/li|/div)[^>]*>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace("/\n\s*\n\s*\n+/", "\n\n", preg_replace('/[ \t]+/', ' ', $text)));
    }
}
