<?php

namespace App\Http\Controllers;

use App\Models\BoardingCharge;
use App\Models\BoardingContract;
use App\Models\BoardingStatement;
use App\Models\Horse;
use App\Models\LessonCharge;
use App\Models\Professional;
use App\Models\User;
use App\Services\Audit;
use App\Services\BoardingBilling;
use App\Support\Perm;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Espace écurie › Pension : pensionnaires, prestations, relevés mensuels par propriétaire. */
class BoardingController extends Controller
{
    public function __construct(private BoardingBilling $billing) {}

    public function index(Request $request)
    {
        $org = $this->stable();
        $period = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('mois')) ? $request->query('mois') : now()->format('Y-m');
        $horses = $this->billing->horses($org);
        $contracts = BoardingContract::with('horse')->where('organization_id', $org->id)->orderByRaw('ends_on is not null')->orderBy('owner_name')->get();

        return view('boarding.index', [
            'org' => $org,
            'period' => $period,
            'periodLabel' => ucfirst(Carbon::createFromFormat('Y-m-d', $period.'-01')->translatedFormat('F Y')),
            'contracts' => $contracts,
            'horses' => $horses,
            'withoutContract' => $horses->reject(fn ($h) => $contracts->contains(fn ($c) => $c->horse_id === $h->id && (! $c->ends_on || $c->ends_on->isFuture()))),
            'suggestions' => $horses->mapWithKeys(fn ($h) => [$h->id => $this->billing->suggestOwner($h, $org)]),
            'charges' => BoardingCharge::with('horse')->where('organization_id', $org->id)->where('charged_on', '>=', Carbon::createFromFormat('Y-m-d', $period.'-01'))
                ->where('charged_on', '<=', Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth())->orderByDesc('charged_on')->get(),
            'statements' => BoardingStatement::where('organization_id', $org->id)->where('period', $period)->orderBy('owner_name')->get(),
            // Cours du mois donnés par les enseignants de l'écurie (frais de cours de leurs cavaliers).
            'lessons' => LessonCharge::with(['horse', 'payer'])->where('amount', '>', 0)->whereIn('professional_id', Professional::where('organization_id', $org->id)->select('id'))
                ->whereBetween('charged_on', [Carbon::createFromFormat('Y-m-d', $period.'-01')->toDateString(), Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth()->toDateString()])
                ->orderByDesc('charged_on')->get(),
            'unpaid' => BoardingStatement::where('organization_id', $org->id)->where('status', 'sent')->sum('total'),
        ]);
    }

    public function storeContract(Request $request)
    {
        $org = $this->stable();
        $data = $request->validate([
            'horse_id' => ['required', 'integer'],
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'label' => ['required', 'string', 'max:150'],
            'monthly_amount' => ['required', 'numeric', 'min:0', 'max:99999'],
            'starts_on' => ['required', 'date'],
        ]);
        abort_unless($this->billing->horses($org)->contains('id', (int) $data['horse_id']), 422, 'Ce cheval n\'est pas en pension dans l\'écurie.');
        $horse = Horse::findOrFail($data['horse_id']);
        $suggested = $this->billing->suggestOwner($horse, $org);
        // Compte du propriétaire : celui proposé si le nom correspond, sinon l'adresse email d'un compte existant.
        $ownerId = $suggested['user_id'] && mb_strtolower($suggested['name']) === mb_strtolower($data['owner_name']) ? $suggested['user_id']
            : (! empty($data['owner_email']) ? User::where('email', strtolower($data['owner_email']))->value('id') : null);
        $contract = new BoardingContract($data + ['owner_user_id' => $ownerId]);
        $contract->organization_id = $org->id;
        $contract->save();
        Audit::log('boarding.contract_created', $contract, [], $org->id);

        return back()->with('success', 'Pension de '.$horse->shortName().' enregistrée : '.number_format((float) $contract->monthly_amount, 2, ',', ' ').' € par mois.');
    }

    public function endContract(Request $request, BoardingContract $contract)
    {
        $org = $this->stable();
        abort_unless($contract->organization_id === $org->id, 404);
        $data = $request->validate(['ends_on' => ['required', 'date', 'after_or_equal:'.$contract->starts_on->toDateString()]]);
        $contract->update($data);

        return back()->with('success', 'Fin de pension enregistrée au '.Carbon::parse($data['ends_on'])->format('d/m/Y').' (prorata appliqué sur le relevé).');
    }

    public function storeCharge(Request $request)
    {
        $org = $this->stable();
        $data = $request->validate([
            'horse_id' => ['required', 'integer'],
            'charged_on' => ['required', 'date'],
            'label' => ['required', 'string', 'max:150'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:99999'],
        ]);
        abort_unless($this->billing->horses($org)->contains('id', (int) $data['horse_id']), 422);
        $charge = new BoardingCharge($data + ['author_id' => $request->user()->id]);
        $charge->organization_id = $org->id;
        $charge->save();

        return back()->with('success', 'Prestation ajoutée : elle figurera sur le relevé du mois.');
    }

    public function destroyCharge(BoardingCharge $charge)
    {
        $org = $this->stable();
        abort_unless($charge->organization_id === $org->id, 404);
        $billed = $charge->statement_id && BoardingStatement::whereKey($charge->statement_id)->where('status', '!=', 'draft')->exists();
        abort_if($billed, 422, 'Prestation déjà facturée sur un relevé envoyé.');
        $charge->delete();

        return back()->with('success', 'Prestation supprimée.');
    }

    public function generate(Request $request)
    {
        $org = $this->stable();
        $period = $request->validate(['period' => ['required', 'regex:/^\d{4}-\d{2}$/']])['period'];
        $created = $this->billing->generate($org, $period);
        Audit::log('boarding.statements_generated', null, ['period' => $period, 'count' => $created->count()], $org->id);

        return redirect()->route('boarding.index', ['mois' => $period])->with($created->isEmpty() ? 'warning' : 'success', $created->isEmpty()
            ? 'Aucun relevé à préparer : pas de pension ni de prestation non facturée ce mois-ci.'
            : $created->count().' relevé(s) prêt(s) : vérifiez-les puis envoyez-les.');
    }

    public function send(BoardingStatement $statement)
    {
        $this->own($statement);
        abort_unless($statement->status === 'draft', 422);
        $this->billing->send($statement);
        Audit::log('boarding.statement_sent', $statement, [], $statement->organization_id);

        return back()->with('success', 'Relevé '.$statement->number.' envoyé'.($statement->owner_user_id ? ' : '.$statement->owner_name.' est prévenu et le retrouve dans son budget.' : '. Téléchargez le PDF pour le transmettre à '.$statement->owner_name.'.'));
    }

    public function pay(Request $request, BoardingStatement $statement)
    {
        $this->own($statement);
        abort_unless($statement->status === 'sent', 422);
        $data = $request->validate(['paid_on' => ['nullable', 'date', 'before_or_equal:today']]);
        $this->billing->markPaid($statement, Carbon::parse($data['paid_on'] ?? today()));

        return back()->with('success', 'Paiement du relevé '.$statement->number.' enregistré.');
    }

    /** Relevé vu par l'écurie ou par le propriétaire concerné, et son PDF. */
    public function show(Request $request, BoardingStatement $statement)
    {
        abort_unless($this->billing->canView($request->user(), $statement), 404);

        if ($request->query('format') === 'pdf') {
            return Pdf::loadView('exports.boarding-statement', ['statement' => $statement->load('organization')])->download('releve-'.$statement->number.'.pdf');
        }

        return view('boarding.statement', ['statement' => $statement->load('organization'), 'isStable' => $request->user()->canInOrg($statement->organization_id, Perm::EXPENSES_ORG_VIEW)]);
    }

    private function stable()
    {
        $org = $this->currentOrganization();
        abort_if($org->isPersonal(), 404);
        $this->authorizeOrg($org, Perm::EXPENSES_ORG_VIEW);
        $this->requireFeature($org, 'stable', 'Gestion d\'écurie');

        return $org;
    }

    private function own(BoardingStatement $statement): void
    {
        abort_unless($statement->organization_id === $this->stable()->id, 404);
    }
}
