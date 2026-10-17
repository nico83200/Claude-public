<?php

namespace App\Http\Controllers;

use App\Models\LessonCharge;
use App\Models\RidingSession;
use App\Services\LessonCosts;
use App\Services\RidingSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Frais de cours de chacun (cours, coaching, déplacement), dans son espace : le budget du cheval reste au propriétaire. */
class LessonCostController extends Controller
{
    public function __construct(private LessonCosts $costs) {}

    public function index(Request $request)
    {
        $user = $request->user();
        // Le cours avant le déplacement du même jour.
        $all = LessonCharge::with(['horse', 'professional'])->where('payer_id', $user->id)->orderByDesc('charged_on')->orderByRaw("kind = 'travel'")->orderBy('id')->get();
        $horse = $request->integer('horse') ?: null;
        $charges = $horse ? $all->where('horse_id', $horse)->values() : $all;

        return view('lesson-costs.index', [
            'months' => $charges->groupBy(fn (LessonCharge $c) => $c->charged_on->format('Y-m')),
            'unpaid' => (float) $charges->whereNull('paid_on')->sum('amount'),
            'thisMonth' => (float) $charges->filter(fn (LessonCharge $c) => $c->charged_on->isSameMonth(now()))->sum('amount'),
            'horses' => $all->pluck('horse')->filter()->unique('id')->sortBy(fn ($h) => mb_strtolower($h->shortName()))->values(),
            'horse' => $horse,
        ]);
    }

    public function paid(Request $request, LessonCharge $charge)
    {
        abort_unless($charge->payer_id === $request->user()->id, 403);
        $paid = $request->boolean('paid', ! $charge->paid_on);
        $this->costs->markPaid($charge, $paid ? Carbon::today() : null);

        return back()->with('success', $paid ? 'Frais marqués payés.' : 'Frais remis « à payer ».');
    }

    public function destroy(Request $request, LessonCharge $charge)
    {
        abort_unless(in_array($request->user()->id, [$charge->payer_id, $charge->created_by], true), 403);
        $this->costs->remove($charge);

        return back()->with('success', 'Frais retirés. Ils ne reviendront pas d\'eux-mêmes (« Rétablir » sur la séance).');
    }

    public function restore(Request $request, LessonCharge $charge)
    {
        abort_unless($charge->trashed() && in_array($request->user()->id, [$charge->payer_id, $charge->created_by], true), 403);
        $this->costs->restore($charge);

        return back()->with('success', 'Frais rétablis.');
    }

    /** Carte « Coût du cours » de la séance : prix (vide : tarif de l'enseignant, 0 : cours offert) et personne qui paie. */
    public function updateSession(Request $request, RidingSession $session, RidingSessionService $sessions)
    {
        $user = $request->user();
        abort_unless($session->isLesson() && $sessions->canEdit($user, $session) && $this->costs->canSetCost($session, $user), 403);
        $data = $request->validate([
            'lesson_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'payer_id' => ['required', 'integer', Rule::in(array_keys($this->costs->payerChoices($session, $user)))],
        ]);
        $session->forceFill(['lesson_price' => $data['lesson_price'] ?? null, 'payer_id' => (int) $data['payer_id']])->save();

        return back()->with('success', 'Coût du cours enregistré.');
    }
}
