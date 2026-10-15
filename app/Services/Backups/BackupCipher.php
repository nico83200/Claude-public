<?php

namespace App\Services\Backups;

use RuntimeException;

/**
 * Chiffrement des sauvegardes (libsodium, XChaCha20-Poly1305 en flux) : fichiers de toute taille
 * chiffrés par blocs de 1 Mo, sans les charger en mémoire. Toute modification du fichier est détectée.
 *
 * Format : « JCBK1\n » + en-tête du flux (24 octets) + blocs [longueur sur 4 octets][bloc chiffré].
 * La clé (32 octets) s'affiche sous la forme « jcbk_… » : c'est la clé de restauration à conserver.
 */
class BackupCipher
{
    private const MAGIC = "JCBK1\n";

    private const CHUNK = 1048576;

    public static function newKey(): string
    {
        return self::encodeKey(sodium_crypto_secretstream_xchacha20poly1305_keygen());
    }

    public static function encodeKey(string $raw): string
    {
        return 'jcbk_'.rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function decodeKey(string $key): string
    {
        $key = trim($key);
        $raw = str_starts_with($key, 'jcbk_') ? base64_decode(strtr(substr($key, 5), '-_', '+/'), true) : false;
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('Clé de restauration invalide (elle commence par « jcbk_ »).');
        }

        return $raw;
    }

    public function encryptFile(string $source, string $target, string $key): void
    {
        $in = fopen($source, 'rb');
        $out = fopen($target, 'wb');
        if (! $in || ! $out) {
            throw new RuntimeException('Impossible de lire ou d\'écrire le fichier de sauvegarde.');
        }
        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push(self::decodeKey($key));
            fwrite($out, self::MAGIC.$header);
            $size = filesize($source);
            $done = 0;
            do {
                $chunk = (string) fread($in, self::CHUNK);
                $done += strlen($chunk);
                $tag = $done >= $size ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
                fwrite($out, pack('N', strlen($cipher)).$cipher);
            } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    public function decryptFile(string $source, string $target, string $key): void
    {
        $in = fopen($source, 'rb');
        if (! $in) {
            throw new RuntimeException('Fichier de sauvegarde illisible.');
        }
        $out = null;
        try {
            if (fread($in, strlen(self::MAGIC)) !== self::MAGIC) {
                throw new RuntimeException('Ce fichier n\'est pas une sauvegarde chiffrée Jack&Cie.');
            }
            $header = (string) fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, self::decodeKey($key));
            $out = fopen($target, 'wb');
            $final = false;
            while (! $final) {
                $len = fread($in, 4);
                if ($len === false || strlen($len) !== 4) {
                    throw new RuntimeException('Sauvegarde incomplète (fichier tronqué).');
                }
                $cipher = (string) fread($in, unpack('N', $len)[1]);
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
                if ($result === false) {
                    throw new RuntimeException('Déchiffrement impossible : mauvaise clé ou fichier modifié.');
                }
                [$plain, $tag] = $result;
                fwrite($out, $plain);
                $final = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }
        } catch (RuntimeException $e) {
            if ($out) {
                fclose($out);
                $out = null;
                @unlink($target);
            }
            throw $e;
        } finally {
            fclose($in);
            if ($out) {
                fclose($out);
            }
        }
    }
}
