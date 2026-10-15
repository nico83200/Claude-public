<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use App\Models\CareRecord;
use App\Models\Horse;
use App\Models\Reminder;
use App\Models\Treatment;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\CareReminders;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Rappels : rendez-vous du lendemain, échéances de soins, fins de traitement.
 * Chaque rappel n'est envoyé qu'une fois (table reminders, clé unique) et
 * uniquement aux personnes ayant accès à l'information concernée.
 */
class SendReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Envoie les rappels (rendez-vous, échéances de soins, fins de traitement)';

    public function handle(HorseAccess $access, CareReminders $reminders): int
    {
        $sent = 0;
        $tomorrow = [now()->addDay()->startOfDay(), now()->addDay()->endOfDay()];

        foreach (CalendarEvent::with('horse.organization')->whereNotNull('horse_id')->whereIn('status', ['planned', 'confirmed'])->whereBetween('starts_at', $tomorrow)->get() as $event) {
            $sent += $this->dispatch($access, $event, $event->horse, $event->isSessionEvent() ? Perm::SESSIONS_VIEW : Perm::CALENDAR_VIEW, 'appointment', $event->isSessionEvent() ? 'Séance demain' : 'Rendez-vous demain', $event->title.' le '.$event->starts_at->format('d/m/Y à H:i').'.', route('calendar.show', $event), $event->starts_at);
        }
        // Échéances de suivi : règle du cheval (maréchal, vaccination… actifs par défaut),
        // rappel N jours avant puis le jour même ; un contrôle déjà remplacé par un soin plus récent est ignoré.
        $today = now()->startOfDay();
        $rulesCache = [];
        $dueRecords = CareRecord::with(['horse.organization', 'category'])->whereNotNull('next_check_on')->whereNull('next_check_done_at')
            ->whereBetween('next_check_on', [$today->toDateString(), $today->copy()->addDays(60)->toDateString()])->get();
        foreach ($dueRecords as $care) {
            if (! $care->horse) {
                continue;
            }
            $rules = $rulesCache[$care->horse_id] ??= $reminders->rulesFor($care->horse);
            $rule = $rules->get($care->care_category_id);
            if ($rule && ! $rule['enabled']) {
                continue;
            }
            $due = Carbon::parse($care->next_check_on)->startOfDay();
            $notice = $rule['notice_days'] ?? 7;
            $what = $care->category->name.' pour '.$care->horse->shortName();
            // « C'est fait » / « Reporter d'une semaine » sur la notification, pour qui peut modifier la santé du cheval.
            $actions = fn (User $user) => $access->can($user, $care->horse, Perm::HEALTH_EDIT) ? $reminders->notificationActions($care, $user) : [];
            if ($notice > 0 && $due->equalTo($today->copy()->addDays($notice))) {
                $sent += $this->dispatch($access, $care, $care->horse, Perm::HEALTH_VIEW, 'care_due', 'Échéance dans '.$notice.' jour'.($notice > 1 ? 's' : ''), $what.' prévu le '.$due->format('d/m/Y').'. Pensez à prendre rendez-vous.', route('horses.care.index', $care->horse), $due->copy()->subDays($notice), $actions);
            }
            if ($due->equalTo($today)) {
                $sent += $this->dispatch($access, $care, $care->horse, Perm::HEALTH_VIEW, 'care_due', 'Échéance aujourd\'hui', $what.' est prévu aujourd\'hui.', route('horses.care.index', $care->horse), $due, $actions);
            }
        }
        foreach (Treatment::with('horse.organization')->where('status', 'active')->whereDate('ends_on', now()->addDay()->toDateString())->get() as $t) {
            $sent += $this->dispatch($access, $t, $t->horse, Perm::TREATMENTS_VIEW, 'treatment_end', 'Fin de traitement demain', 'Le traitement '.$t->product.' de '.$t->horse->shortName().' se termine le '.$t->ends_on->format('d/m/Y').'.', route('horses.care.index', $t->horse), Carbon::parse($t->ends_on));
        }
        // Pesées régulières : le jour prévu (une seule fois par échéance).
        foreach (Horse::with('organization')->whereNotNull('weigh_every_weeks')->whereNull('archived_at')->get() as $horse) {
            $due = $horse->nextWeighingOn();
            if ($due && $due->isSameDay($today)) {
                $sent += $this->dispatch($access, $horse, $horse, Perm::DAILYLOG_CREATE, 'weight_due', 'Pesée à faire', 'C\'est le jour de peser '.$horse->shortName().' (balance ou ruban) pour suivre son poids.', route('horses.weight', $horse), $due);
            }
        }
        Treatment::where('status', 'active')->whereNotNull('ends_on')->where('ends_on', '<', now()->toDateString())->update(['status' => 'completed']);

        $this->info("$sent rappel(s) envoyé(s).");

        return self::SUCCESS;
    }

    private function dispatch(HorseAccess $access, $model, $horse, string $perm, string $kind, string $title, string $body, string $url, Carbon $at, ?\Closure $actions = null): int
    {
        if (! $horse) {
            return 0;
        }
        $ids = $horse->organization->users()->pluck('users.id')
            ->merge($horse->ownerships()->whereNotNull('user_id')->pluck('user_id'))
            ->merge($horse->accessGrants()->active()->pluck('user_id'))->unique();
        $count = 0;
        foreach (User::whereIn('id', $ids)->whereNull('suspended_at')->get() as $user) {
            if (! in_array($perm, $access->permissions($user, $horse), true)) {
                continue;
            }
            $reminder = Reminder::firstOrCreate(['user_id' => $user->id, 'remindable_type' => $model->getMorphClass(), 'remindable_id' => $model->id, 'remind_at' => $at->copy()->startOfMinute()]);
            if ($reminder->sent_at) {
                continue;
            }
            $user->notify(new AppNotification($kind, $title, $body, $url, actions: $actions ? $actions($user) : []));
            $reminder->update(['sent_at' => now()]);
            $count++;
        }

        return $count;
    }
}
