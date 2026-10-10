<?php

namespace App\Services\Billing;

use App\Models\StripeEvent;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookProcessor
{
    public const ALERT_AFTER_ATTEMPTS = 3;

    public function __construct(private BillingService $billing) {}

    public function process(StripeEvent $event): bool
    {
        // Verrou : deux livraisons simultanées du même événement ne sont pas traitées deux fois.
        return Cache::lock('stripe-event:'.$event->stripe_event_id, 30)->block(10, function () use ($event) {
            $event->refresh();
            if (in_array($event->status, ['processed', 'ignored'], true)) {
                return true;
            }
            $event->increment('attempts');
            try {
                $result = $this->billing->handleEvent($event->payload);
                $event->update(['status' => $result, 'processed_at' => now(), 'last_error' => null]);

                return true;
            } catch (Throwable $e) {
                $event->update(['status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 2000)]);
                Log::error('Traitement du webhook Stripe en échec', ['event' => $event->stripe_event_id, 'type' => $event->type, 'error' => $e->getMessage()]);
                if ($event->attempts === self::ALERT_AFTER_ATTEMPTS) {
                    foreach (User::where('is_super_admin', true)->get() as $admin) {
                        $admin->notify(new AppNotification('payment_failed', 'Webhook Stripe en échec', "L'événement {$event->type} ({$event->stripe_event_id}) échoue de façon répétée.", route('admin.billing.index')));
                    }
                }

                return false;
            }
        });
    }
}
