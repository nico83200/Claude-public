<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\HorseMeasurement;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Poids et état corporel : pesées, estimations au ruban, note de Henneke, courbe,
 * et alerte aux responsables du cheval en cas de variation importante.
 */
class MeasurementController extends Controller
{
    /** Variation de poids (en %) par rapport à la mesure précédente qui déclenche une alerte. */
    public const ALERT_PERCENT = 5.0;

    /** Écart de note d'état corporel qui déclenche une alerte. */
    public const ALERT_CONDITION = 2;

    public function index(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_VIEW);
        $measures = $horse->measurements()->with('author')->get();
        $withWeight = $measures->whereNotNull('weight_kg')->values();
        $last = $withWeight->last();
        $prev = $withWeight->count() > 1 ? $withWeight[$withWeight->count() - 2] : null;

        return view('horses.weight', [
            'horse' => $horse,
            'measures' => $measures->sortByDesc(fn ($m) => [$m->measured_on, $m->id])->values(),
            'points' => $withWeight->map(fn ($m) => ['d' => $m->measured_on->format('Y-m-d'), 'label' => $m->measured_on->translatedFormat('j M Y'), 'w' => $m->weight_kg, 'm' => $m->method])->values(),
            'last' => $last,
            'change' => $last && $prev ? self::change($prev->weight_kg, $last->weight_kg) : null,
            'lastCondition' => $measures->whereNotNull('body_condition')->last(),
            'canCreate' => $this->horseCan($horse, Perm::DAILYLOG_CREATE) && ! $horse->isArchived(),
            'nextWeighing' => $horse->nextWeighingOn(),
        ]);
    }

    /** Rappel régulier de pesée (notification le jour prévu et pesée à faire dans l'agenda). */
    public function reminder(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::DAILYLOG_CREATE);
        $data = $request->validate(['weigh_every_weeks' => ['nullable', 'integer', Rule::in(array_keys(Horse::WEIGH_FREQUENCIES))]]);
        $horse->forceFill(['weigh_every_weeks' => $data['weigh_every_weeks'] ?? null])->save();
        $next = $horse->nextWeighingOn();

        return back()->with('success', $next ? 'Rappel de pesée enregistré : prochaine pesée le '.$next->format('d/m/Y').', visible dans l\'agenda.' : 'Rappel de pesée désactivé.');
    }

    public function store(Request $request, Horse $horse, HorseAccess $access)
    {
        $this->authorizeHorse($horse, Perm::DAILYLOG_CREATE);
        $data = $request->validate([
            'measured_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(HorseMeasurement::METHODS))],
            'weight_kg' => ['nullable', 'numeric', 'between:30,1500'],
            'girth_cm' => ['nullable', 'integer', 'between:60,300', 'required_if:method,tape'],
            'length_cm' => ['nullable', 'integer', 'between:50,300', 'required_if:method,tape'],
            'body_condition' => ['nullable', 'integer', 'between:1,9'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'girth_cm.required_if' => 'Indiquez le tour de poitrine.',
            'length_cm.required_if' => 'Indiquez la longueur du corps.',
        ]);
        if ($data['method'] !== 'tape' && empty($data['weight_kg']) && empty($data['body_condition'])) {
            throw ValidationException::withMessages(['weight_kg' => 'Indiquez le poids ou la note d\'état corporel (ou choisissez « Ruban et mètre » pour estimer le poids).']);
        }
        if ($data['method'] === 'tape') {
            $data['weight_kg'] = HorseMeasurement::estimateFromTape((int) $data['girth_cm'], (int) $data['length_cm']);
        }

        $previous = $horse->measurements()->where('measured_on', '<=', $data['measured_on'])->get()->last();
        $measure = HorseMeasurement::create($data + ['horse_id' => $horse->id, 'author_id' => $request->user()->id]);

        $alerts = self::alerts($previous, $measure);
        if ($alerts) {
            ObservationController::notifyManagers($horse, $request->user(), $access, 'Variation de poids : '.$horse->shortName(), implode(' ', $alerts).' Pensez à en parler au vétérinaire si besoin.', route('horses.weight', $horse));
        }

        return back()->with($alerts ? 'warning' : 'success', $alerts
            ? 'Mesure enregistrée. '.implode(' ', $alerts)
            : 'Mesure enregistrée'.($data['method'] === 'tape' ? ' : poids estimé à '.number_format($measure->weight_kg, 0, ',', ' ').' kg.' : '.'));
    }

    public function destroy(Request $request, Horse $horse, HorseMeasurement $measurement)
    {
        $this->authorizeHorse($horse, Perm::DAILYLOG_CREATE);
        abort_unless($measurement->horse_id === $horse->id, 404);
        abort_unless($measurement->author_id === $request->user()->id || $this->horseCan($horse, Perm::HORSE_MANAGE), 403);
        $measurement->delete();

        return back()->with('success', 'Mesure supprimée.');
    }

    public static function change(float $from, float $to): array
    {
        return ['kg' => round($to - $from, 1), 'pct' => $from > 0 ? round(($to - $from) / $from * 100, 1) : 0.0];
    }

    /** Messages d'alerte entre deux mesures (variation de poids ou d'état corporel). */
    public static function alerts(?HorseMeasurement $previous, HorseMeasurement $current): array
    {
        $alerts = [];
        if ($previous?->weight_kg && $current->weight_kg) {
            $c = self::change($previous->weight_kg, $current->weight_kg);
            if (abs($c['pct']) >= self::ALERT_PERCENT) {
                $alerts[] = sprintf('%s de %s kg (%s %%) depuis le %s.', $c['kg'] < 0 ? 'Perte' : 'Prise', number_format(abs($c['kg']), 1, ',', ' '),
                    number_format(abs($c['pct']), 1, ',', ' '), $previous->measured_on->format('d/m/Y'));
            }
        }
        if ($previous?->body_condition && $current->body_condition && abs($current->body_condition - $previous->body_condition) >= self::ALERT_CONDITION) {
            $alerts[] = 'Note d\'état corporel passée de '.$previous->body_condition.' à '.$current->body_condition.'.';
        } elseif ($current->body_condition && ($current->body_condition <= 3 || $current->body_condition >= 8)) {
            $alerts[] = 'Note d\'état corporel de '.$current->body_condition.'/9 : '.($current->body_condition <= 3 ? 'cheval trop maigre.' : 'surpoids important.');
        }

        return $alerts;
    }
}
