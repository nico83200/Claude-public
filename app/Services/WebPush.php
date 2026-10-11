<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush as Sender;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Notifications sur le téléphone ou l'ordinateur (protocole Web Push, sans service tiers payant).
 *
 * Les clés VAPID sont créées au premier usage et conservées en base, la clé privée chiffrée
 * avec APP_KEY : rien n'est stocké dans le code ni dans le dépôt.
 */
class WebPush
{
    private const SETTING = 'webpush.vapid';

    /** Clé publique transmise au navigateur pour s'abonner. */
    public function publicKey(): string
    {
        return $this->keys()['public'];
    }

    /** @return array{public: string, private: string} */
    private function keys(): array
    {
        $stored = ApplicationSetting::get(self::SETTING);
        if (is_array($stored) && isset($stored['public'], $stored['private'])) {
            try {
                return ['public' => $stored['public'], 'private' => Crypt::decryptString($stored['private'])];
            } catch (Throwable) {
                // APP_KEY changée : nouvelles clés, les appareils devront se réabonner.
            }
        }
        $keys = VAPID::createVapidKeys();
        ApplicationSetting::put(self::SETTING, ['public' => $keys['publicKey'], 'private' => Crypt::encryptString($keys['privateKey'])]);
        PushSubscription::query()->delete();

        return ['public' => $keys['publicKey'], 'private' => $keys['privateKey']];
    }

    private function subject(): string
    {
        $url = (string) config('app.url');
        if (str_starts_with($url, 'https://')) {
            return $url;
        }
        $from = (string) config('mail.from.address');

        return 'mailto:'.(filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : 'contact@jackcie.app');
    }

    /**
     * Envoie à tous les appareils de l'utilisateur. Les abonnements expirés (404/410) sont supprimés.
     *
     * @param  array{title: string, body: string, url?: string|null, tag?: string|null}  $payload
     * @return int nombre d'appareils atteints
     */
    public function send(User $user, array $payload): int
    {
        $subscriptions = PushSubscription::where('user_id', $user->id)->get();
        if ($subscriptions->isEmpty()) {
            return 0;
        }
        $keys = $this->keys();
        $sender = new Sender(['VAPID' => ['subject' => $this->subject(), 'publicKey' => $keys['public'], 'privateKey' => $keys['private']]],
            ['TTL' => 6 * 3600, 'urgency' => 'normal'], logger: new NullLogger);
        $json = json_encode(array_filter($payload + ['icon' => '/icons/icon-192.png'], fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $reached = 0;
        foreach ($subscriptions as $sub) {
            try {
                $report = $sender->sendOneNotification(Subscription::create([
                    'endpoint' => $sub->endpoint, 'publicKey' => $sub->public_key, 'authToken' => $sub->auth_token, 'contentEncoding' => $sub->content_encoding,
                ]), $json);
            } catch (Throwable $e) {
                Log::warning('Web Push : envoi impossible', ['subscription' => $sub->id, 'error' => $e->getMessage()]);

                continue;
            }
            if ($report->isSuccess()) {
                $sub->forceFill(['last_success_at' => now(), 'failures' => 0])->save();
                $reached++;
            } elseif ($report->isSubscriptionExpired()) {
                $sub->delete();
            } else {
                $sub->increment('failures');
                if ($sub->failures >= 10) {
                    $sub->delete();
                }
                Log::warning('Web Push : refusé', ['subscription' => $sub->id, 'reason' => $report->getReason()]);
            }
        }

        return $reached;
    }
}
