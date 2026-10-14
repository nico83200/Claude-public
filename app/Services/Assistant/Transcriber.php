<?php

namespace App\Services\Assistant;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Transcription de la voix par ElevenLabs (Speech-to-Text « Scribe »).
 * L'audio est transmis puis oublié : il n'est jamais écrit sur le disque de l'application.
 */
class Transcriber
{
    public function configured(): bool
    {
        return filled(config('services.elevenlabs.key'));
    }

    public function transcribe(UploadedFile $audio): string
    {
        return $this->transcribeBytes($audio->get(), 'consigne.'.($audio->guessExtension() ?: 'webm'));
    }

    public function transcribeBytes(string $bytes, string $filename): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('Transcription non configurée (clé ElevenLabs manquante).');
        }
        $response = Http::withHeaders(['xi-api-key' => config('services.elevenlabs.key')])
            ->timeout(45)->retry(2, 500, fn ($e) => ! $e instanceof \Illuminate\Http\Client\RequestException || $e->response->serverError(), throw: false)
            ->attach('file', $bytes, $filename)
            ->post(rtrim(config('services.elevenlabs.base_url', 'https://api.elevenlabs.io'), '/').'/v1/speech-to-text', [
                'model_id' => config('services.elevenlabs.stt_model', 'scribe_v2'),
                'language_code' => 'fra',
                'tag_audio_events' => 'false',
            ]);
        if (! $response->successful()) {
            $message = self::explain($response->status(), (string) $response->body());
            if (self::creditsExhausted($response->status(), (string) $response->body())) {
                throw new VoiceCreditsExhausted($message);
            }
            throw new RuntimeException($message);
        }

        return trim((string) $response->json('text', ''));
    }

    /** Erreur due à un solde ElevenLabs insuffisant (crédits ou quota épuisés). */
    public static function creditsExhausted(int $status, string $body): bool
    {
        $json = json_decode($body, true);
        $detail = is_array($json['detail'] ?? null) ? $json['detail'] : ['message' => is_string($json['detail'] ?? null) ? $json['detail'] : null];
        $code = (string) ($detail['status'] ?? $detail['code'] ?? '');
        $message = mb_strtolower((string) ($detail['message'] ?? mb_substr($body, 0, 200)));

        return in_array($code, ['quota_exceeded', 'insufficient_credits', 'insufficient_quota', 'payment_required'], true) || $status === 402
            || str_contains($message, 'credit') || str_contains($message, 'quota');
    }

    /** Message clair à partir de la réponse d'erreur d'ElevenLabs (pour le super-administrateur et le journal). */
    public static function explain(int $status, string $body): string
    {
        $json = json_decode($body, true);
        $detail = is_array($json['detail'] ?? null) ? $json['detail'] : ['message' => is_string($json['detail'] ?? null) ? $json['detail'] : null];
        $code = (string) ($detail['status'] ?? $detail['code'] ?? '');
        $message = (string) ($detail['message'] ?? mb_substr($body, 0, 200));
        $hint = match (true) {
            $code === 'missing_permissions' || str_contains($message, 'permission') => 'la clé ElevenLabs n\'a pas la permission « Speech to Text » : dans ElevenLabs › Developers › API Keys, modifiez la clé et autorisez « Speech to Text »',
            self::creditsExhausted($status, $body) => 'crédits ElevenLabs épuisés : rechargez ou changez d\'abonnement chez ElevenLabs',
            $code === 'invalid_api_key' || ($status === 401 && $code === '') => 'clé ElevenLabs refusée : vérifiez-la dans Administration › Assistant IA',
            $status === 429 => 'trop de demandes simultanées chez ElevenLabs, réessayez dans un instant',
            $status === 422 || $status === 400 => 'demande refusée par ElevenLabs (format audio ou modèle)',
            $status >= 500 => 'service ElevenLabs momentanément indisponible',
            default => 'erreur ElevenLabs',
        };

        return 'Transcription : '.$hint.' (HTTP '.$status.($code ? ', '.$code : '').($message ? ' : '.mb_substr($message, 0, 200) : '').').';
    }

    /** Une seconde de son (WAV) pour vérifier la transcription depuis l'administration. */
    public static function sampleWav(): string
    {
        $rate = 16000;
        $samples = '';
        for ($i = 0; $i < $rate; $i++) {
            $samples .= pack('v', (int) (3000 * sin(2 * M_PI * 440 * $i / $rate)) & 0xFFFF);
        }

        return 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16).'data'.pack('V', strlen($samples)).$samples;
    }
}
