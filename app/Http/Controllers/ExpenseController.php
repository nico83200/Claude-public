<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseDocument;
use App\Models\Horse;
use App\Models\Organization;
use App\Models\Professional;
use App\Services\Audit;
use App\Services\HorseAccess;
use App\Support\Perm;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dépenses : visibles par cheval (expenses.view) ou pour tout l'espace
 * (expenses.org_view). Montants en DECIMAL(10,2).
 */
class ExpenseController extends Controller
{
    public function index(Request $request, HorseAccess $access)
    {
        $user = $request->user();
        $query = $this->visibleQuery($request, $access);
        $year = (int) $request->query('year', now()->year);

        $filtered = (clone $query)
            ->when($request->integer('horse'), fn ($q, $h) => $q->where('expenses.horse_id', $h))
            ->when($request->integer('category'), fn ($q, $c) => $q->where('expenses.expense_category_id', $c))
            ->when($request->query('from'), fn ($q, $f) => $q->where('expenses.spent_on', '>=', $f))
            ->when($request->query('to'), fn ($q, $t) => $q->where('expenses.spent_on', '<=', $t));

        $yearQuery = (clone $filtered)->whereYear('expenses.spent_on', $year);
        $creatable = Horse::whereIn('id', $access->horseIdsWith($user, Perm::EXPENSES_CREATE))->whereNull('archived_at')->orderBy('official_name')->get()
            ->filter(fn ($h) => $access->can($user, $h, Perm::EXPENSES_CREATE));

        return view('budget.index', [
            'expenses' => (clone $filtered)->with(['horse', 'category', 'documents', 'author'])->orderByDesc('spent_on')->orderByDesc('id')->paginate(30)->withQueryString(),
            'year' => $year,
            'kpis' => [
                'month' => (clone $filtered)->whereBetween('expenses.spent_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('expenses.amount'),
                'year' => (clone $yearQuery)->sum('expenses.amount'),
                'by_category' => (clone $yearQuery)->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
                    ->select(\DB::raw('expense_categories.name as name, sum(expenses.amount) as total'))->groupBy('expense_categories.name')->orderByDesc('total')->pluck('total', 'name'),
                'by_horse' => (clone $yearQuery)->leftJoin('horses', 'horses.id', '=', 'expenses.horse_id')
                    ->select(\DB::raw("coalesce(horses.official_name, 'Non affecté') as name, sum(expenses.amount) as total"))->groupBy('name')->orderByDesc('total')->pluck('total', 'name'),
                'by_month' => (clone $yearQuery)->get(['spent_on', 'amount'])->groupBy(fn ($e) => (int) $e->spent_on->format('n'))->map(fn ($g) => $g->sum('amount')),
            ],
            'horses' => Horse::whereIn('id', (clone $query)->whereNotNull('expenses.horse_id')->distinct()->pluck('expenses.horse_id')->merge($creatable->pluck('id')))->orderBy('official_name')->pluck('official_name', 'id'),
            'creatableHorses' => $creatable->pluck('official_name', 'id'),
            'canOrgExpense' => $user->canInOrg($this->currentOrganization(), Perm::EXPENSES_ORG_VIEW) && $this->entitlements()->writable($this->currentOrganization()),
            'categories' => ExpenseCategory::orderBy('name')->pluck('name', 'id'),
            'professionals' => Professional::whereIn('organization_id', $user->memberships->pluck('organization_id'))->whereNull('archived_at')->orderBy('last_name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->fullName()]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $org = $this->authorizeTarget($request, $data['horse_id'] ?? null);
        $this->requireFeature($org, 'budget', 'Suivi des dépenses');

        $expense = new Expense($data);
        $expense->organization_id = $org->id;
        $expense->author_id = $request->user()->id;
        $expense->save();
        $this->storeReceipt($request, $expense, $org);

        return back()->with('success', 'Dépense enregistrée.');
    }

    public function edit(Request $request, Expense $expense, HorseAccess $access)
    {
        $this->authorizeEdit($request, $expense);

        return view('budget.edit', [
            'expense' => $expense->load('documents'),
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

        return redirect()->route('budget.index')->with('success', 'Dépense mise à jour.');
    }

    public function destroy(Request $request, Expense $expense)
    {
        $this->authorizeEdit($request, $expense);
        $expense->delete();
        Audit::log('expense.deleted', $expense, ['amount' => (string) $expense->amount], $expense->organization_id);

        return back()->with('success', 'Dépense supprimée.');
    }

    public function receipt(Request $request, Expense $expense, ExpenseDocument $document, HorseAccess $access)
    {
        abort_unless($this->visibleQuery($request, $access)->whereKey($expense->id)->exists(), 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name, ['Cache-Control' => 'private, no-store']);
    }

    public function export(Request $request, string $format, HorseAccess $access)
    {
        $expenses = $this->visibleQuery($request, $access)->with(['horse', 'category'])
            ->when($request->integer('horse'), fn ($q, $h) => $q->where('expenses.horse_id', $h))
            ->when($request->query('year'), fn ($q, $y) => $q->whereYear('expenses.spent_on', $y))
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
    private function visibleQuery(Request $request, HorseAccess $access): Builder
    {
        $user = $request->user();
        $horseIds = $access->horseIdsWith($user, Perm::EXPENSES_VIEW);
        $orgIds = $user->memberships->filter(fn ($m) => in_array(Perm::EXPENSES_ORG_VIEW, $m->role->permissionKeys(), true))->pluck('organization_id');

        return Expense::query()->select('expenses.*')->where(fn ($q) => $q->whereIn('expenses.horse_id', $horseIds)->orWhereIn('expenses.organization_id', $orgIds));
    }

    private function authorizeTarget(Request $request, ?int $horseId): Organization
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

    private function authorizeEdit(Request $request, Expense $expense): void
    {
        $user = $request->user();
        $ok = $expense->horse
            ? $this->horseCan($expense->horse, Perm::EXPENSES_CREATE) && ($expense->author_id === $user->id || $this->horseCan($expense->horse, Perm::HORSE_MANAGE))
            : $user->canInOrg($expense->organization_id, Perm::EXPENSES_ORG_VIEW);
        abort_unless($ok, 403, 'Vous ne pouvez pas modifier cette dépense.');
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
            'horse_id' => ['nullable', 'integer'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'spent_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999', 'decimal:0,2'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'professional_id' => ['nullable', 'integer'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'max:'.config('equine.uploads.max_kb'), 'mimes:pdf,jpg,jpeg,png,webp,heic'],
        ]);
        unset($data['receipt']);
        if (! empty($data['professional_id']) && ! Professional::whereIn('organization_id', $request->user()->memberships->pluck('organization_id'))->whereKey($data['professional_id'])->exists()) {
            $data['professional_id'] = null;
        }

        return $data;
    }
}
