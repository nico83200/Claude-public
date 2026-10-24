<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Cadeau de bienvenue de la newsletter : chaque nouvelle inscrite reçoit un code personnel
 * (ex. CLUB-7KQ2M9) d'un montant fixe, utilisable une seule fois, créé automatiquement dans Stripe
 * (coupon « Bienvenue au club » + code promo limité à 1 utilisation).
 * Sans Stripe, repli sur le code commun saisi dans les réglages (welcome_code).
 */
final class WelcomeOffer
{
    /** Montant du cadeau en centimes (0 = pas de code personnel). */
    public static function amount(): int
    {
        return max(0, (int) setting('welcome_amount', 500));
    }

    public static function active(): bool
    {
        return (self::amount() > 0 && (string) config('stripe.secret_key') !== '') || (string) setting('welcome_code') !== '';
    }

    /** Libellé affiché : « 5 € offerts ». */
    public static function label(): string
    {
        if (self::amount() > 0 && (string) config('stripe.secret_key') !== '') {
            return money(self::amount()) . ' offerts';
        }
        return (string) setting('welcome_discount');
    }

    /** Code de l'inscrite : créé une seule fois, puis toujours le même. */
    public static function codeFor(string $email): ?string
    {
        $email = mb_strtolower(trim($email));
        $existing = Database::value('SELECT welcome_code FROM newsletter_subscribers WHERE email = ?', [$email]);
        if ($existing) {
            return (string) $existing;
        }
        $code = null;
        if (self::amount() > 0 && (string) config('stripe.secret_key') !== '') {
            try {
                $code = self::createStripeCode($email);
            } catch (\Throwable $e) {
                log_message('stripe', 'Code de bienvenue non créé', ['error' => $e->getMessage()]);
            }
        }
        $code ??= (string) setting('welcome_code') ?: null;
        if ($code !== null) {
            Database::query('UPDATE newsletter_subscribers SET welcome_code = ? WHERE email = ?', [$code, $email]);
        }
        return $code;
    }

    private static function createStripeCode(string $email): string
    {
        $stripe = Stripe::fromConfig();
        $amount = self::amount();
        $coupon = json_field((string) setting('welcome_coupon'));
        if (($coupon['amount'] ?? 0) !== $amount || empty($coupon['id'])) {
            $created = $stripe->request('POST', 'coupons', [
                'amount_off' => $amount,
                'currency'   => strtolower((string) config('stripe.currency', 'eur')),
                'duration'   => 'once',
                'name'       => 'Bienvenue au club (' . money($amount) . ')',
            ], 'welcome-coupon-' . $amount . '-' . substr(sha1((string) config('app.key')), 0, 8));
            $coupon = ['id' => $created['id'], 'amount' => $amount];
            \App\Core\Settings::set('welcome_coupon', json_encode($coupon));
        }
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        for ($try = 0; $try < 3; $try++) {
            $code = 'CLUB-';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            try {
                $stripe->request('POST', 'promotion_codes', [
                    'coupon'          => $coupon['id'],
                    'code'            => $code,
                    'max_redemptions' => 1,
                    'metadata'        => ['email' => $email, 'origine' => 'newsletter'],
                ]);
                return $code;
            } catch (\RuntimeException $e) {
                if (!str_contains(mb_strtolower($e->getMessage()), 'already exists')) {
                    throw $e;
                }
            }
        }
        throw new \RuntimeException('Impossible de générer un code unique.');
    }
}
