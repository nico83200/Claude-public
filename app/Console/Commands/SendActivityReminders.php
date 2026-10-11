<?php

namespace App\Console\Commands;

use App\Models\AssistantRequest;
use App\Models\CareRecord;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\HealthObservation;
use App\Models\Reminder;
use App\Models\RidingSession;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\AppNotification;
use App\Services\HorseAccess;
use App\Services\RidingSessionService;
use App\Support\Perm;
use Illuminate\Console\Command;

/**
 * Rappels de saisie (toutes les 5 minutes) :
 * - après une séance ou un cours prévu, proposer d'en faire le bilan (exercices travaillés, ressenti) ;
 * - rappel quotidien à l'heure choisie par l'utilisateur, seulement s'il n'a rien noté dans la journée.
 * Notification dans l'application et sur les appareils abonnés ; jamais par email. Chaque rappel n'est envoyé qu'une fois.
 */
class SendActivityReminders extends Command
{
    protected $signature = 'reminders:activity';

    protected $description = 'Rappels de bilan après les séances et rappel quotidien de saisie';

    /** Délai après la fin prévue de la séance avant de proposer le bilan. */
    private const GRACE_MINUTES = 10;

    public function handle(HorseAccess $access, RidingSessionService $sessions): int
    {
        $debriefs = $this->debriefReminders($access, $sessions);
        $daily = $this->dailyReminders($access);
        $this->info("$debriefs rappel(s) de bilan, $daily rappel(s) quotidien(s).");

        return self::SUCCESS;
    }

    private function debriefReminders(HorseAccess $access, RidingSessionService $sessions): int
    {
        $now = now();
        $count = 0;
        $candidates = RidingSession::with(['horse', 'rider.profile', 'creator.profile'])
            ->whereIn('status', ['planned', 'in_progress'])
            ->whereBetween('scheduled_at', [$now->copy()->subHours(30), $now])
            ->get();
        foreach ($candidates as $session) {
            $end = $session->scheduled_at->copy()->addMinutes(($session->planned_minutes ?: 60) + self::GRACE_MINUTES);
            if ($end->isFuture() || $end->lt($now->copy()->subDay()) || ! $session->horse || $session->horse->archived_at) {
                continue;
            }
            $user = $session->rider ?? $session->creator;
            if (! $user || $user->suspended_at || ! ($user->profile?->debrief_reminders ?? true)) {
                continue;
            }
            if (! $access->can($user, $session->horse, Perm::SESSIONS_VIEW) || ! $sessions->canEdit($user, $session)) {
                continue;
            }
            $reminder = Reminder::firstOrCreate(['user_id' => $user->id, 'remindable_type' => $session->getMorphClass(), 'remindable_id' => $session->id, 'remind_at' => $session->scheduled_at->copy()->startOfMinute()]);
            if ($reminder->sent_at) {
                continue;
            }
            $what = $session->isLesson() ? 'votre cours' : 'votre séance';
            $user->notify(new AppNotification('session_debrief',
                'Comment s\'est passé '.$what.' ?',
                $session->title().' – '.$session->horse->shortName().' : ajoutez les exercices travaillés et votre bilan.',
                route('sessions.debrief', $session), mail: false));
            $reminder->update(['sent_at' => now()]);
            $count++;
        }

        return $count;
    }

    private function dailyReminders(HorseAccess $access): int
    {
        $now = now();
        $count = 0;
        $profiles = UserProfile::with('user')->whereNotNull('daily_reminder_at')->where('daily_reminder_at', '<=', $now->format('H:i:s'))->get();
        foreach ($profiles as $profile) {
            $user = $profile->user;
            if (! $user || $user->suspended_at) {
                continue;
            }
            $at = $now->copy()->setTimeFromTimeString($profile->daily_reminder_at)->startOfMinute();
            if (Reminder::where(['user_id' => $user->id, 'remindable_type' => $user->getMorphClass(), 'remindable_id' => $user->id])->whereDate('remind_at', $now->toDateString())->exists()) {
                continue;
            }
            if ($this->wroteSomethingToday($user) || ! $access->accessibleQuery($user)->whereNull('horses.archived_at')->exists()) {
                continue;
            }
            Reminder::create(['user_id' => $user->id, 'remindable_type' => $user->getMorphClass(), 'remindable_id' => $user->id, 'remind_at' => $at, 'sent_at' => now()]);
            $user->notify(new AppNotification('daily_reminder', 'Rien de noté aujourd\'hui ?',
                'Une séance, un soin, une observation… Dites-le à l\'assistant en quelques mots.', route('dashboard'), mail: false));
            $count++;
        }

        return $count;
    }

    private function wroteSomethingToday(User $user): bool
    {
        $today = now()->startOfDay();

        return RidingSession::where(fn ($q) => $q->where('created_by', $user->id)->orWhere('rider_id', $user->id))
            ->where(fn ($q) => $q->where('created_at', '>=', $today)->orWhere('completed_at', '>=', $today))->exists()
            || DailyLog::where('author_id', $user->id)->where('created_at', '>=', $today)->exists()
            || CareRecord::where('author_id', $user->id)->where('created_at', '>=', $today)->exists()
            || HealthObservation::where('author_id', $user->id)->where('created_at', '>=', $today)->exists()
            || Expense::where('author_id', $user->id)->where('created_at', '>=', $today)->exists()
            || AssistantRequest::where('user_id', $user->id)->where('created_at', '>=', $today)->exists();
    }
}
