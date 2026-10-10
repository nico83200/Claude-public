<?php

namespace App\Http\Controllers;

use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Horse;
use App\Models\HorseDocument;
use App\Models\Professional;
use App\Services\Audit;
use App\Services\CalendarService;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CareRecordController extends Controller
{
    public function __construct(private CalendarService $calendar) {}

    public function index(Request $request, Horse $horse)
    {
        $perms = $this->horsePerms($horse);
        abort_unless(array_intersect([Perm::HEALTH_VIEW, Perm::TREATMENTS_VIEW], $perms), 403);
        $canHealth = in_array(Perm::HEALTH_VIEW, $perms, true);

        $records = $canHealth ? $horse->careRecords()->with(['category', 'professional', 'author'])
            ->when($request->integer('category'), fn ($q, $c) => $q->where('care_category_id', $c))
            ->paginate(20)->withQueryString() : null;

        return view('health.horse', [
            'horse' => $horse,
            'records' => $records,
            'canHealth' => $canHealth,
            'canEdit' => $this->horseCan($horse, Perm::HEALTH_EDIT),
            'canObserve' => $this->horseCan($horse, Perm::COMMENTS_CREATE),
            'treatments' => $horse->treatments()->with(['administrations.author', 'responsible'])->get(),
            'observations' => $horse->observations()->with('author')->limit(30)->get(),
            'categories' => CareCategory::visibleTo($horse->organization_id)->orderBy('name')->pluck('name', 'id'),
            'users' => $this->assignableUsers($horse),
        ]);
    }

    public function create(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);

        return view('health.form', $this->formData($horse, new CareRecord(['performed_at' => now()])));
    }

    public function store(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $data = $this->validated($request, $horse);
        $record = DB::transaction(function () use ($request, $horse, $data) {
            $record = new CareRecord($data);
            $record->horse_id = $horse->id;
            $record->author_id = $request->user()->id;
            $record->save();
            $this->afterSave($request, $horse, $record);

            return $record;
        });
        Audit::log('care.created', $record, [], $horse->organization_id);

        return redirect()->route('horses.care.show', [$horse, $record])->with('success', 'Soin enregistré.');
    }

    public function show(Horse $horse, CareRecord $care)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_VIEW);

        return view('health.show', [
            'horse' => $horse,
            'record' => $care->load(['category', 'professional', 'author', 'treatments', 'documents']),
            'canEdit' => $this->horseCan($horse, Perm::HEALTH_EDIT),
        ]);
    }

    public function edit(Horse $horse, CareRecord $care)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);

        return view('health.form', $this->formData($horse, $care));
    }

    public function update(Request $request, Horse $horse, CareRecord $care)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $data = $this->validated($request, $horse);
        // Verrou optimiste : refuse d'écraser une modification concurrente.
        if ((int) $request->input('version') !== (int) $care->version) {
            return back()->withInput()->with('error', 'Ce soin a été modifié par quelqu\'un d\'autre entre-temps. Rechargez la page pour voir la dernière version avant d\'enregistrer.');
        }
        DB::transaction(function () use ($request, $horse, $care, $data) {
            $care->update($data);
            $this->afterSave($request, $horse, $care);
        });
        Audit::log('care.updated', $care, array_keys($care->getChanges()), $horse->organization_id);

        return redirect()->route('horses.care.show', [$horse, $care])->with('success', 'Soin mis à jour.');
    }

    public function destroy(Horse $horse, CareRecord $care)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $care->delete();
        $this->calendar->syncFor($care, $horse, 'care', '', null);
        Audit::log('care.deleted', $care, [], $horse->organization_id);

        return redirect()->route('horses.care.index', $horse)->with('success', 'Soin supprimé.');
    }

    private function afterSave(Request $request, Horse $horse, CareRecord $record): void
    {
        $record->loadMissing('category');
        $this->calendar->syncFor($record, $horse, $this->eventType($record->category?->key), 'Contrôle : '.$record->category?->name.' – '.$horse->shortName(),
            $record->next_check_on ? Carbon::parse($record->next_check_on)->setTime(9, 0) : null,
            ['professional_id' => $record->professional_id, 'duration_minutes' => 30]);

        if ($request->boolean('add_expense') && $record->cost && $this->horseCan($horse, Perm::EXPENSES_CREATE)
            && ! Expense::where('care_record_id', $record->id)->exists()) {
            $catKey = in_array($record->category?->key, ['veterinary', 'farrier', 'osteopathy', 'dentistry'], true) ? $record->category->key : 'veterinary';
            $expense = new Expense([
                'horse_id' => $horse->id, 'expense_category_id' => ExpenseCategory::where('key', $catKey)->value('id'),
                'professional_id' => $record->professional_id, 'care_record_id' => $record->id, 'spent_on' => $record->performed_at->toDateString(),
                'amount' => $record->cost, 'supplier' => $record->professional?->fullName(), 'comment' => $record->reason,
            ]);
            $expense->organization_id = $horse->organization_id;
            $expense->author_id = $request->user()->id;
            $expense->save();
        }

        if ($request->hasFile('attachment') && $this->horseCan($horse, Perm::DOCUMENTS_CREATE)) {
            HorseDocumentController::storeFor($horse, $request->file('attachment'), ['category' => $request->input('attachment_category', 'report'), 'is_sensitive' => true], $request->user()->id, $record);
        }
    }

    private function eventType(?string $categoryKey): string
    {
        return match ($categoryKey) {
            'veterinary', 'vaccination', 'exams', 'imaging', 'specialist' => 'veterinary',
            'farrier' => 'farrier',
            default => 'care',
        };
    }

    private function validated(Request $request, Horse $horse): array
    {
        $data = $request->validate([
            'care_category_id' => ['required', Rule::exists('care_categories', 'id')->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $horse->organization_id))],
            'professional_id' => ['nullable', Rule::exists('professionals', 'id')->where('organization_id', $horse->organization_id)],
            'performed_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string', 'max:10000'],
            'care_performed' => ['nullable', 'string', 'max:10000'],
            'diagnosis' => ['nullable', 'string', 'max:10000'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'next_check_on' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'max:'.config('equine.uploads.max_kb'), 'mimes:'.implode(',', config('equine.uploads.mimes'))],
        ]);
        unset($data['attachment']);

        return $data;
    }

    private function formData(Horse $horse, CareRecord $record): array
    {
        return [
            'horse' => $horse,
            'record' => $record,
            'categories' => CareCategory::visibleTo($horse->organization_id)->orderBy('name')->pluck('name', 'id'),
            'professionals' => Professional::where('organization_id', $horse->organization_id)->whereNull('archived_at')->orderBy('last_name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->fullName().' – '.$p->kindLabel()]),
            'canExpense' => $this->horseCan($horse, Perm::EXPENSES_CREATE),
            'docCategories' => HorseDocument::CATEGORIES,
        ];
    }

    private function assignableUsers(Horse $horse)
    {
        return $horse->organization->users()->orderBy('name')->pluck('name', 'users.id');
    }
}
