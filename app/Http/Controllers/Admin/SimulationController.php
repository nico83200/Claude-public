<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SimulationBatch;
use App\Services\LoadSimulation;
use App\Services\NavigationSimulation;
use App\Services\ServerStatus;
use Illuminate\Http\Request;

/** Simulation de charge et état du serveur en temps réel (super-administrateur). */
class SimulationController extends Controller
{
    public function __construct(private LoadSimulation $simulation) {}

    public function index()
    {
        $batches = SimulationBatch::with('author')->latest('id')->limit(20)->get();

        return view('admin.simulation', [
            'batches' => $batches,
            'active' => $batches->firstWhere('status', 'running') ?? $batches->first(fn ($b) => in_array($b->status, ['stopped', 'purging'], true)),
            'defaults' => LoadSimulation::DEFAULTS,
            'limits' => LoadSimulation::LIMITS,
        ]);
    }

    public function store(Request $request)
    {
        $rules = collect(LoadSimulation::LIMITS)->map(fn ($l, $key) => [$key === 'shares' ? 'nullable' : 'required', 'integer', 'min:'.$l[0], 'max:'.$l[1]])->all()
            + ['shares_accept' => ['nullable', 'boolean']];
        $batch = $this->simulation->start($request->user(), $request->validate($rules));

        return $request->wantsJson()
            ? response()->json($this->simulation->progress($batch) + ['step_url' => route('admin.simulation.step', $batch)])
            : redirect()->route('admin.simulation.index')->with('success', 'Simulation préparée : lancez-la depuis cette page.');
    }

    public function step(SimulationBatch $batch)
    {
        return response()->json($this->simulation->step($batch));
    }

    public function stop(Request $request, SimulationBatch $batch)
    {
        $this->simulation->stop($batch);

        return $request->wantsJson() ? response()->json($this->simulation->progress($batch->fresh())) : back();
    }

    public function resume(Request $request, SimulationBatch $batch)
    {
        $this->simulation->resume($batch);

        return $request->wantsJson() ? response()->json($this->simulation->progress($batch->fresh())) : back();
    }

    public function purge(SimulationBatch $batch)
    {
        return response()->json($this->simulation->purgeStep($batch));
    }

    /** Simulation de navigation (rendu interne) : une étape de pages générées comme des comptes de test. */
    public function navigation(Request $request, NavigationSimulation $navigation)
    {
        $pages = $request->validate(['pages' => ['required', 'integer', 'min:1', 'max:'.NavigationSimulation::MAX_PAGES]])['pages'];
        $visits = $navigation->step($pages);
        if (! $visits) {
            return response()->json(['message' => 'Aucun compte de test : lancez d\'abord une simulation de création.'], 422);
        }

        return response()->json(['visits' => $visits])->header('Cache-Control', 'no-store');
    }

    public function status(ServerStatus $status)
    {
        return response()->json($status->snapshot())->header('Cache-Control', 'no-store');
    }
}
