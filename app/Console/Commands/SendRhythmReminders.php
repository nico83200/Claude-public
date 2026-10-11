<?php

namespace App\Console\Commands;

use App\Models\Horse;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\CareRhythm;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Console\Command;

/**
 * Rappels intelligents (une fois par jour) : quand le prochain passage habituel approche
 * (5 jours avant, ou jusqu'à 2 semaines de retard) et qu'aucun rendez-vous n'est prévu,
 * les responsables santé du cheval reçoivent « … on prend rendez-vous ? ». Une seule fois par échéance.
 */
class SendRhythmReminders extends Command
{
    protected $signature = 'reminders:rhythm';

    protected $description = 'Propose de prendre rendez-vous selon le rythme habituel des soins';

    public const NOTICE_DAYS = 5;

    public const LATE_DAYS = 14;

    public function handle(CareRhythm $rhythm, HorseAccess $access): int
    {
        $sent = 0;
        Horse::whereNull('archived_at')->with('organization')->chunkById(100, function ($horses) use ($rhythm, $access, &$sent) {
            foreach ($horses as $horse) {
                foreach ($rhythm->forHorse($horse) as $r) {
                    if ($r['planned'] || $r['due_in'] > self::NOTICE_DAYS || $r['due_in'] < -self::LATE_DAYS) {
                        continue;
                    }
                    foreach ($this->recipients($horse, $access) as $user) {
                        $reminder = Reminder::firstOrCreate(['user_id' => $user->id, 'remindable_type' => 'care_rhythm:'.$r['category']->id, 'remindable_id' => $horse->id, 'remind_at' => $r['next']->copy()->startOfDay()]);
                        if ($reminder->sent_at) {
                            continue;
                        }
                        $user->notify(new AppNotification('care_due', $r['category']->name.' : on prend rendez-vous ?', self::message($horse, $r),
                            route('calendar.index', ['nouveau' => 1, 'horse' => $horse->id, 'type' => $r['appointment_type'], 'title' => $r['category']->name,
                                'date' => $r['next']->isPast() ? today()->addDay()->toDateString() : $r['next']->toDateString(), 'professional' => $r['last_professional_id']])));
                        $reminder->update(['sent_at' => now()]);
                        $sent++;
                    }
                }
            }
        });
        $this->info("$sent rappel(s) intelligent(s) envoyé(s).");

        return self::SUCCESS;
    }

    public static function message(Horse $horse, array $r): string
    {
        $ago = $r['last']->diffInDays(today());
        $since = $ago >= 14 ? (int) round($ago / 7).' semaines' : $ago.' jours';

        return 'Dernier passage pour '.$horse->shortName().' il y a '.$since.' ; d\'habitude, c\'est toutes les '.$r['label']
            .($r['due_in'] < 0 ? ' : c\'est en retard.' : ($r['due_in'] === 0 ? ' : c\'est aujourd\'hui.' : ' : prochain passage vers le '.$r['next']->translatedFormat('j F').'.'));
    }

    /** Personnes qui suivent la santé du cheval et peuvent prendre rendez-vous. */
    private function recipients(Horse $horse, HorseAccess $access)
    {
        return $horse->organization->users()->whereNull('suspended_at')->get()
            ->merge(User::whereIn('id', $horse->ownerships()->whereNotNull('user_id')->pluck('user_id'))->whereNull('suspended_at')->get())
            ->unique('id')
            ->filter(fn (User $u) => $access->can($u, $horse, Perm::HEALTH_VIEW) && $access->can($u, $horse, Perm::CALENDAR_EDIT));
    }
}
