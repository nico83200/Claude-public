<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\CareReminderRule;
use App\Models\Horse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
