<?php

namespace App\Http\Controllers;

use App\Models\AssistantRequest;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Horse;
use App\Models\Professional;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\AssistantTools;
use App\Services\Assistant\ClaudeGateway;
use App\Services\Audit;
use App\Services\CalendarService;
use App\Services\CareReminders;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Facture photographiée ou PDF lue par l'assistant (Claude) : les informations sont proposées
 * dans un formulaire à vérifier ; rien n'est enregistré sans validation. La facture devient le
 * justificatif de la dépense, et un soin peut être créé en même temps.
 */
class InvoiceController extends ExpenseController
{
    private const TTL_MINUTES = 120;

    /** Envoi de la photo / du PDF puis lecture. */
    public function read(Request $request, AssistantService $assistant, ClaudeGateway $claude, HorseAccess $access)
    {
        $org = $this->currentOrganization();
        $request->validate(['invoice' => ['required', 'file', 'max:'.config('equine.uploads.max_kb'), 'mimes:jpg,jpeg,png,webp,gif,pdf,heic,heif']],
            ['invoice.mimes' => 'Envoyez une photo (JPEG, PNG) ou un PDF.']);
        foreach (['budget' => 'Suivi des dépenses', 'assistant' => 'Assistant vocal (IA)'] as $feature => $label) {
            $this->requireFeature($org, $feature, $label);
        }
        if ($reason = $assistant->unavailableReason($org)) {
            return back()->with('error', $reason);
        }
        $file = $request->file('invoice');
        [$bytes, $mime] = \App\Support\AiImage::prepare($file->getRealPath(), (string) $file->getMimeType());
        if (! $bytes) {
            return back()->with('error', 'Format de photo non lisible : sur iPhone, réglez Appareil photo › Formats sur « Le plus compatible », ou envoyez une capture d\'écran.');
        }

        $user = $request->user();
        $horses = Horse::whereIn('id', $access->horseIdsWith($user, Perm::EXPENSES_CREATE))->whereNull('archived_at')->get();
        $started = microtime(true);
        try {
            $result = $claude->readInvoice($bytes, $mime, $horses->map->shortName()->values()->all(), AssistantTools::EXPENSE_KEYS, AssistantTools::CARE_KEYS);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        AssistantRequest::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'organization_id' => $org->id, 'conversation_id' => (string) Str::uuid(),
            'input_mode' => 'document', 'input_text' => 'Lecture de facture : '.Str::limit($file->getClientOriginalName(), 120),
            'reply' => Str::limit((string) ($result['data']['summary'] ?? ''), 500), 'status' => ($result['data']['is_invoice'] ?? false) ? 'done' : 'clarify',
            'model' => $result['model'], 'input_tokens' => $result['usage']['input'], 'output_tokens' => $result['usage']['output'], 'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);
        if (! ($result['data']['is_invoice'] ?? false)) {
            return back()->with('warning', 'Ce document ne ressemble pas à une facture. Essayez une photo plus nette, à plat et bien éclairée.');
        }

        $token = Str::random(32);
        $path = $file->storeAs('tmp/invoices', $token.'.'.$file->extension(), 'local');
        Cache::put('invoice:'.$token, [
            'user_id' => $user->id, 'path' => $path, 'mime' => $file->getMimeType(), 'name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'size' => $file->getSize(), 'data' => $result['data'], 'horse' => $request->integer('horse') ?: null,
        ], now()->addMinutes(self::TTL_MINUTES));

        return redirect()->route('budget.invoice.review', $token);
    }

    /** Formulaire de vérification, pré-rempli. */
    public function review(Request $request, string $token, HorseAccess $access)
    {
        $pending = $this->pending($request, $token);
        $d = $pending['data'];
        $user = $request->user();
        $horses = Horse::whereIn('id', $access->horseIdsWith($user, Perm::EXPENSES_CREATE))->whereNull('archived_at')->orderBy('official_name')->get()
            ->filter(fn ($h) => $this->entitlements()->hasFeature($h->organization, 'budget'));
        $guessedHorse = $horses->first(fn ($h) => $d['horse_name'] !== '' && in_array(mb_strtolower($d['horse_name']), [mb_strtolower($h->shortName()), mb_strtolower($h->official_name)], true))
            ?? ($pending['horse'] ? $horses->firstWhere('id', $pending['horse']) : null) ?? ($horses->count() === 1 ? $horses->first() : null);
        $date = rescue(fn () => Carbon::parse($d['invoice_date']), null, false);
        $care = $d['care_category'] !== 'none' ? CareCategory::where('key', $d['care_category'])->first() : null;
        $supplierPro = $d['supplier'] !== '' ? Professional::whereIn('organization_id', $user->memberships->pluck('organization_id'))
            ->get()->first(fn ($p) => Str::contains(mb_strtolower($d['supplier']), [mb_strtolower($p->last_name)]) || ($p->company && Str::contains(mb_strtolower($d['supplier']), mb_strtolower($p->company)))) : null;

        // Dépenses déjà saisies qui ressemblent à cette facture (même cheval, montant et date proches) : fusion possible.
        $refDate = $date ?? today();
        $matches = Expense::with(['horse', 'category', 'documents'])->whereIn('horse_id', $horses->pluck('id'))
            ->whereBetween('spent_on', [$refDate->copy()->subDays(45)->toDateString(), $refDate->copy()->addDays(45)->toDateString()])
            ->whereNull('boarding_statement_id')->get()
            ->filter(fn (Expense $e) => $this->canEdit($request, $e))
            ->map(function (Expense $e) use ($d, $refDate, $guessedHorse) {
                $amount = (float) $d['total_amount'];
                $gap = $amount > 0 ? abs((float) $e->amount - $amount) / $amount : 1;
                $score = (1 - min(1, $gap)) * 50 + (1 - min(45, $e->spent_on->diffInDays($refDate)) / 45) * 30
                    + ($guessedHorse && $e->horse_id === $guessedHorse->id ? 15 : 0) + ($e->documents->isEmpty() ? 5 : 0);

                return ['expense' => $e, 'score' => $score, 'same_amount' => $gap < 0.01];
            })
            ->filter(fn ($m) => $m['score'] >= 55)->sortByDesc('score')->take(4)->values();

        return view('budget.invoice', [
            'token' => $token,
            'matches' => $matches,
            'pending' => $pending,
            'd' => $d,
            'horses' => $horses->mapWithKeys(fn ($h) => [$h->id => $h->shortName()]),
            'horseId' => $guessedHorse?->id,
            'categories' => ExpenseCategory::orderBy('name')->pluck('name', 'id'),
            'categoryId' => ExpenseCategory::where('key', $d['expense_category'])->value('id'),
            'date' => $date && ! $date->isFuture() ? $date->toDateString() : today()->toDateString(),
            'careCategories' => CareCategory::where(fn ($q) => $q->whereNull('organization_id')->orWhereIn('organization_id', $user->memberships->pluck('organization_id')))->orderBy('name')->pluck('name', 'id'),
            'care' => $care,
            'professionals' => Professional::whereIn('organization_id', $user->memberships->pluck('organization_id'))->whereNull('archived_at')->orderBy('last_name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->fullName()]),
            'professionalId' => $supplierPro?->id,
            'canOrgExpense' => $user->canInOrg($this->currentOrganization(), Perm::EXPENSES_ORG_VIEW),
        ]);
    }

    /** Validation : dépense (+ justificatif) et, si demandé, soin. */
    public function save(Request $request, string $token, HorseAccess $access, CareReminders $reminders, CalendarService $calendar)
    {
        $pending = $this->pending($request, $token);
        $data = $request->validate([
            'horse_id' => ['required', 'integer'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999', 'decimal:0,2'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'professional_id' => ['nullable', 'integer'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'paid' => ['nullable', 'boolean'],
            'with_care' => ['nullable', 'boolean'],
            'care_category_id' => ['nullable', 'required_if:with_care,1', 'exists:care_categories,id'],
            'merge_expense_id' => ['nullable', 'integer'],
        ]);
        $merge = null;
        if (! empty($data['merge_expense_id'])) {
            $merge = Expense::findOrFail($data['merge_expense_id']);
            abort_unless($this->canEdit($request, $merge) && ! $merge->boarding_statement_id, 403);
        }
        $org = $this->authorizeTarget($request, $data['horse_id'] ?? null);
        $this->requireFeature($org, 'budget', 'Suivi des dépenses');
        $horse = ! empty($data['horse_id']) ? Horse::find($data['horse_id']) : null;
        if (! empty($data['professional_id']) && ! Professional::whereIn('organization_id', $request->user()->memberships->pluck('organization_id'))->whereKey($data['professional_id'])->exists()) {
            $data['professional_id'] = null;
        }
        $withCare = $request->boolean('with_care') && $horse && ! $merge?->care_record_id;
        if ($withCare && ! $access->can($request->user(), $horse, Perm::HEALTH_EDIT)) {
            return back()->withInput()->with('error', 'Vous ne pouvez pas enregistrer de soin pour ce cheval : décochez « Enregistrer aussi le soin ».');
        }

        $expense = DB::transaction(function () use ($request, $data, $org, $horse, $withCare, $pending, $reminders, $calendar, $merge) {
            $care = null;
            if ($withCare) {
                $category = CareCategory::findOrFail($data['care_category_id']);
                $performed = Carbon::parse($data['spent_on'])->setTime(12, 0);
                $rule = $reminders->ruleFor($horse, $category->id);
                $next = $rule && $rule['enabled'] ? CareReminders::addInterval($performed, $rule['interval_value'], $rule['interval_unit']) : null;
                $care = new CareRecord(['care_category_id' => $category->id, 'performed_at' => $performed, 'professional_id' => $data['professional_id'] ?? null,
                    'reason' => Str::limit($data['comment'] ?: $pending['data']['summary'], 250, ''), 'cost' => $data['amount'], 'next_check_on' => $next?->toDateString()]);
                $care->horse_id = $horse->id;
                $care->author_id = $request->user()->id;
                $care->save();
                $reminders->closePrevious($care, $calendar);
            }
            $values = collect($data)->only(['horse_id', 'expense_category_id', 'spent_on', 'amount', 'supplier', 'professional_id', 'comment'])->all();
            if ($merge) {
                // Fusion : la dépense existante prend les informations vérifiées de la facture (et garde son soin éventuel).
                $expense = $merge;
                $expense->fill(array_filter($values, fn ($v) => $v !== null && $v !== '') + ($care ? ['care_record_id' => $care->id] : []));
                if ($request->boolean('paid', true) && ! $expense->paid_on) {
                    $expense->paid_on = $data['spent_on'];
                }
                $expense->organization_id = $org->id;
                $expense->save();
            } else {
                $expense = new Expense($values + ['care_record_id' => $care?->id]);
                $expense->paid_on = $request->boolean('paid', true) ? $data['spent_on'] : null;
                $expense->organization_id = $org->id;
                $expense->author_id = $request->user()->id;
                $expense->save();
            }

            // La facture devient le justificatif (si l'offre a la place de la stocker).
            if (Storage::disk('local')->exists($pending['path']) && $this->entitlements()->canStore($org, (int) $pending['size'])) {
                $dest = 'organizations/'.$org->id.'/receipts/'.Str::uuid().'.'.pathinfo($pending['path'], PATHINFO_EXTENSION);
                Storage::disk('local')->move($pending['path'], $dest);
                $expense->documents()->create(['path' => $dest, 'original_name' => $pending['name'], 'mime' => $pending['mime'], 'size_bytes' => $pending['size']]);
            }

            return $expense;
        });
        Cache::forget('invoice:'.$token);
        Storage::disk('local')->delete($pending['path']);
        Audit::log($merge ? 'expense.merged_with_invoice' : 'expense.created_from_invoice', $expense, ['care' => $expense->care_record_id], $org->id);

        return redirect()->route('budget.index', $horse ? ['horse' => $horse->id] : [])->with('success', $merge
            ? 'Facture rattachée à la dépense existante ('.number_format((float) $expense->amount, 2, ',', ' ').' €) : elle est complétée, sans doublon.'
            : 'Dépense de '.number_format((float) $expense->amount, 2, ',', ' ').' € enregistrée avec la facture'.($withCare ? ', et le soin ajouté au carnet de santé.' : '.'));
    }

    public function cancel(Request $request, string $token)
    {
        $pending = $this->pending($request, $token);
        Storage::disk('local')->delete($pending['path']);
        Cache::forget('invoice:'.$token);

        return redirect()->route('budget.index')->with('status', 'Facture abandonnée : rien n\'a été enregistré.');
    }

    private function pending(Request $request, string $token): array
    {
        $pending = Cache::get('invoice:'.$token);
        abort_unless($pending && $pending['user_id'] === $request->user()->id, 404, 'Lecture de facture expirée : envoyez-la à nouveau.');

        return $pending;
    }
}
