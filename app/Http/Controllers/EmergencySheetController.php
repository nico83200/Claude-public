<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\HorsePhoto;
use App\Services\Audit;
use App\Support\Perm;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fiche d'urgence du cheval : une page sans connexion, ouverte par le QR code collé sur le box,
 * qui ne montre que l'essentiel en cas de problème (contacts, traitements, précautions, identification).
 * Désactivée par défaut ; le propriétaire peut changer le lien à tout moment (l'ancien QR ne marche plus).
 */
class EmergencySheetController extends Controller
{
    public function edit(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_EDIT);

        return view('emergency.edit', ['horse' => $horse, 'sheet' => $this->sheet($horse), 'owner' => request()->user()->loadMissing('profile')]);
    }

    public function update(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_EDIT);
        $data = $request->validate([
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +().-]{6,40}$/'],
            'emergency_notes' => ['nullable', 'string', 'max:1000'],
            'action' => ['nullable', 'in:enable,disable,renew'],
        ], ['emergency_contact_phone.regex' => 'Numéro de téléphone invalide.']);
        $horse->fill(collect($data)->except('action')->all());
        $action = $data['action'] ?? null;
        if ($action === 'enable' && ! $horse->emergency_token || $action === 'renew') {
            $horse->emergency_token = Str::random(40);
        } elseif ($action === 'disable') {
            $horse->emergency_token = null;
        }
        $horse->save();
        if ($action) {
            Audit::log('horse.emergency_sheet_'.$action, $horse, [], $horse->organization_id);
        }

        return back()->with('success', match ($action) {
            'enable' => 'Fiche d\'urgence activée : imprimez le QR code et collez-le sur le box.',
            'disable' => 'Fiche d\'urgence désactivée : le QR code ne mène plus à rien.',
            'renew' => 'Nouveau lien créé : l\'ancien QR code ne fonctionne plus, imprimez le nouveau.',
            default => 'Fiche d\'urgence enregistrée.',
        });
    }

    /** Fiche A5 à imprimer : informations du jour + QR code vers la version toujours à jour. */
    public function print(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_EDIT);
        abort_unless($horse->emergency_token, 404);

        return view('emergency.print', ['horse' => $horse, 'sheet' => $this->sheet($horse), 'qr' => $this->qrSvg(route('emergency.public', $horse->emergency_token))]);
    }

    public function qr(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_EDIT);
        abort_unless($horse->emergency_token, 404);

        return response($this->qrSvg(route('emergency.public', $horse->emergency_token)), 200, [
            'Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'attachment; filename="urgence-'.Str::slug($horse->shortName()).'.svg"',
        ]);
    }

    /** Page publique (QR code) : pas de connexion, jamais indexée. */
    public function show(string $token)
    {
        $horse = Horse::where('emergency_token', $token)->whereNull('archived_at')->firstOrFail();

        return response()->view('emergency.public', ['horse' => $horse, 'sheet' => $this->sheet($horse), 'token' => $token])
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'private, no-store');
    }

    public function photo(string $token)
    {
        $horse = Horse::where('emergency_token', $token)->whereNull('archived_at')->firstOrFail();
        $photo = $horse->main_photo_path ? HorsePhoto::where('horse_id', $horse->id)->find($horse->main_photo_path) : null;
        $path = $photo?->thumb_path ?: $photo?->path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=3600', 'X-Robots-Tag' => 'noindex']);
    }

    /** Contenu de la fiche : uniquement ce qui sert en urgence. */
    private function sheet(Horse $horse): array
    {
        $horse->loadMissing(['breed', 'identifiers', 'professionals']);
        $pros = $horse->professionals->sortByDesc(fn ($p) => (int) $p->pivot->is_primary)
            ->filter(fn ($p) => ! $p->archived_at && in_array($p->kind, ['veterinarian', 'farrier', 'osteopath', 'dentist'], true))
            ->unique('kind')->values();

        return [
            'identity' => array_filter([
                'Sexe' => $horse->sex !== 'unknown' ? Horse::SEXES[$horse->sex] ?? null : null,
                'Âge' => $horse->age() ? $horse->age().' ans' : null,
                'Robe' => $horse->coat,
                'Race' => $horse->breed?->name,
                'Transpondeur' => $horse->identifier('transponder'),
                'SIRE' => $horse->identifier('sire'),
                'Lieu' => $horse->current_location,
            ]),
            'treatments' => $horse->treatments()->where('status', 'active')->where('starts_on', '<=', now()->toDateString())
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString()))->orderBy('starts_on')->get(),
            'professionals' => $pros,
            'precautions' => $horse->precautions,
            'notes' => $horse->emergency_notes,
            'contact' => ['name' => $horse->emergency_contact_name, 'phone' => $horse->emergency_contact_phone],
            'has_photo' => (bool) $horse->main_photo_path,
        ];
    }

    private function qrSvg(string $link): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(600, 2), new SvgImageBackEnd)))->writeString($link);
    }
}
