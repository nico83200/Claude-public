<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\CareReminderRule;
use App\Models\Horse;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Fréquences de suivi (maréchal, vaccination, vermifuge…) : règle du cheval,
 * sinon valeur par défaut de l'application (config equine.care_reminders).
 */
class CareReminders
{
    /** Règles effectives du cheval, indexées par id de catégorie. */
    public function rulesFor(Horse $horse): Collection
    {
        $rows = CareReminderRule::where('horse_id', $horse->id)->get()->keyBy('care_category_id');
        $defaults = config('equine.care_reminders', []);
        $categories = CareCategory::visibleTo($horse->organization_id)
            ->where(fn ($q) => $q->whereIn('key', array_keys($defaults))->orWhereIn('id', $rows->keys()))
            ->orderBy('name')->get();

        return $categories->mapWithKeys(function (CareCategory $cat) use ($rows, $defaults) {
            $row = $rows->get($cat->id);
            $base = $defaults[$cat->key] ?? ['enabled' => false, 'interval_value' => 6, 'interval_unit' => 'months', 'notice_days' => 7];

            return [$cat->id => [
                'category' => $cat,
                'enabled' => $row ? $row->enabled : (bool) $base['enabled'],
                'interval_value' => $row?->interval_value ?? $base['interval_value'],
                'interval_unit' => $row?->interval_unit ?? $base['interval_unit'],
                'notice_days' => $row?->notice_days ?? $base['notice_days'],
                'customized' => (bool) $row,
            ]];
        })->sortBy(fn ($r) => [! $r['enabled'], $r['category']->name], SORT_REGULAR, false);
    }

    public function ruleFor(Horse $horse, int $categoryId): ?array
    {
        return $this->rulesFor($horse)->get($categoryId);
    }

    public function save(Horse $horse, int $categoryId, array $values): CareReminderRule
    {
        return CareReminderRule::updateOrCreate(['horse_id' => $horse->id, 'care_category_id' => $categoryId], $values);
    }

    public static function addInterval(Carbon $from, int $value, string $unit): Carbon
    {
        return $unit === 'weeks' ? $from->copy()->addWeeks($value) : $from->copy()->addMonthsNoOverflow($value);
    }

    public static function describe(int $value, string $unit): string
    {
        return $value.' '.($unit === 'weeks' ? ($value > 1 ? 'semaines' : 'semaine') : 'mois');
    }

    /**
     * Boutons de la notification de rappel (« C'est fait », « Reporter d'une semaine ») : adresses signées,
     * valables 30 jours, liées à la personne et à la date d'échéance (un second appui ne reporte pas deux fois).
     *
     * @return array<int, array{action: string, title: string, url: string}>
     */
    public function notificationActions(CareRecord $due, User $user): array
    {
        $params = ['care' => $due->id, 'user' => $user->id, 'due' => Carbon::parse($due->next_check_on)->toDateString()];

        return [
            ['action' => 'done', 'title' => 'C\'est fait', 'url' => URL::temporarySignedRoute('care.quick', now()->addDays(30), $params + ['action' => 'fait'])],
            ['action' => 'later', 'title' => 'Reporter d\'une semaine', 'url' => URL::temporarySignedRoute('care.quick', now()->addDays(30), $params + ['action' => 'reporter'])],
        ];
    }

    /**
     * « C'est fait » depuis un rappel : soin enregistré aujourd'hui (même catégorie, même intervenant),
     * échéance suivante calculée selon la fréquence du cheval ; le rappel en attente est clos.
     */
    public function markDone(CareRecord $due, User $user, CalendarService $calendar): CareRecord
    {
        $horse = $due->horse;
        $rule = $this->ruleFor($horse, $due->care_category_id);
        $now = now();
        $next = null;
        if ($rule && $rule['enabled']) {
            $next = self::addInterval($now, (int) $rule['interval_value'], $rule['interval_unit']);
        } elseif ($due->performed_at && $due->next_check_on) {
            // Pas de fréquence enregistrée : même écart que la fois précédente.
            $next = $now->copy()->addDays((int) $due->performed_at->copy()->startOfDay()->diffInDays(Carbon::parse($due->next_check_on)));
        }

        $record = new CareRecord([
            'care_category_id' => $due->care_category_id, 'professional_id' => $due->professional_id, 'performed_at' => $now,
            'reason' => $due->reason, 'observations' => 'Noté depuis la notification de rappel.', 'next_check_on' => $next?->toDateString(),
        ]);
        $record->horse_id = $horse->id;
        $record->author_id = $user->id;
        $record->save();
        $this->closePrevious($record, $calendar);

        return $record;
    }

    /** « Reporter d'une semaine » : l'échéance (et son rendez-vous de contrôle dans l'agenda) avance de 7 jours. */
    public function postpone(CareRecord $due, int $days = 7): Carbon
    {
        $due->next_check_on = Carbon::parse($due->next_check_on)->addDays($days);
        $due->save();

        return $due->next_check_on;
    }

    /**
     * Un nouveau soin clôt les contrôles en attente plus anciens de la même catégorie.
     *
     * @return int[] identifiants des soins dont le contrôle a été clos
     */
    public function closePrevious(CareRecord $record, CalendarService $calendar): array
    {
        $older = CareRecord::where('horse_id', $record->horse_id)->where('care_category_id', $record->care_category_id)
            ->where('id', '!=', $record->id)->where('performed_at', '<=', $record->performed_at)
            ->whereNotNull('next_check_on')->whereNull('next_check_done_at')->get();
        foreach ($older as $old) {
            $old->forceFill(['next_check_done_at' => now()])->saveQuietly();
            CalendarEvent::where('source_type', $old->getMorphClass())->where('source_id', $old->id)
                ->whereIn('status', ['planned', 'confirmed'])->update(['status' => 'done']);
        }

        return $older->pluck('id')->all();
    }
}
