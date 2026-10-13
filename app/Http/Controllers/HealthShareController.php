<?php

namespace App\Http\Controllers;

use App\Models\AssistantRequest;
use App\Models\HealthShare;
use App\Models\Horse;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\ClaudeGateway;
use App\Services\Audit;
use App\Services\VetSummary;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Résumé de santé pour le vétérinaire : aperçu dans le dossier, puis lien temporaire
 * (24 h, 7 ou 30 jours) consultable sans compte. Synthèse rédigée par l'assistant en option.
 */
class HealthShareController extends Controller
{
    public function index(Request $request, Horse $horse, VetSummary $summary, AssistantService $assistant)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_VIEW);

        return view('health.vet-summary', [
            'horse' => $horse,
            's' => $summary->build($horse),
            'canShare' => $this->horseCan($horse, Perm::HORSE_MANAGE),
            'shares' => HealthShare::with('author')->where('horse_id', $horse->id)->latest()->limit(10)->get(),
            'aiAvailable' => $assistant->unavailableReason($horse->organization) === null,
        ]);
    }

    public function store(Request $request, Horse $horse, VetSummary $summary, AssistantService $assistant, ClaudeGateway $claude)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $data = $request->validate([
            'days' => ['required', 'in:'.implode(',', array_keys(HealthShare::DURATIONS))],
            'recipient' => ['nullable', 'string', 'max:120'],
            'with_ai' => ['nullable', 'boolean'],
        ]);
        $ai = null;
        if ($request->boolean('with_ai') && $assistant->unavailableReason($horse->organization) === null) {
            $started = microtime(true);
            try {
                $result = $claude->write(
                    'Tu rédiges, pour un vétérinaire équin, une synthèse factuelle du dossier de santé d\'un cheval tenu par son propriétaire. '
                    .'Français, 5 à 8 lignes, phrases courtes, sans mise en forme. Cite les faits marquants avec leurs dates (soins, traitements, évolution du poids, observations, anomalies en séance). '
                    .'N\'interprète pas, ne pose aucun diagnostic, ne donne aucun conseil ; si une donnée manque, ne l\'invente pas.',
                    $summary->text($summary->build($horse)), 700);
                $ai = $result['text'];
                AssistantRequest::create([
                    'uuid' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'organization_id' => $horse->organization_id, 'conversation_id' => (string) Str::uuid(),
                    'input_mode' => 'text', 'input_text' => 'Synthèse pour le vétérinaire : '.$horse->shortName(), 'reply' => Str::limit($ai, 500), 'status' => 'done',
                    'model' => $result['model'], 'input_tokens' => $result['usage']['input'], 'output_tokens' => $result['usage']['output'], 'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                ]);
            } catch (RuntimeException $e) {
                session()->flash('warning', 'Lien créé sans synthèse : '.$e->getMessage());
            }
        }
        $share = new HealthShare(['horse_id' => $horse->id, 'created_by' => $request->user()->id, 'recipient' => $data['recipient'] ?? null,
            'months' => 6, 'ai_summary' => $ai, 'expires_at' => now()->addDays((int) $data['days'])]);
        $share->token = Str::random(40);
        $share->save();
        Audit::log('horse.health_share_created', $share, ['days' => (int) $data['days'], 'ai' => (bool) $ai], $horse->organization_id);

        return redirect()->route('horses.vet-summary', $horse)->with('share', $share->id)
            ->with('success', 'Lien créé, valable '.HealthShare::DURATIONS[(int) $data['days']].'. Copiez-le ou partagez-le avec le vétérinaire.');
    }

    public function revoke(Horse $horse, HealthShare $share)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        abort_unless($share->horse_id === $horse->id, 404);
        $share->update(['revoked_at' => now()]);

        return back()->with('success', 'Lien désactivé : il ne permet plus de consulter le résumé.');
    }

    /** Page publique : sans compte, jamais indexée, uniquement tant que le lien est valable. */
    public function show(string $token, VetSummary $summary)
    {
        $share = HealthShare::with('horse')->where('token', $token)->first();
        if (! $share || ! $share->isActive() || ! $share->horse || $share->horse->trashed()) {
            return response()->view('health.vet-summary-expired', [], 410)->header('X-Robots-Tag', 'noindex, nofollow');
        }
        $share->increment('views', 1, ['last_viewed_at' => now()]);

        return response()->view('health.vet-summary-public', ['share' => $share, 's' => $summary->build($share->horse, $share->months)])
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'private, no-store');
    }
}
