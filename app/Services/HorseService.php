<?php

namespace App\Services;

use App\Models\Horse;
use App\Models\HorseIdentifier;
use App\Models\HorseOwnership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HorseService
{
    public function __construct(private Entitlements $entitlements) {}

    public static function rules(): array
    {
        return [
            'official_name' => ['required', 'string', 'max:150'],
            'usual_name' => ['nullable', 'string', 'max:100'],
            'horse_breed_id' => ['nullable', 'integer', 'exists:horse_breeds,id'],
            'sex' => ['required', Rule::in(array_keys(Horse::SEXES))],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'birth_year' => ['nullable', 'integer', 'min:1950', 'max:'.now()->year],
            'coat' => ['nullable', 'string', 'max:80'],
            'height_cm' => ['nullable', 'integer', 'min:50', 'max:220'],
            'birth_country' => ['nullable', 'string', 'size:2'],
            'breeder' => ['nullable', 'string', 'max:150'],
            'main_discipline' => ['nullable', Rule::in(array_keys(Horse::DISCIPLINES))],
            'work_level' => ['nullable', 'string', 'max:60'],
            'particularities' => ['nullable', 'string', 'max:5000'],
            'general_notes' => ['nullable', 'string', 'max:10000'],
            'care_instructions' => ['nullable', 'string', 'max:5000'],
            'precautions' => ['nullable', 'string', 'max:5000'],
            'current_location' => ['nullable', 'string', 'max:150'],
            'identifiers' => ['array'],
            'identifiers.sire' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z]{6,12}$/'],
            'identifiers.ueln' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z]{15}$/'],
            'identifiers.transponder' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{15}$/'],
            'identifiers.passport' => ['nullable', 'string', 'max:40'],
            'identifiers.fei' => ['nullable', 'string', 'max:20'],
        ];
    }

    public static function messages(): array
    {
        return [
            'identifiers.sire.regex' => 'Le numéro SIRE comporte 8 chiffres suivis d\'une lettre (ex. 12345678A).',
            'identifiers.ueln.regex' => 'L\'UELN comporte 15 caractères alphanumériques.',
            'identifiers.transponder.regex' => 'Le numéro de transpondeur comporte 15 chiffres.',
        ];
    }

    public function assertCanAdd(Organization $org): void
    {
        $count = $org->horses()->whereNull('archived_at')->count();
        if (! $this->entitlements->withinLimit($org, 'max_horses', $count)) {
            throw ValidationException::withMessages(['official_name' => 'Le nombre maximal de chevaux de l\'offre ('.$this->entitlements->limit($org, 'max_horses').') est atteint. Archivez un cheval ou changez d\'offre.']);
        }
    }

    public function create(Organization $org, User $user, array $data, bool $userIsOwner): Horse
    {
        $this->assertCanAdd($org);

        return DB::transaction(function () use ($org, $user, $data, $userIsOwner) {
            $identifiers = $data['identifiers'] ?? [];
            unset($data['identifiers']);
            $horse = new Horse($data);
            $horse->organization_id = $org->id;
            $horse->created_by = $user->id;
            $horse->save();
            $this->syncIdentifiers($horse, $identifiers);
            if ($userIsOwner) {
                HorseOwnership::create(['horse_id' => $horse->id, 'user_id' => $user->id, 'role' => 'owner', 'started_on' => now()->toDateString()]);
            }
            Audit::log('horse.created', $horse, ['name' => $horse->official_name], $org->id);

            return $horse;
        });
    }

    public function update(Horse $horse, array $data): Horse
    {
        DB::transaction(function () use ($horse, $data) {
            $identifiers = $data['identifiers'] ?? null;
            unset($data['identifiers']);
            $horse->update($data);
            if ($identifiers !== null) {
                $this->syncIdentifiers($horse, $identifiers);
            }
        });

        return $horse;
    }

    public function syncIdentifiers(Horse $horse, array $identifiers): void
    {
        foreach (array_keys(HorseIdentifier::TYPES) as $type) {
            if (! array_key_exists($type, $identifiers)) {
                continue;
            }
            $value = trim((string) $identifiers[$type]);
            if ($value === '') {
                HorseIdentifier::where('horse_id', $horse->id)->where('type', $type)->delete();
            } else {
                HorseIdentifier::updateOrCreate(['horse_id' => $horse->id, 'type' => $type], ['value' => strtoupper($value)]);
            }
        }
    }
}
