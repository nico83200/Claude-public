<?php

namespace App\Console\Commands;

use App\Models\FeedingPlan;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Console\Command;

/**
 * Fin de période d'une ration (date de fin passée) : archivée automatiquement, et les personnes
 * qui gèrent l'alimentation du cheval en sont prévenues.
 */
class ArchiveFeedingPlans extends Command
{
    protected $signature = 'feeding:archive';

    protected $description = 'Archive les rations arrivées à leur date de fin et prévient les responsables';

    public function handle(HorseAccess $access): int
    {
        $ended = FeedingPlan::with('horse.organization')->whereNull('archived_at')->whereNotNull('ends_on')->where('ends_on', '<', now()->toDateString())->get();
        foreach ($ended as $plan) {
            $plan->update(['archived_at' => now()]);
            $horse = $plan->horse;
            if (! $horse || $horse->trashed()) {
                continue;
            }
            $ids = $horse->organization->users()->pluck('users.id')
                ->merge($horse->ownerships()->whereNotNull('user_id')->pluck('user_id'))
                ->merge($horse->accessGrants()->active()->pluck('user_id'))->unique();
            foreach (User::whereIn('id', $ids)->whereNull('suspended_at')->get() as $user) {
                if ($access->can($user, $horse, Perm::FEEDING_EDIT)) {
                    $user->notify(new AppNotification('feeding_end', 'Fin de ration : '.$plan->name,
                        'La ration « '.$plan->name.' » de '.$horse->shortName().' s\'est terminée le '.$plan->ends_on->format('d/m/Y').'. Elle est archivée ; vous pouvez la reprendre depuis l\'historique.',
                        route('horses.feeding', $horse)));
                }
            }
        }
        $this->info($ended->count().' ration(s) archivée(s).');

        return self::SUCCESS;
    }
}
