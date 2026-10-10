<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use App\Models\CareRecord;
use App\Models\Reminder;
use App\Models\Treatment;
use App\Models\User;
use App\Notifications\AppNotification;
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

    public function handle(HorseAccess $access): int
    {
        $sent = 0;
        $tomorrow = [now()->addDay()->startOfDay(), now()->addDay()->endOfDay()];

        foreach (CalendarEvent::with('horse.organization')->whereNotNull('horse_id')->whereIn('status', ['planned', 'confirmed'])->whereBetween('starts_at', $tomorrow)->get() as $event) {
            $sent += $this->dispatch($access, $event, $event->horse, Perm::CALENDAR_VIEW, 'appointment', 'Rendez-vous demain', $event->title.' le '.$event->starts_at->format('d/m/Y à H:i').'.', route('calendar.show', $event), $event->starts_at);
        }
        foreach (CareRecord::with(['horse.organization', 'category'])->whereNotNull('next_check_on')->whereDate('next_check_on', now()->addDays(7)->toDateString())->get() as $care) {
            $sent += $this->dispatch($access, $care, $care->horse, Perm::HEALTH_VIEW, 'care_due', 'Échéance de soin dans 7 jours', $care->category->name.' pour '.$care->horse->shortName().' prévu le '.$care->next_check_on->format('d/m/Y').'.', route('horses.care.index', $care->horse), Carbon::parse($care->next_check_on));
        }
        foreach (Treatment::with('horse.organization')->where('status', 'active')->whereDate('ends_on', now()->addDay()->toDateString())->get() as $t) {
            $sent += $this->dispatch($access, $t, $t->horse, Perm::TREATMENTS_VIEW, 'treatment_end', 'Fin de traitement demain', 'Le traitement '.$t->product.' de '.$t->horse->shortName().' se termine le '.$t->ends_on->format('d/m/Y').'.', route('horses.care.index', $t->horse), Carbon::parse($t->ends_on));
        }
        Treatment::where('status', 'active')->whereNotNull('ends_on')->where('ends_on', '<', now()->toDateString())->update(['status' => 'completed']);

        $this->info("$sent rappel(s) envoyé(s).");

        return self::SUCCESS;
    }

    private function dispatch(HorseAccess $access, $model, $horse, string $perm, string $kind, string $title, string $body, string $url, Carbon $at): int
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
            $user->notify(new AppNotification($kind, $title, $body, $url));
            $reminder->update(['sent_at' => now()]);
            $count++;
        }

        return $count;
    }
}
