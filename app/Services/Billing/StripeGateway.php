<?php

namespace App\Services\Billing;

use RuntimeException;
use Stripe\StripeClient;

/**
 * Accès à l'API Stripe, isolé pour pouvoir être remplacé dans les tests.
 * Aucune donnée de carte ne transite par l'application (Stripe Checkout / portail).
 */
class StripeGateway
{
    private ?StripeClient $client = null;

    private ?string $clientKey = null;

    public function configured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    private function client(): StripeClient
    {
        if (! $this->configured()) {
            throw new RuntimeException('Stripe n\'est pas configuré (STRIPE_SECRET manquant).');
        }

        // La clé peut changer en cours d'exécution (réglages modifiés par le super-admin).
        if ($this->clientKey !== config('services.stripe.secret')) {
            $this->clientKey = config('services.stripe.secret');
            $this->client = new StripeClient($this->clientKey);
        }

        return $this->client;
    }

    public function createCustomer(string $email, string $name, array $metadata): string
    {
        return $this->client()->customers->create(['email' => $email, 'name' => $name, 'metadata' => $metadata])->id;
    }

    public function createCheckoutSession(array $params): array
    {
        $s = $this->client()->checkout->sessions->create($params);

        return ['id' => $s->id, 'url' => $s->url];
    }

    public function retrieveCheckoutSession(string $id): array
    {
        return $this->client()->checkout->sessions->retrieve($id, [])->toArray();
    }

    public function createPortalSession(string $customerId, string $returnUrl): string
    {
        return $this->client()->billingPortal->sessions->create(['customer' => $customerId, 'return_url' => $returnUrl])->url;
    }

    public function retrieveSubscription(string $id): array
    {
        return $this->client()->subscriptions->retrieve($id, [])->toArray();
    }

    public function updateSubscription(string $id, array $params): array
    {
        return $this->client()->subscriptions->update($id, $params)->toArray();
    }

    public function createProduct(string $name, array $metadata): string
    {
        return $this->client()->products->create(['name' => $name, 'metadata' => $metadata])->id;
    }

    public function createPrice(string $productId, int $unitAmountCents, string $currency, string $interval, array $metadata = []): string
    {
        return $this->client()->prices->create([
            'product' => $productId, 'unit_amount' => $unitAmountCents, 'currency' => strtolower($currency),
            'recurring' => ['interval' => $interval], 'metadata' => $metadata,
        ])->id;
    }

    /** Vérifie la clé secrète : renvoie le mode (test / live) et la devise du compte. */
    public function testConnection(): array
    {
        $balance = $this->client()->balance->retrieve();

        return ['livemode' => (bool) $balance->livemode, 'currencies' => collect($balance->available ?? [])->pluck('currency')->unique()->values()->all()];
    }

    public function createCoupon(array $params): string
    {
        return $this->client()->coupons->create($params)->id;
    }

    public function createPromotionCode(string $couponId, string $code, array $params = []): string
    {
        return $this->client()->promotionCodes->create($params + [
            'promotion' => ['type' => 'coupon', 'coupon' => $couponId], 'code' => $code,
        ])->id;
    }

    public function setPromotionCodeActive(string $id, bool $active): void
    {
        $this->client()->promotionCodes->update($id, ['active' => $active]);
    }
}
