<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationSetting;
use App\Models\AssistantRequest;
use App\Services\Assistant\AssistantConfig;
use App\Services\Assistant\AssistantCredits;
use App\Services\Assistant\ClaudeGateway;
use App\Services\Assistant\Speaker;
use App\Services\Assistant\Transcriber;
use App\Services\Audit;
use App\Services\IntegrationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/** Réglages de l'assistant vocal : clés, modèle, consignes libres pour l'IA, suivi de l'usage. */
class AssistantSettingsController extends Controller
{
    public function edit(AssistantConfig $config, IntegrationSettings $settings)
    {
        $month = AssistantRequest::where('created_at', '>=', now()->startOfMonth());

        return view('admin.assistant', [
            'config' => $config,
            'masked' => ['anthropic.key' => $settings->masked('anthropic.key'), 'elevenlabs.key' => $settings->masked('elevenlabs.key')],
            'configured' => ['anthropic' => filled(config('services.anthropic.key')), 'elevenlabs' => filled(config('services.elevenlabs.key'))],
            'stats' => [
                'requests' => (clone $month)->count(),
                'errors' => (clone $month)->where('status', 'error')->count(),
                'voice' => (clone $month)->where('input_mode', 'voice')->count(),
                'input_tokens' => (int) (clone $month)->sum('input_tokens'),
                'output_tokens' => (int) (clone $month)->sum('output_tokens'),
                'avg_ms' => (int) (clone $month)->where('status', '!=', 'error')->avg('duration_ms'),
                'undone' => DB::table('assistant_actions')->whereNotNull('undone_at')->where('created_at', '>=', now()->startOfMonth())->count(),
                'actions' => DB::table('assistant_actions')->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'recentErrors' => AssistantRequest::with('user')->where('status', 'error')->latest()->limit(8)->get(),
        ]);
    }

    public function update(Request $request, IntegrationSettings $settings)
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'model' => ['required', Rule::in(array_keys(AssistantConfig::MODELS))],
            'effort' => ['required', Rule::in(array_keys(AssistantConfig::EFFORTS))],
            'session_min' => ['required', 'integer', 'min:10', 'max:240'],
            'session_max' => ['required', 'integer', 'min:10', 'max:240', 'gte:session_min'],
            'result_seconds' => ['sometimes', 'integer', Rule::in(array_keys(AssistantConfig::RESULT_DELAYS))],
            'instructions' => ['nullable', 'string', 'max:8000'],
            'voice_id' => ['nullable', 'regex:/^[A-Za-z0-9]{10,40}$/'],
            'speech_model' => ['nullable', Rule::in(array_keys(Speaker::MODELS))],
            'anthropic_key' => ['nullable', 'string', 'max:255', 'regex:/^sk-ant-/'],
            'elevenlabs_key' => ['nullable', 'string', 'max:255'],
            'packs' => ['array', 'max:3'],
            'packs.*.credits' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'packs.*.price' => ['nullable', 'numeric', 'min:0.5', 'max:1000'],
            'clear' => ['array'], 'clear.*' => [Rule::in(['anthropic.key', 'elevenlabs.key'])],
        ], ['voice_id.regex' => 'Identifiant de voix ElevenLabs invalide (lettres et chiffres).', 'anthropic_key.regex' => 'La clé Anthropic commence par sk-ant-.', 'session_max.gte' => 'La durée maximale doit être supérieure ou égale à la durée minimale.']);

        ApplicationSetting::put('assistant.enabled', $request->boolean('enabled'));
        foreach (array_intersect_key($data, array_flip(['model', 'effort', 'session_min', 'session_max', 'result_seconds'])) as $k => $v) {
            ApplicationSetting::put('assistant.'.$k, $v);
        }
        ApplicationSetting::put('assistant.instructions', $data['instructions'] ?? null);
        ApplicationSetting::put('assistant.speech_enabled', $request->boolean('speech_enabled'));
        ApplicationSetting::put('assistant.voice_id', $data['voice_id'] ?? null);
        ApplicationSetting::put('assistant.speech_model', $data['speech_model'] ?? null);
        ApplicationSetting::put('digest_ai', $request->boolean('digest_ai'));
        if ($request->has('packs')) {
            ApplicationSetting::put(AssistantCredits::ENABLED_KEY, $request->boolean('credits_enabled'));
            ApplicationSetting::put(AssistantCredits::PACKS_KEY, collect($data['packs'] ?? [])
                ->filter(fn ($p) => filled($p['credits'] ?? null) && filled($p['price'] ?? null))
                ->map(fn ($p) => ['credits' => (int) $p['credits'], 'price_cents' => (int) round((float) $p['price'] * 100)])->values()->all());
        }
        $settings->save(['anthropic.key' => $data['anthropic_key'] ?? null, 'elevenlabs.key' => $data['elevenlabs_key'] ?? null], $data['clear'] ?? []);
        Audit::log('admin.assistant_settings_updated', null, ['model' => $data['model'], 'effort' => $data['effort'], 'keys_changed' => array_keys(array_filter(['anthropic' => $data['anthropic_key'] ?? null, 'elevenlabs' => $data['elevenlabs_key'] ?? null]))]);

        return back()->with('success', 'Réglages de l\'assistant enregistrés.');
    }

    public function test(Request $request, ClaudeGateway $claude, Transcriber $transcriber)
    {
        $which = $request->validate(['service' => ['required', Rule::in(['anthropic', 'elevenlabs'])]])['service'];
        try {
            if ($which === 'anthropic') {
                $r = $claude->send([['type' => 'text', 'text' => 'Réponds uniquement « OK ».']], [['role' => 'user', 'content' => 'Test de connexion']], []);

                return back()->with('success', 'Claude répond ('.$r['model'].') : '.trim(implode(' ', $r['texts'])));
            }
            abort_unless(filled(config('services.elevenlabs.key')), back()->with('error', 'Aucune clé ElevenLabs configurée.'));
            // Vrai essai de transcription (1 seconde de son) : vérifie la clé, sa permission « Speech to Text » et les crédits.
            $transcriber->transcribeBytes(Transcriber::sampleWav(), 'essai.wav');

            return back()->with('success', 'Transcription ElevenLabs opérationnelle (modèle '.config('services.elevenlabs.stt_model', 'scribe_v2').').');
        } catch (Throwable $e) {
            return back()->with('error', 'Échec du test : '.mb_substr($e->getMessage(), 0, 300));
        }
    }

    /** Essai de la voix (phrase courte) : renvoie le son ou l'erreur expliquée. */
    public function voiceTest(Speaker $speaker)
    {
        try {
            return response($speaker->speak('Bonjour, je suis l\'assistant de Jack et Cie. C\'est noté : maréchal pour Uni mardi à neuf heures.'), 200, ['Content-Type' => 'audio/mpeg']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
