<?php

namespace App\Console\Commands;

use App\Models\PlanPriceChange;
use App\Models\Subscription;
use App\Notifications\AppNotification;
use App\Services\Audit;
use App\Services\Billing\StripeGateway;
use App\Support\Perm;
use Illuminate\Console\Command;
use Throwable;

/**
 * Migration tarifaire explicite des abonnés existants :
 *  1. information des abonnés dès l'enregistrement du changement (préavis) ;
 *  2. application du nouveau prix Stripe à la date prévue, au renouvellement
 *     (proration_behavior = none), jamais silencieusement.
 */
class ApplyPriceMigrations extends Command
{
    protected $signature = 'billing:price-migrations {--dry-run}';

    protected $description = 'Informe les abonnés des évolutions tarifaires et applique les migrations arrivées à échéance';

    public function handle(StripeGateway $stripe): int
    {
        foreach (PlanPriceChange::with('plan')->where('existing_subscribers', 'migrate_after_notice')->whereNull('subscribers_notified_at')->get() as $change) {
            foreach ($this->subscriptionsFor($change) as $sub) {
                foreach ($sub->organization->members()->with(['user', 'role'])->get() as $m) {
                    if (in_array(Perm::BILLING_MANAGE, $m->role->permissionKeys(), true)) {
                        $new = $sub->interval === 'year' ? $change->new_price_yearly : $change->new_price_monthly;
                        $m->user->notify(new AppNotification('renewal', 'Évolution tarifaire', 'Le tarif de l\'offre '.$change->plan->name.' passera à '.number_format((float) $new, 2, ',', ' ').' € par '.($sub->interval === 'year' ? 'an' : 'mois').' à partir du '.$change->migration_effective_on->format('d/m/Y').'. Vous pouvez résilier à tout moment avant cette date.', route('billing.index')));
                    }
                }
            }
            if (! $this->option('dry-run')) {
                $change->update(['subscribers_notified_at' => now()]);
            }
        }

        $due = PlanPriceChange::with('plan')->where('existing_subscribers', 'migrate_after_notice')->whereNotNull('subscribers_notified_at')
            ->whereDate('migration_effective_on', '<=', now()->toDateString())->get();
        foreach ($due as $change) {
            foreach ($this->subscriptionsFor($change) as $sub) {
                $newPrice = $sub->interval === 'year' ? $change->plan->stripe_price_yearly_id : $change->plan->stripe_price_monthly_id;
                if (! $newPrice || $newPrice === $sub->stripe_price_id) {
                    continue;
                }
                $this->line("Migration de l'abonnement {$sub->stripe_subscription_id} vers {$newPrice}");
                if ($this->option('dry-run')) {
                    continue;
                }
                try {
                    $current = $stripe->retrieveSubscription($sub->stripe_subscription_id);
                    $stripe->updateSubscription($sub->stripe_subscription_id, ['items' => [['id' => $current['items']['data'][0]['id'], 'price' => $newPrice]], 'proration_behavior' => 'none']);
                    Audit::log('billing.price_migrated', $sub, ['to' => $newPrice], $sub->organization_id);
                } catch (Throwable $e) {
                    report($e);
                    $this->error($e->getMessage());
                }
            }
        }

        return self::SUCCESS;
    }

    private function subscriptionsFor(PlanPriceChange $change)
    {
        $old = array_filter([$change->old_stripe_price_monthly_id, $change->old_stripe_price_yearly_id]);

        return Subscription::with('organization')->whereIn('stripe_status', ['active', 'trialing', 'past_due'])->whereIn('stripe_price_id', $old ?: ['-'])->get();
    }
}
