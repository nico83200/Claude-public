<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Horse;
use App\Models\HorseDocument;
use App\Models\Professional;
use App\Services\Audit;
use App\Services\CalendarService;
use App\Services\CareCalendarSync;
use App\Services\CareReminders;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CareRecordController extends Controller
{
    public function __construct(private CalendarService $calendar, private CareReminders $reminders) {}

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
            'appointments' => $canHealth ? CalendarEvent::where('horse_id', $horse->id)
                ->whereIn('type', array_keys(CareCalendarSync::CATEGORY_FOR_EVENT))
                ->whereIn('status', ['planned', 'confirmed'])->whereNull('care_record_id')
                ->where('starts_at', '>=', now()->subMonths(3))
                ->with('professional')->orderBy('starts_at')->limit(15)->get() : collect(),
            'canEdit' => $this->horseCan($horse, Perm::HEALTH_EDIT),
            'canObserve' => $this->horseCan($horse, Perm::COMMENTS_CREATE),
            'treatments' => $horse->treatments()->with(['administrations.author', 'responsible'])->get(),
            'observations' => $horse->observations()->with('author')->limit(30)->get(),
            'categories' => CareCategory::visibleTo($horse->organization_id)->orderBy('name')->pluck('name', 'id'),
            'users' => $this->assignableUsers($horse),
            'nextChecks' => $canHealth ? $this->nextChecks($horse) : collect(),
            'reminderRules' => $canHealth ? $this->reminders->rulesFor($horse)->map(function ($r, $catId) use ($horse) {
                $last = $horse->careRecords()->where('care_category_id', $catId)->latest('performed_at')->first();

                return $r + ['last' => $last?->performed_at, 'next' => $last?->next_check_done_at ? null : $last?->next_check_on];
            }) : collect(),
        ]);
    }

    /**
     * Prochaine échéance de contrôle de chaque type de soin (celle du dernier soin du type), avec son rendez-vous
     * s'il a été ajouté à l'agenda.
     */
    private function nextChecks(Horse $horse)
    {
        $latest = $horse->careRecords()->with('category')->whereNotIn('id', CareRecord::query()->from('care_records as later')
            ->join('care_records as prev', fn ($j) => $j->on('prev.horse_id', '=', 'later.horse_id')->on('prev.care_category_id', '=', 'later.care_category_id')->on('later.performed_at', '>', 'prev.performed_at'))
            ->whereNull('later.deleted_at')->where('later.horse_id', $horse->id)->select('prev.id'))
            ->whereNotNull('next_check_on')->whereNull('next_check_done_at')->get()->sortBy('next_check_on')->values();
        $events = CalendarEvent::where('source_type', (new CareRecord)->getMorphClass())->whereIn('source_id', $latest->pluck('id'))->get()->keyBy('source_id');

        return $latest->map(fn (CareRecord $c) => ['care' => $c, 'event' => $events->get($c->id)]);
    }

    public function create(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $record = new CareRecord(['performed_at' => now()]);
        // Validation d'une intervention prévue au calendrier : formulaire prérempli.
        $event = $request->integer('evenement') ? CalendarEvent::where('horse_id', $horse->id)->find($request->integer('evenement')) : null;
        if ($event) {
            $key = match ($event->type) {
                'farrier' => 'farrier', 'veterinary' => 'veterinary', default => null
            };
            $source = $event->source_type === (new CareRecord)->getMorphClass() ? CareRecord::find($event->source_id) : null;
            $record->fill([
                'performed_at' => $event->starts_at->isFuture() ? now() : $event->starts_at,
                'professional_id' => $event->professional_id, 'reason' => $event->title,
                'care_category_id' => $source?->care_category_id ?? ($key ? CareCategory::visibleTo($horse->organization_id)->where('key', $key)->value('id') : null),
            ]);
        }

        return view('health.form', ['event' => $event] + $this->formData($horse, $record));
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
            // Informations financières : dépenses rattachées au soin, si l'utilisateur voit le budget de ce cheval.
            'expenses' => $this->horseCan($horse, Perm::EXPENSES_VIEW)
                ? Expense::where('care_record_id', $care->id)->with(['category', 'documents', 'professional'])->orderBy('spent_on')->get()
                : collect(),
            'canEditExpense' => fn ($e) => $this->horseCan($horse, Perm::EXPENSES_CREATE) && ($e->author_id === request()->user()->id || $this->horseCan($horse, Perm::HORSE_MANAGE)),
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

    public function destroy(Request $request, Horse $horse, CareRecord $care)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $care->delete();
        $this->calendar->syncFor($care, $horse, 'care', '', null);
        Audit::log('care.deleted', $care, [], $horse->organization_id);
        // Suppression d'un glissé dans la liste (téléphone) : réponse JSON avec l'adresse d'annulation.
        if ($request->expectsJson()) {
            return response()->json(['deleted' => true, 'restore_url' => route('horses.care.restore', [$horse, $care])]);
        }

        return redirect()->route('horses.care.index', $horse)->with('success', 'Soin supprimé.');
    }

    /** « Annuler » après une suppression : le soin revient, avec son rendez-vous et son contrôle prévu dans l'agenda. */
    public function restore(Request $request, Horse $horse, CareRecord $care)
    {
        abort_unless($care->trashed(), 404);
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $care->restore();
        Audit::log('care.restored', $care, [], $horse->organization_id);

        return $request->expectsJson()
            ? response()->json(['restored' => true])
            : redirect()->route('horses.care.show', [$horse, $care])->with('success', 'Soin restauré.');
    }

    private function afterSave(Request $request, Horse $horse, CareRecord $record): void
    {
        $record->loadMissing('category');
        // Fréquence du prochain passage : retenue pour ce cheval si demandé (rappel activé).
        if ($request->filled('next_interval_value') && $request->boolean('remember_frequency')) {
            $this->reminders->save($horse, $record->care_category_id, [
                'enabled' => true, 'interval_value' => (int) $request->input('next_interval_value'), 'interval_unit' => $request->input('next_interval_unit'),
                'notice_days' => $this->reminders->ruleFor($horse, $record->care_category_id)['notice_days'] ?? 7,
            ]);
        }
        $this->reminders->closePrevious($record, $this->calendar);
        if ($request->integer('calendar_event_id')) {
            $planned = CalendarEvent::where('horse_id', $horse->id)->where(fn ($q) => $q->whereNull('source_type')->orWhere('source_type', (new CareRecord)->getMorphClass()))
                ->whereKey($request->integer('calendar_event_id'))->first();
            if ($planned) {
                app(CareCalendarSync::class)->linkValidated($planned, $record);
            }
        }
        // Le contrôle prévu suit le soin dans l'agenda (CareCalendarSync, à chaque enregistrement).

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
        return CareCalendarSync::eventTypeFor($categoryKey);
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
            'next_mode' => ['nullable', Rule::in(['interval', 'date', 'none'])],
            'next_interval_value' => ['nullable', 'integer', 'min:1', 'max:120'],
            'next_interval_unit' => ['nullable', 'required_with:next_interval_value', Rule::in(['weeks', 'months'])],
            'remember_frequency' => ['boolean'],
            'calendar_event_id' => ['nullable', 'integer'],
            'attachment' => ['nullable', 'file', 'max:'.config('equine.uploads.max_kb'), 'mimes:'.implode(',', config('equine.uploads.mimes'))],
        ]);
        $mode = $data['next_mode'] ?? (isset($data['next_interval_value']) ? 'interval' : 'date');
        if ($mode === 'interval' && ! empty($data['next_interval_value'])) {
            $data['next_check_on'] = CareReminders::addInterval(Carbon::parse($data['performed_at']), (int) $data['next_interval_value'], $data['next_interval_unit'])->toDateString();
        } elseif ($mode === 'none') {
            $data['next_check_on'] = null;
        }
        if (! empty($data['next_check_on'])) {
            $data['next_check_done_at'] = null;
        }
        unset($data['attachment'], $data['next_mode'], $data['next_interval_value'], $data['next_interval_unit'], $data['remember_frequency'], $data['calendar_event_id']);

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
            'rules' => $this->reminders->rulesFor($horse)->map(fn ($r) => ['enabled' => $r['enabled'], 'value' => $r['interval_value'], 'unit' => $r['interval_unit']]),
            'event' => null,
            'linkedExpense' => $record->exists ? Expense::where('care_record_id', $record->id)->first() : null,
            'defaultMode' => $this->defaultNextMode($horse, $record),
        ];
    }

    /**
     * Prochain passage proposé : « dans un délai » pour un nouveau soin, ou pour un soin dont le contrôle
     * correspond à la fréquence du rappel ; « à une date précise » seulement pour une date choisie à la main.
     */
    private function defaultNextMode(Horse $horse, CareRecord $record): string
    {
        if (! $record->exists) {
            return 'interval';
        }
        if (! $record->next_check_on) {
            return 'none';
        }
        $rule = $record->care_category_id ? $this->reminders->ruleFor($horse, $record->care_category_id) : null;
        if ($rule && $rule['enabled'] && $record->performed_at
            && CareReminders::addInterval($record->performed_at, $rule['interval_value'], $rule['interval_unit'])->isSameDay($record->next_check_on)) {
            return 'interval';
        }

        return 'date';
    }

    private function assignableUsers(Horse $horse)
    {
        return $horse->organization->users()->orderBy('name')->pluck('name', 'users.id');
    }
}
