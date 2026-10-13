<?php

namespace App\Http\Controllers;

use App\Models\AssistantRequest;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Horse;
use App\Models\Professional;
use App\Models\Treatment;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\AssistantTools;
use App\Services\Assistant\ClaudeGateway;
use App\Services\Audit;
use App\Services\CalendarService;
use App\Services\CareReminders;
use App\Support\AiImage;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ordonnance ou compte rendu vétérinaire photographié, lu par l'assistant (Claude) :
 * soin, traitements prescrits et prochain contrôle sont proposés dans un formulaire à vérifier.
 * Rien n'est enregistré sans validation ; la posologie est recopiée telle qu'écrite, jamais calculée.
 */
class PrescriptionController extends Controller
{
    private const TTL_MINUTES = 120;

    public function read(Request $request, Horse $horse, AssistantService $assistant, ClaudeGateway $claude)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $request->validate(['prescription' => ['required', 'file', 'max:'.config('equine.uploads.max_kb'), 'mimes:jpg,jpeg,png,webp,gif,pdf,heic,heif']],
            ['prescription.mimes' => 'Envoyez une photo (JPEG, PNG) ou un PDF.']);
        $org = $horse->organization;
        $this->requireFeature($org, 'assistant', 'Assistant vocal (IA)');
        if ($reason = $assistant->unavailableReason($org)) {
            return back()->with('error', $reason);
        }
        $file = $request->file('prescription');
        [$bytes, $mime] = AiImage::prepare($file->getRealPath(), (string) $file->getMimeType());
        if (! $bytes) {
            return back()->with('error', 'Format de photo non lisible : sur iPhone, réglez Appareil photo › Formats sur « Le plus compatible », ou envoyez une capture d\'écran.');
        }
        $started = microtime(true);
        try {
            $result = $claude->readPrescription($bytes, $mime, [$horse->shortName(), $horse->official_name], AssistantTools::CARE_KEYS);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $d = $result['data'];
        AssistantRequest::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'organization_id' => $org->id, 'conversation_id' => (string) Str::uuid(),
            'input_mode' => 'document', 'input_text' => 'Lecture d\'ordonnance : '.Str::limit($file->getClientOriginalName(), 120),
            'reply' => Str::limit(trim(($d['reason'] ?? '').' · '.count($d['treatments'] ?? []).' traitement(s)'), 500), 'status' => ($d['is_prescription'] ?? false) ? 'done' : 'clarify',
            'model' => $result['model'], 'input_tokens' => $result['usage']['input'], 'output_tokens' => $result['usage']['output'], 'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);
        if (! ($d['is_prescription'] ?? false)) {
            return back()->with('warning', 'Ce document ne ressemble pas à une ordonnance ou un compte rendu. Essayez une photo plus nette, à plat et bien éclairée.');
        }
        $token = Str::random(32);
        $path = $file->storeAs('tmp/prescriptions', $token.'.'.$file->extension(), 'local');
        Cache::put('prescription:'.$token, [
            'user_id' => $request->user()->id, 'horse_id' => $horse->id, 'path' => $path, 'name' => Str::limit($file->getClientOriginalName(), 250, ''), 'data' => $d,
        ], now()->addMinutes(self::TTL_MINUTES));

        return redirect()->route('horses.prescription.review', [$horse, $token]);
    }

    public function review(Request $request, Horse $horse, string $token)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $pending = $this->pending($request, $horse, $token);
        $d = $pending['data'];
        $professionals = Professional::where('organization_id', $horse->organization_id)->whereNull('archived_at')->orderBy('last_name')->get();
        $practitioner = mb_strtolower((string) $d['practitioner']);
        $guess = $practitioner !== '' ? $professionals->first(fn ($p) => Str::contains($practitioner, mb_strtolower($p->last_name)) || ($p->company && Str::contains($practitioner, mb_strtolower($p->company)))) : null;
        $date = rescue(fn () => Carbon::parse($d['visit_date']), null, false);
        $date = $date && ! $date->isFuture() ? $date : today();
        $next = rescue(fn () => $d['next_check_on'] ? Carbon::parse($d['next_check_on']) : null, null, false)
            ?? ((int) $d['next_check_days'] > 0 ? $date->copy()->addDays((int) $d['next_check_days']) : null);
        $named = trim((string) $d['horse_name']);

        return view('health.prescription', [
            'horse' => $horse, 'token' => $token, 'd' => $d,
            'otherHorse' => $named !== '' && ! in_array(mb_strtolower($named), [mb_strtolower($horse->shortName()), mb_strtolower($horse->official_name)], true) ? $named : null,
            'categories' => CareCategory::visibleTo($horse->organization_id)->orderBy('name')->pluck('name', 'id'),
            'categoryId' => CareCategory::where('key', $d['care_category'])->value('id') ?? CareCategory::where('key', 'veterinary')->value('id'),
            'professionals' => $professionals->mapWithKeys(fn ($p) => [$p->id => $p->fullName().' – '.$p->kindLabel()]),
            'professionalId' => $guess?->id,
            'date' => $date->toDateString(),
            'nextCheck' => $next?->toDateString(),
            'treatments' => collect($d['treatments'] ?? [])->filter(fn ($t) => trim((string) $t['product']) !== '')->values(),
        ]);
    }

    public function save(Request $request, Horse $horse, string $token, CareReminders $reminders, CalendarService $calendar)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $pending = $this->pending($request, $horse, $token);
        $data = $request->validate([
            'care_category_id' => ['required', \Illuminate\Validation\Rule::exists('care_categories', 'id')->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $horse->organization_id))],
            'professional_id' => ['nullable', \Illuminate\Validation\Rule::exists('professionals', 'id')->where('organization_id', $horse->organization_id)],
            'performed_at' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:255'],
            'diagnosis' => ['nullable', 'string', 'max:10000'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'next_check_on' => ['nullable', 'date', 'after:performed_at'],
            'keep_document' => ['nullable', 'boolean'],
            'treatments' => ['array', 'max:15'],
            'treatments.*.create' => ['nullable', 'boolean'],
            'treatments.*.product' => ['nullable', 'required_if_accepted:treatments.*.create', 'string', 'max:150'],
            'treatments.*.dosage' => ['nullable', 'string', 'max:255'],
            'treatments.*.frequency' => ['nullable', 'string', 'max:255'],
            'treatments.*.times_per_day' => ['nullable', 'integer', 'min:1', 'max:24'],
            'treatments.*.starts_on' => ['nullable', 'date'],
            'treatments.*.ends_on' => ['nullable', 'date'],
            'treatments.*.instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        [$record, $count] = DB::transaction(function () use ($request, $horse, $data, $pending) {
            $record = new CareRecord(collect($data)->only(['care_category_id', 'professional_id', 'performed_at', 'reason', 'diagnosis', 'instructions', 'next_check_on'])->all());
            $record->horse_id = $horse->id;
            $record->author_id = $request->user()->id;
            $record->save();
            $count = 0;
            foreach ($data['treatments'] ?? [] as $t) {
                if (empty($t['create']) || trim((string) ($t['product'] ?? '')) === '') {
                    continue;
                }
                $starts = $t['starts_on'] ?? null ?: Carbon::parse($data['performed_at'])->toDateString();
                $ends = ! empty($t['ends_on']) && $t['ends_on'] >= $starts ? $t['ends_on'] : null;
                $treatment = new Treatment(['product' => $t['product'], 'dosage' => $t['dosage'] ?? null, 'frequency' => $t['frequency'] ?? null,
                    'times_per_day' => $t['times_per_day'] ?? null, 'starts_on' => $starts, 'ends_on' => $ends, 'instructions' => $t['instructions'] ?? null, 'care_record_id' => $record->id]);
                $treatment->horse_id = $horse->id;
                $treatment->author_id = $request->user()->id;
                $treatment->status = $ends && $ends < today()->toDateString() ? 'completed' : 'active';
                $treatment->save();
                $count++;
            }
            if ($request->boolean('keep_document', true) && Storage::disk('local')->exists($pending['path']) && $this->horseCan($horse, Perm::DOCUMENTS_CREATE)) {
                $file = new \Illuminate\Http\UploadedFile(Storage::disk('local')->path($pending['path']), $pending['name'], null, null, true);
                HorseDocumentController::storeFor($horse, $file, ['category' => 'prescription', 'title' => 'Ordonnance du '.Carbon::parse($data['performed_at'])->format('d/m/Y'), 'is_sensitive' => true], $request->user()->id, $record);
            }

            return [$record, $count];
        });
        $reminders->closePrevious($record->load('category'), $calendar);
        Storage::disk('local')->delete($pending['path']);
        Cache::forget('prescription:'.$token);
        Audit::log('care.created_from_prescription', $record, ['treatments' => $count], $horse->organization_id);

        return redirect()->route('horses.care.show', [$horse, $record])
            ->with('success', 'Soin enregistré'.($count ? ' avec '.$count.' traitement'.($count > 1 ? 's' : '').' (prises et fin de traitement dans l\'agenda)' : '').'.');
    }

    public function cancel(Request $request, Horse $horse, string $token)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $pending = $this->pending($request, $horse, $token);
        Storage::disk('local')->delete($pending['path']);
        Cache::forget('prescription:'.$token);

        return redirect()->route('horses.care.index', $horse)->with('success', 'Lecture abandonnée : rien n\'a été enregistré.');
    }

    private function pending(Request $request, Horse $horse, string $token): array
    {
        $pending = Cache::get('prescription:'.$token);
        abort_unless($pending && $pending['user_id'] === $request->user()->id && $pending['horse_id'] === $horse->id, 404, 'Lecture expirée : envoyez à nouveau l\'ordonnance.');

        return $pending;
    }
}
