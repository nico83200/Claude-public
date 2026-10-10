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

    public function configured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    private function client(): StripeClient
    {
        if (! $this->configured()) {
            throw new RuntimeException('Stripe n\'est pas configuré (STRIPE_SECRET manquant).');
        }

        return $this->client ??= new StripeClient(config('services.stripe.secret'));
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
}
