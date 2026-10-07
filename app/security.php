<?php
declare(strict_types=1);

/**
 * Double authentification (TOTP, RFC 6238) : code à 6 chiffres généré par une application
 * (Google Authenticator, Microsoft Authenticator, Authy…). Le secret est chiffré en base.
 */

function base32_encode(string $bin): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alpha[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function base32_decode(string $b32): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32))) as $c) {
        $bits .= str_pad(decbin(strpos($alpha, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_code(string $secret, ?int $t = null): string
{
    $counter = pack('N*', 0, intdiv($t ?? time(), 30));
    $h = hash_hmac('sha1', $counter, base32_decode($secret), true);
    $o = ord($h[19]) & 0xf;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Créneau (période de 30 s) correspondant au code, ou null. Tolérance ±30 s pour l'horloge du téléphone. */
function totp_match(string $secret, string $code, ?int $now = null): ?int
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6 || $secret === '') {
        return null;
    }
    foreach ([0, -1, 1] as $w) {
        $t = ($now ?? time()) + $w * 30;
        if (hash_equals(totp_code($secret, $t), $code)) {
            return intdiv($t, 30);
        }
    }
    return null;
}

function user_totp_secret(array $u): string
{
    return !empty($u['totp_secret']) ? decrypt_secret($u['totp_secret']) : '';
}

function user_has_2fa(array $u): bool
{
    return user_totp_secret($u) !== '';
}

/** Vérifie le code d'un utilisateur ; un code déjà utilisé est refusé (pas de rejeu). */
function user_totp_verify(array $u, string $code): bool
{
    $slot = totp_match(user_totp_secret($u), $code);
    if ($slot === null || (string)$slot === (string)($u['totp_last'] ?? '')) {
        return false;
    }
    update('users', ['totp_last' => (string)$slot], 'id = ?', [$u['id']]);
    return true;
}

function totp_uri(string $secret, string $account): string
{
    $issuer = app_name();
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
}

/** Les administrateurs doivent-ils obligatoirement activer la double authentification ? */
function admin_2fa_required(): bool
{
    return setting('admin_2fa_required', '0') === '1';
}
