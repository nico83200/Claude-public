<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingService;
use App\Services\Billing\StripeGateway;
use Illuminate\Console\Command;

/**
 * Filet de sécurité si des webhooks Stripe n'arrivent pas : relit les abonnements en cours
 * directement auprès de l'API Stripe (fin d'essai, renouvellement, résiliation, échec de paiement).
 */
class SyncStripeSubscriptions extends Command
{
    protected $signature = 'billing:sync';

    protected $description = 'Relit chez Stripe l\'état des abonnements en cours';

    public function handle(BillingService $billing, StripeGateway $stripe): int
    {
        if (! $stripe->configured()) {
            $this->info('Stripe n\'est pas configuré : rien à synchroniser.');

            return self::SUCCESS;
        }
        $this->info($billing->refreshOpenSubscriptions().' abonnement(s) relu(s) chez Stripe.');

        return self::SUCCESS;
    }
}
