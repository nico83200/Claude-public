<?php

namespace App\Http\Controllers;

use App\Models\StripeEvent;
use App\Services\Billing\WebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Réception des webhooks Stripe : signature vérifiée, journalisation,
 * traitement idempotent (identifiant d'événement unique).
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookProcessor $processor)
    {
        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            Log::error('Webhook Stripe reçu alors que STRIPE_WEBHOOK_SECRET n\'est pas configuré.');

            return response('Webhook non configuré', 503);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret, config('services.stripe.webhook_tolerance'));
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            Log::warning('Webhook Stripe rejeté : signature invalide.', ['ip' => $request->ip()]);

            return response('Signature invalide', 400);
        }

        $payload = $event->toArray();
        $record = StripeEvent::firstOrCreate(['stripe_event_id' => $payload['id']], [
            'type' => $payload['type'], 'stripe_created' => $payload['created'], 'livemode' => (bool) ($payload['livemode'] ?? false), 'payload' => $payload,
        ]);

        if (in_array($record->status, ['processed', 'ignored'], true)) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        $ok = $processor->process($record);

        // 500 → Stripe renverra l'événement (nouvelles tentatives automatiques).
        return $ok ? response()->json(['received' => true]) : response()->json(['received' => false], 500);
    }
}
