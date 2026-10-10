<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Notifications\AppNotification;
use App\Services\Audit;
use Illuminate\Console\Command;

/**
 * Transitions temporelles des licences qui ne dépendent d'aucun événement Stripe :
 * fin de période de grâce, fin de licence manuelle ou d'essai.
 */
class RefreshLicenses extends Command
{
    protected $signature = 'licenses:refresh';

    protected $description = 'Applique les fins de période de grâce et les expirations de licences';

    public function handle(): int
    {
        $graceEnded = License::where('status', 'grace')->whereNotNull('grace_ends_at')->where('grace_ends_at', '<', now())->get();
        foreach ($graceEnded as $license) {
            $license->update(['status' => 'suspended']);
            Audit::log('license.grace_ended', $license, [], $license->organization_id);
            $license->organization->owner?->notify(new AppNotification('payment_failed', 'Abonnement suspendu', 'La période de grâce est terminée sans régularisation du paiement : l\'espace '.$license->organization->name.' passe en lecture seule. Vos données sont conservées et exportables.', route('billing.index')));
        }

        $expired = License::whereIn('source', ['manual', 'trial'])->whereIn('status', License::WRITABLE)->whereNotNull('ends_at')->where('ends_at', '<', now())->get();
        foreach ($expired as $license) {
            $license->update(['status' => 'expired']);
            Audit::log('license.expired', $license, [], $license->organization_id);
        }

        $this->info("Grâce terminée : {$graceEnded->count()} · Licences expirées : {$expired->count()}");

        return self::SUCCESS;
    }
}
