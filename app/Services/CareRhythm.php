<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Horse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rappels intelligents : rythme réel de chaque type de soin, calculé à partir de l'historique
 * (« vous faites venir le maréchal toutes les 7 semaines en moyenne »), et prochain passage estimé.
 * Il faut au moins 3 soins de la même catégorie ; l'écart retenu est la médiane des 6 derniers.
 */
class CareRhythm
{
    public const MIN_RECORDS = 3;

    /** Catégorie de soin → type de rendez-vous proposé. */
    public const APPOINTMENT_TYPE = ['farrier' => 'farrier', 'veterinary' => 'veterinary', 'vaccination' => 'veterinary', 'exams' => 'veterinary', 'imaging' => 'veterinary', 'specialist' => 'veterinary'];

    public function __construct(private CareReminders $reminders) {}

    /**
     * @return Collection<int, array{category: CareCategory, count: int, interval_days: int, label: string, last: Carbon, next: Carbon, due_in: int, last_professional_id: ?int, rule_days: ?int, planned: ?CalendarEvent}>
     */
    public function forHorse(Horse $horse): Collection
    {
        $records = CareRecord::with('category')->where('horse_id', $horse->id)->orderBy('performed_at')->get()->groupBy('care_category_id');
        $rules = $this->reminders->rulesFor($horse);

        return $records->filter(fn ($group) => $group->count() >= self::MIN_RECORDS)->map(function (Collection $group, $categoryId) use ($horse, $rules) {
            $dates = $group->pluck('performed_at')->map(fn ($d) => $d->copy()->startOfDay())->unique(fn ($d) => $d->toDateString())->values();
            if ($dates->count() < self::MIN_RECORDS) {
                return null;
            }
            $gaps = $dates->sliding(2)->map(fn ($pair) => (int) $pair->first()->diffInDays($pair->last()))->filter(fn ($g) => $g > 0)->take(-6)->sort()->values();
            if ($gaps->isEmpty()) {
                return null;
            }
            $median = (int) round($gaps->count() % 2 ? $gaps[intdiv($gaps->count(), 2)] : ($gaps[$gaps->count() / 2 - 1] + $gaps[$gaps->count() / 2]) / 2);
            $last = $dates->last();
            $next = $last->copy()->addDays($median);
            $rule = $rules->get($categoryId);
            $category = $group->first()->category;
            $type = self::APPOINTMENT_TYPE[$category->key] ?? 'care';
            $planned = CalendarEvent::where('horse_id', $horse->id)->whereIn('status', ['planned', 'confirmed'])->where('starts_at', '>=', today())
                ->where(fn ($q) => $q->where('type', $type)->orWhere('title', 'like', '%'.$category->name.'%'))->orderBy('starts_at')->first();

            return [
                'category' => $category,
                'count' => $group->count(),
                'interval_days' => $median,
                'label' => self::describeDays($median),
                'last' => $last,
                'next' => $next,
                'due_in' => (int) today()->diffInDays($next, false),
                'last_professional_id' => $group->last()->professional_id,
                'rule_days' => $rule && $rule['enabled'] ? (int) CareReminders::addInterval($last, $rule['interval_value'], $rule['interval_unit'])->diffInDays($last, true) : null,
                'appointment_type' => $type,
                'planned' => $planned,
            ];
        })->filter()->sortBy('next')->values();
    }

    /** « 7 semaines », « 2 mois », « 10 jours » : la forme la plus naturelle. */
    public static function describeDays(int $days): string
    {
        if ($days >= 56 && abs($days / 30.44 - round($days / 30.44)) < 0.25) {
            $m = (int) round($days / 30.44);

            return $m.' mois';
        }
        if ($days >= 14) {
            $w = (int) round($days / 7);

            return $w.' semaines';
        }

        return $days.' jour'.($days > 1 ? 's' : '');
    }

    /** Écart notable entre l'habitude observée et la règle de rappel enregistrée (plus de 15 %). */
    public static function differsFromRule(array $r): bool
    {
        return $r['rule_days'] !== null && abs($r['interval_days'] - $r['rule_days']) > max(7, $r['rule_days'] * 0.15);
    }
}
