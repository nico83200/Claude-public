<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit;
use App\Support\VaccineRules;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Administration › Vaccins concours : règles FFE et FEI appliquées à la vérification des vaccins avant un concours. */
class VaccineRulesController extends Controller
{
    public function edit()
    {
        return view('admin.vaccine-rules', ['config' => VaccineRules::config(), 'defaults' => VaccineRules::DEFAULTS]);
    }

    public function update(Request $request)
    {
        $sets = array_keys(VaccineRules::DEFAULTS['sets']);
        $int = fn (int $max) => ['required', 'integer', 'min:0', 'max:'.$max];
        $request->validate([
            'default' => ['required', Rule::in($sets)],
            'reminder_days' => ['nullable', 'string', 'max:40', 'regex:/^\s*\d{1,2}(\s*[,;]\s*\d{1,2})*\s*$/'],
            'sets' => ['required', 'array'],
            'sets.*.label' => ['required', 'string', 'max:80'],
            'sets.*.short' => ['required', 'string', 'max:30'],
            'sets.*.source' => ['required', 'string', 'max:500'],
            'sets.*.note' => ['nullable', 'string', 'max:500'],
            'sets.*.checked_on' => ['required', 'date'],
            'sets.*.vaccines.*.months' => $int(36),
            'sets.*.vaccines.*.days' => $int(400),
            'sets.*.vaccines.*.primo_months' => $int(36),
            'sets.*.vaccines.*.primo_days' => $int(400),
            'sets.*.vaccines.*.min_days' => $int(60),
        ], [], ['reminder_days' => 'rappels avant le concours']);

        $config = ['default' => $request->input('default'), 'reminder_days' => [], 'sets' => []];
        foreach ($sets as $key) {
            $in = $request->input('sets.'.$key, []);
            $set = [
                'label' => trim((string) ($in['label'] ?? '')), 'short' => trim((string) ($in['short'] ?? '')),
                'active' => $request->boolean('sets.'.$key.'.active'), 'arrival' => $request->boolean('sets.'.$key.'.arrival'),
                'source' => trim((string) ($in['source'] ?? '')), 'note' => trim((string) ($in['note'] ?? '')), 'checked_on' => (string) ($in['checked_on'] ?? ''), 'vaccines' => [],
            ];
            foreach (VaccineRules::VACCINES as $vaccine) {
                $v = $in['vaccines'][$vaccine] ?? [];
                $dose = ['required' => $request->boolean('sets.'.$key.'.vaccines.'.$vaccine.'.required')];
                foreach (['months', 'days', 'primo_months', 'primo_days', 'min_days'] as $field) {
                    $dose[$field] = (int) ($v[$field] ?? 0);
                }
                // Un vaccin exigé doit rester valable un certain temps.
                if ($dose['required'] && ($dose['months'] + $dose['days'] === 0 || $dose['primo_months'] + $dose['primo_days'] === 0)) {
                    throw ValidationException::withMessages(['sets.'.$key.'.vaccines.'.$vaccine.'.months' => 'Indiquez une durée de validité (mois ou jours).']);
                }
                $set['vaccines'][$vaccine] = $dose;
            }
            $config['sets'][$key] = $set;
        }
        if (! collect($config['sets'])->contains(fn ($s) => $s['active'])) {
            throw ValidationException::withMessages(['default' => 'Gardez au moins un règlement actif.']);
        }
        $config['reminder_days'] = collect(preg_split('/[,;\s]+/', (string) $request->input('reminder_days'), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($d) => (int) $d)->filter(fn ($d) => $d >= 1 && $d <= 90)->unique()->sortDesc()->values()->all();
        VaccineRules::save($config);
        Audit::log('admin.vaccine_rules_updated', null, ['default' => $config['default'], 'reminder_days' => $config['reminder_days']]);

        return back()->with('success', 'Règles des vaccins enregistrées : elles s\'appliquent aux prochaines vérifications.');
    }

    public function reset()
    {
        VaccineRules::reset();
        Audit::log('admin.vaccine_rules_reset', null, []);

        return back()->with('success', 'Valeurs vérifiées le '.Carbon::parse(VaccineRules::DEFAULTS['sets']['ffe_amateur']['checked_on'])->format('d/m/Y').' rétablies.');
    }
}
