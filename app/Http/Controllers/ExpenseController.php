<?php

namespace App\Http\Controllers;

use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseDocument;
use App\Models\Horse;
use App\Models\Organization;
use App\Models\RecurringExpense;
use App\Services\Audit;
use App\Services\CareExpenseSync;
use App\Services\HorseAccess;
use App\Services\ProfessionalDirectory;
use App\Services\RecurringExpenses;
use App\Services\Upsell;
use App\Support\Perm;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dépenses : visibles par cheval (expenses.view) ou pour tout l'espace
 * (expenses.org_view). Montants en DECIMAL(10,2).
 */
class ExpenseController extends Controller
{
    /** Dépenses affichées à la fois dans la liste du budget. */
    public const PER_PAGE = 10;

    public function index(Request $request, HorseAccess $access)
    {
        $user = $request->user();
        $recurring = $this->visibleRecurringQuery($request, $access)->with(['horse', 'category'])
            ->when($request->integer('horse'), fn ($q, $h) => $q->where('horse_id', $h))->orderBy('label')->get();
        // Rattrapage des échéances si le planificateur n'est pas passé.
        $service = app(RecurringExpenses::class);
        $recurring->filter(fn ($r) => $r->isActive() && $r->next_due_on->lte(today()))->each(fn ($r) => $service->generate($r));
        $recurring->each->refresh();

        $query = $this->visibleQuery($request, $access);
        // « Toutes » (year=toutes) : indicateurs et graphiques sur toutes les dates.
        $allDates = $request->query('year') === 'toutes';
        $year = $allDates ? null : (int) $request->query('year', now()->year);

        $filtered = $this->applyFilters($request, clone $query);

        $yearQuery = $allDates ? (clone $filtered) : (clone $filtered)->whereYear('expenses.spent_on', $year);
        $total = (clone $yearQuery)->sum('expenses.amount');
        $first = $allDates ? (clone $yearQuery)->min('expenses.spent_on') : null;
        $creatable = Horse::with('organization')->whereIn('id', $access->horseIdsWith($user, Perm::EXPENSES_CREATE))->whereNull('archived_at')->orderBy('official_name')->get()
            ->filter(fn ($h) => $access->can($user, $h, Perm::EXPENSES_CREATE))
            // Pas de formulaire voué à l'échec : seuls les chevaux dont l'offre inclut le suivi des dépenses.
            ->filter(fn ($h) => $this->entitlements()->hasFeature($h->organization, 'budget'));
        $contextHorse = $this->contextHorse($request, $access);

        return view('budget.index', [
            'recurring' => $recurring,
            'canPay' => fn (Expense $e) => $this->canEdit($request, $e),
            'canDelete' => fn (Expense $e) => $this->canEdit($request, $e),
            'unpaid' => (clone $query)->with(['horse', 'category'])->whereNull('expenses.paid_on')
                ->when($request->integer('horse'), fn ($q, $h) => $q->where('expenses.horse_id', $h))->orderBy('spent_on')->limit(50)->get(),
            'expenses' => $this->expensePage(clone $filtered),
            'year' => $year,
            'periodLabel' => $allDates ? 'Toutes les dates' : 'Année '.$year,
            'kpis' => [
                'month' => (clone $filtered)->whereBetween('expenses.spent_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('expenses.amount'),
                'year' => $total,
                // Moyenne mensuelle : mois écoulés de l'année, ou depuis la première dépense pour « Toutes ».
                'per_month' => $total / max(1, $allDates
                    ? ($first ? (int) Carbon::parse($first)->startOfMonth()->diffInMonths(now()->startOfMonth()) + 1 : 1)
                    : ($year == now()->year ? now()->month : 12)),
                'by_category' => (clone $yearQuery)->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
                    ->select(\DB::raw('expense_categories.name as name, sum(expenses.amount) as total'))->groupBy('expense_categories.name')->orderByDesc('total')->pluck('total', 'name'),
                'by_horse' => (clone $yearQuery)->leftJoin('horses', 'horses.id', '=', 'expenses.horse_id')
                    ->select(\DB::raw("coalesce(horses.official_name, 'Non affecté') as name, sum(expenses.amount) as total"))->groupBy('name')->orderByDesc('total')->pluck('total', 'name'),
                'series' => $this->series($yearQuery, $year, $first),
            ],
            'horses' => Horse::whereIn('id', (clone $query)->whereNotNull('expenses.horse_id')->distinct()->pluck('expenses.horse_id')->merge($creatable->pluck('id')))->orderBy('official_name')->pluck('official_name', 'id'),
            // Ouvert depuis le dossier d'un cheval : la page garde l'en-tête et les onglets du cheval.
            'contextHorse' => $contextHorse,
            'upsell' => app(Upsell::class)->for($contextHorse?->organization ?? $this->currentOrganization(), $user, 'budget'),
            'creatableHorses' => $creatable->pluck('official_name', 'id'),
            // Plus de dépense « générale de l'espace » : chaque dépense est rattachée à un cheval.
            'canOrgExpense' => false,
            'categories' => ExpenseCategory::orderBy('name')->pluck('name', 'id'),
            'careCategoryIds' => ExpenseCategory::whereIn('key', array_keys(CareExpenseSync::CARE_FOR_EXPENSE))->pluck('id')->map(fn ($id) => (string) $id)->values(),
            // Soins récents sans dépense, que la nouvelle dépense peut compléter (un soin n'a qu'une dépense).
            'openCares' => $this->openCares($user, $creatable, $access),
            'professionals' => app(ProfessionalDirectory::class)->visibleQuery($request->user())->whereNull('archived_at')->orderBy('last_name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->fullName()]),
        ]);
    }

    /** « Afficher 10 dépenses de plus » : lignes suivantes (téléphone et tableau), sans recalculer la page. */
    public function more(Request $request, HorseAccess $access)
    {
        $expenses = $this->expensePage($this->applyFilters($request, $this->visibleQuery($request, $access)));
        $data = ['expenses' => $expenses, 'contextHorse' => $this->contextHorse($request, $access), 'canDelete' => fn (Expense $e) => $this->canEdit($request, $e)];

        return response()->json([
            'mobile' => view('budget._expense-items', $data)->render(),
            'desktop' => view('budget._expense-rows', $data)->render(),
            'next' => $expenses->hasMorePages() ? route('budget.more', array_merge($request->query(), ['page' => $expenses->currentPage() + 1])) : null,
        ]);
    }

    /** Filtres de la liste (cheval, catégorie, période). */
    private function applyFilters(Request $request, Builder $query): Builder
    {
        return $query
            ->when($request->integer('horse'), fn ($q, $h) => $q->where('expenses.horse_id', $h))
            ->when($request->integer('category'), fn ($q, $c) => $q->where('expenses.expense_category_id', $c))
            ->when($request->query('from'), fn ($q, $f) => $q->where('expenses.spent_on', '>=', $f))
            ->when($request->query('to'), fn ($q, $t) => $q->where('expenses.spent_on', '<=', $t));
    }

    /** Une page de dépenses, les plus récentes d'abord (sans compter le total : page plus légère). */
    private function expensePage(Builder $query): Paginator
    {
        return $query->with(['horse', 'category', 'documents', 'author'])->orderByDesc('spent_on')->orderByDesc('id')
            ->simplePaginate(self::PER_PAGE)->withQueryString();
    }

    private function contextHorse(Request $request, HorseAccess $access): ?Horse
    {
        $horse = $request->integer('horse') ? Horse::find($request->integer('horse')) : null;

        return $horse && $access->can($request->user(), $horse, Perm::EXPENSES_VIEW) ? $horse : null;
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $org = $this->authorizeTarget($request, $data['horse_id'] ?? null);
        $this->requireFeature($org, 'budget', 'Suivi des dépenses');

        if ($request->boolean('recurring')) {
            $extra = $request->validate([
                'frequency' => ['required', 'in:'.implode(',', array_keys(RecurringExpense::FREQUENCIES))],
                'label' => ['nullable', 'string', 'max:120'],
                'ends_on' => ['nullable', 'date', 'after_or_equal:spent_on'],
            ]);
            $rec = new RecurringExpense([
                'horse_id' => $data['horse_id'] ?? null, 'expense_category_id' => $data['expense_category_id'], 'professional_id' => $data['professional_id'] ?? null,
                'label' => $extra['label'] ?: ExpenseCategory::find($data['expense_category_id'])->name,
                'amount' => $data['amount'], 'frequency' => $extra['frequency'], 'starts_on' => $data['spent_on'], 'next_due_on' => $data['spent_on'],
                'ends_on' => $extra['ends_on'] ?? null, 'supplier' => $data['supplier'] ?? null, 'comment' => $data['comment'] ?? null,
            ]);
            $rec->organization_id = $org->id;
            $rec->author_id = $request->user()->id;
            $rec->save();
            // Toutes les échéances déjà passées d'un coup (sinon le reste, au-delà de 36, revenait plus tard « à payer »).
            app(RecurringExpenses::class)->generate($rec, notify: false, max: RecurringExpenses::MAX_AT_CREATION);
            // Échéances antérieures ou égales à aujourd'hui : réglées si l'utilisateur l'indique.
            if ($request->boolean('paid')) {
                $rec->occurrences()->whereNull('paid_on')->where('spent_on', '<=', today()->toDateString())->update(['paid_on' => DB::raw('spent_on')]);
            }
            if ($first = $rec->occurrences()->orderBy('spent_on')->first()) {
                $this->storeReceipt($request, $first, $org);
            }

            return back()->with('success', 'Dépense récurrente créée : '.mb_strtolower(RecurringExpense::FREQUENCIES[$rec->frequency]).', prochaine échéance le '.$rec->fresh()->next_due_on->translatedFormat('j F Y').'.');
        }

        $expense = new Expense($data);
        $expense->paid_on = $request->boolean('paid', true) ? $data['spent_on'] : null;
        $expense->organization_id = $org->id;
        $expense->author_id = $request->user()->id;
        $expense->save();
        $this->storeReceipt($request, $expense, $org);
        // Dépense de soin (vétérinaire, maréchal…) : le soin est ajouté au carnet de santé avec les mêmes informations.
        // Ou rattachée à un soin déjà noté, qui n'a pas encore de dépense.
        $link = (string) $request->input('care_link', $request->boolean('add_care', true) ? 'new' : 'none');
        if (ctype_digit($link)) {
            $care = $this->openCares($request->user(), collect([$expense->horse]), app(HorseAccess::class))->get($expense->horse_id, collect())->firstWhere('id', (int) $link);
            if ($care) {
                $expense->forceFill(['care_record_id' => $care['id']])->save();
                CareRecord::whereKey($care['id'])->whereNull('cost')->update(['cost' => $expense->amount]);

                return back()->with('success', 'Dépense enregistrée et rattachée au soin du '.$care['date'].'.');
            }
            $link = 'none';
        }
        $care = $link === 'new' ? app(CareExpenseSync::class)->careFromExpense($expense, $request->user()) : null;

        return back()->with('success', $care ? 'Dépense enregistrée, et soin ajouté au carnet de santé.' : 'Dépense enregistrée.');
    }

    /** Détail d'une dépense ; une dépense de soin ouvre le soin, avec ses informations financières. */
    public function show(Request $request, Expense $expense, HorseAccess $access)
    {
        abort_unless($this->visibleQuery($request, $access)->whereKey($expense->id)->exists(), 404);
        if ($expense->care_record_id && $expense->horse && $access->can($request->user(), $expense->horse, Perm::HEALTH_VIEW)
            && CareRecord::whereKey($expense->care_record_id)->where('horse_id', $expense->horse_id)->exists()) {
            return redirect()->route('horses.care.show', [$expense->horse_id, $expense->care_record_id]);
        }

        return view('budget.show', [
            'expense' => $expense->load(['horse', 'category', 'documents', 'author', 'professional', 'recurring']),
            'canEdit' => $this->canEdit($request, $expense),
            'linkedCare' => $this->linkedCare($request, $expense),
        ]);
    }

    public function edit(Request $request, Expense $expense, HorseAccess $access)
    {
        $this->authorizeEdit($request, $expense);

        return view('budget.edit', [
            'expense' => $expense->load('documents'),
            'linkedCare' => $this->linkedCare($request, $expense),
            'categories' => ExpenseCategory::orderBy('name')->pluck('name', 'id'),
            'horses' => Horse::whereIn('id', $access->horseIdsWith($request->user(), Perm::EXPENSES_CREATE))->orderBy('official_name')->pluck('official_name', 'id'),
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $this->authorizeEdit($request, $expense);
        $data = $this->validated($request);
        if (($data['horse_id'] ?? null) != $expense->horse_id) {
            $org = $this->authorizeTarget($request, $data['horse_id'] ?? null);
            abort_unless($org->id === $expense->organization_id, 422, 'Une dépense ne peut pas changer d\'espace.');
        }
        $expense->update($data);
        $this->storeReceipt($request, $expense, $expense->organization);

        // Retour à la page d'où l'on vient (détail de la dépense, fiche du soin), jamais vers un autre site.
        $back = (string) $request->input('return_to');

        return (str_starts_with($back, '/') && ! str_starts_with($back, '//') ? redirect($back) : redirect()->route('budget.index'))->with('success', 'Dépense mise à jour.');
    }

    /** Suivi du paiement : réglée (à la date indiquée, aujourd'hui par défaut) ou de nouveau à payer. */
    public function pay(Request $request, Expense $expense)
    {
        $this->authorizeEdit($request, $expense);
        $data = $request->validate(['paid' => ['required', 'boolean'], 'paid_on' => ['nullable', 'date', 'before_or_equal:today']]);
        $expense->update(['paid_on' => $request->boolean('paid') ? ($data['paid_on'] ?? today()->toDateString()) : null]);
        Audit::log($expense->paid_on ? 'expense.paid' : 'expense.unpaid', $expense, ['amount' => (string) $expense->amount], $expense->organization_id);

        return back()->with('success', $expense->paid_on ? 'Paiement enregistré.' : 'Dépense remise « à payer ».');
    }

    public function destroy(Request $request, Expense $expense)
    {
        $this->authorizeEdit($request, $expense);
        $care = $this->linkedCare($request, $expense);
        $expense->delete();
        Audit::log('expense.deleted', $expense, ['amount' => (string) $expense->amount], $expense->organization_id);
        // Soin rattaché : supprimé aussi si l'utilisateur l'a choisi dans la confirmation.
        $withCare = $care && $request->boolean('with_care');
        if ($withCare) {
            $care->delete();
            Audit::log('care.deleted', $care, ['with_expense' => $expense->id], $expense->horse->organization_id);
        }
        // Suppression d'un glissé dans la liste (téléphone) : réponse JSON avec l'adresse d'annulation,
        // et la proposition de supprimer aussi le soin rattaché.
        if ($request->expectsJson()) {
            return response()->json(['deleted' => true, 'restore_url' => route('budget.restore', $expense)] + ($care && ! $withCare ? ['care' => [
                'text' => 'Supprimer aussi le soin du '.$care->performed_at->format('d/m/Y').' ?',
                'delete_url' => route('horses.care.destroy', [$care->horse_id, $care]),
                'done' => 'Soin du '.$care->performed_at->format('d/m/Y').' supprimé',
            ]] : []));
        }

        // Depuis la page détail (qui n'existe plus), retour au budget.
        return (str_starts_with(url()->previous(), route('budget.show', $expense)) || str_starts_with(url()->previous(), route('budget.edit', $expense)) ? redirect()->route('budget.index', array_filter(['horse' => $expense->horse_id])) : back())
            ->with('success', $withCare ? 'Dépense et soin supprimés.' : 'Dépense supprimée.');
    }

    /**
     * Soins des 6 derniers mois sans dépense rattachée, par cheval (droit de modifier la santé requis).
     *
     * @return Collection<int, Collection<int, array{id: int, label: string, date: string}>>
     */
    private function openCares($user, $horses, HorseAccess $access)
    {
        $ids = $horses->filter(fn ($h) => $h && $access->can($user, $h, Perm::HEALTH_EDIT))->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }

        return CareRecord::with('category')->whereIn('horse_id', $ids)->where('performed_at', '>=', now()->subMonths(6))
            ->whereNotIn('id', Expense::whereNotNull('care_record_id')->select('care_record_id'))
            ->latest('performed_at')->limit(200)->get()
            ->groupBy('horse_id')
            ->map(fn ($cares) => $cares->map(fn (CareRecord $c) => [
                'id' => $c->id, 'date' => $c->performed_at->format('d/m/Y'),
                'label' => $c->performed_at->format('d/m/Y').' · '.$c->category->name.($c->reason ? ' · '.Str::limit($c->reason, 40) : ''),
            ])->values());
    }

    /**
     * Évolution : par mois pour une année, par année pour « Toutes ».
     *
     * @return array<int, array{short: string, long: string, value: float}>
     */
    private function series($query, ?int $year, ?string $first): array
    {
        $rows = (clone $query)->get(['spent_on', 'amount']);
        if ($year) {
            $byMonth = $rows->groupBy(fn ($e) => (int) $e->spent_on->format('n'))->map(fn ($g) => (float) $g->sum('amount'));

            return array_map(fn ($m) => [
                'short' => mb_substr(Carbon::create(null, $m)->translatedFormat('M'), 0, 3), 'long' => ucfirst(Carbon::create(null, $m)->translatedFormat('F')), 'value' => $byMonth[$m] ?? 0.0,
            ], range(1, 12));
        }
        $byYear = $rows->groupBy(fn ($e) => (int) $e->spent_on->format('Y'))->map(fn ($g) => (float) $g->sum('amount'));
        $from = $first ? min((int) Carbon::parse($first)->format('Y'), now()->year) : now()->year;
        $to = max(now()->year, (int) ($byYear->keys()->max() ?? now()->year));

        return array_map(fn ($y) => ['short' => (string) $y, 'long' => (string) $y, 'value' => $byYear[$y] ?? 0.0], range($from, $to));
    }

    /** Soin rattaché à la dépense que l'utilisateur peut supprimer (droit de modifier la santé du cheval). */
    private function linkedCare(Request $request, Expense $expense): ?CareRecord
    {
        if (! $expense->care_record_id || ! $expense->horse) {
            return null;
        }
        $care = CareRecord::whereKey($expense->care_record_id)->where('horse_id', $expense->horse_id)->first();

        return $care && app(HorseAccess::class)->can($request->user(), $expense->horse, Perm::HEALTH_EDIT) ? $care : null;
    }

    /** « Annuler » après une suppression : la dépense revient, avec ses justificatifs. */
    public function restore(Request $request, Expense $expense)
    {
        abort_unless($expense->trashed(), 404);
        $this->authorizeEdit($request, $expense);
        $expense->restore();
        Audit::log('expense.restored', $expense, [], $expense->organization_id);

        return $request->expectsJson()
            ? response()->json(['restored' => true])
            : redirect()->route('budget.show', $expense)->with('success', 'Dépense restaurée.');
    }

    public function receipt(Request $request, Expense $expense, ExpenseDocument $document, HorseAccess $access)
    {
        abort_unless($this->visibleQuery($request, $access)->whereKey($expense->id)->exists(), 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        $name = $document->original_name ?: 'justificatif';
        // Image ou PDF : affiché dans l'application (visionneuse) ; sinon, ou sur demande, téléchargé.
        if ($request->boolean('telecharger') || ! $document->viewerType()) {
            return Storage::disk('local')->download($document->path, $name, ['Cache-Control' => 'private, no-store']);
        }
        $mime = $document->isPdf() ? 'application/pdf' : (Storage::disk('local')->mimeType($document->path) ?: 'application/octet-stream');
        abort_unless($mime === 'application/pdf' || (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml'), 415);

        return Storage::disk('local')->response($document->path, $name, [
            'Content-Type' => $mime, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => 'sandbox',
        ], 'inline');
    }

    public function export(Request $request, string $format, HorseAccess $access)
    {
        // Réservé aux offres payantes : offre de l'espace du cheval exporté, sinon de l'espace courant.
        $horse = $request->integer('horse') ? Horse::find($request->integer('horse')) : null;
        $this->requireFeature($horse?->organization ?? $this->currentOrganization(), 'exports', 'Exports PDF / CSV');
        $expenses = $this->visibleQuery($request, $access)->with(['horse', 'category'])
            ->when($request->integer('horse'), fn ($q, $h) => $q->where('expenses.horse_id', $h))
            ->when(ctype_digit((string) $request->query('year')), fn ($q) => $q->whereYear('expenses.spent_on', (int) $request->query('year')))
            ->orderBy('spent_on')->get();
        Audit::log('export.expenses', null, ['format' => $format, 'count' => $expenses->count()], $this->currentOrganization()->id);
        $name = 'depenses-'.now()->format('Ymd');

        if ($format === 'pdf') {
            return Pdf::loadView('exports.expenses', ['expenses' => $expenses, 'title' => 'Dépenses'])->download($name.'.pdf');
        }

        return response()->streamDownload(function () use ($expenses) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Cheval', 'Catégorie', 'Fournisseur', 'Montant', 'Devise', 'Commentaire'], ';');
            foreach ($expenses as $e) {
                fputcsv($out, [$e->spent_on->format('d/m/Y'), $e->horse?->official_name, $e->category?->name, $e->supplier, number_format((float) $e->amount, 2, ',', ''), $e->currency, $e->comment], ';');
            }
            fclose($out);
        }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Dépenses visibles : chevaux avec expenses.view + espaces avec expenses.org_view. */
    public function visibleQuery(Request $request, HorseAccess $access): Builder
    {
        $user = $request->user();
        $horseIds = $access->horseIdsWith($user, Perm::EXPENSES_VIEW);
        $orgIds = $user->memberships->filter(fn ($m) => in_array(Perm::EXPENSES_ORG_VIEW, $m->role->permissionKeys(), true))->pluck('organization_id');

        return Expense::query()->select('expenses.*')->where(fn ($q) => $q->whereIn('expenses.horse_id', $horseIds)->orWhereIn('expenses.organization_id', $orgIds));
    }

    /** Récurrences visibles : mêmes règles que les dépenses. */
    public function visibleRecurringQuery(Request $request, HorseAccess $access): Builder
    {
        $user = $request->user();
        $horseIds = $access->horseIdsWith($user, Perm::EXPENSES_VIEW);
        $orgIds = $user->memberships->filter(fn ($m) => in_array(Perm::EXPENSES_ORG_VIEW, $m->role->permissionKeys(), true))->pluck('organization_id');

        return RecurringExpense::query()->where(fn ($q) => $q->whereIn('horse_id', $horseIds)->orWhere(fn ($w) => $w->whereNull('horse_id')->whereIn('organization_id', $orgIds)));
    }

    public function authorizeTarget(Request $request, ?int $horseId): Organization
    {
        if ($horseId) {
            $horse = Horse::findOrFail($horseId);
            $this->authorizeHorse($horse, Perm::EXPENSES_CREATE);

            return $horse->organization;
        }
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::EXPENSES_ORG_VIEW);

        return $org;
    }

    protected function canEdit(Request $request, Expense $expense): bool
    {
        $user = $request->user();

        return $expense->horse
            ? $this->horseCan($expense->horse, Perm::EXPENSES_CREATE) && ($expense->author_id === $user->id || $this->horseCan($expense->horse, Perm::HORSE_MANAGE))
            : $user->canInOrg($expense->organization_id, Perm::EXPENSES_ORG_VIEW);
    }

    private function authorizeEdit(Request $request, Expense $expense): void
    {
        abort_unless($this->canEdit($request, $expense), 403, 'Vous ne pouvez pas modifier cette dépense.');
    }

    private function storeReceipt(Request $request, Expense $expense, Organization $org): void
    {
        if (! $request->hasFile('receipt')) {
            return;
        }
        $file = $request->file('receipt');
        if (! $this->entitlements()->canStore($org, $file->getSize())) {
            session()->flash('warning', 'Justificatif non enregistré : espace de stockage insuffisant.');

            return;
        }
        $path = $file->storeAs('organizations/'.$org->id.'/receipts', Str::uuid().'.'.$file->extension(), 'local');
        $expense->documents()->create(['path' => $path, 'original_name' => Str::limit($file->getClientOriginalName(), 250, ''), 'mime' => $file->getMimeType() ?? 'application/octet-stream', 'size_bytes' => $file->getSize()]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            // Toute dépense se rapporte à un cheval.
            'horse_id' => ['required', 'integer'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'spent_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999', 'decimal:0,2'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'professional_id' => ['nullable', 'integer'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'max:'.config('equine.uploads.max_kb'), 'mimes:pdf,jpg,jpeg,png,webp,heic'],
        ]);
        unset($data['receipt']);
        if (! empty($data['professional_id']) && ! app(ProfessionalDirectory::class)->visibleQuery($request->user())->whereKey($data['professional_id'])->exists()) {
            $data['professional_id'] = null;
        }

        return $data;
    }
}
