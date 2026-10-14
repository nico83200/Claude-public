<?php

namespace App\Services\Assistant;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Réponse vocale de l'assistant (ElevenLabs Text-to-Speech) : la réponse écrite est lue à voix haute,
 * pratique les mains prises à l'écurie. Le son n'est jamais écrit sur le disque.
 */
class Speaker
{
    /** Voix par défaut (bibliothèque ElevenLabs), modifiable dans Administration › Assistant IA. */
    public const DEFAULT_VOICE = 'JBFqnCBsd6RMkjVDRZzb';

    public const MODELS = [
        'eleven_flash_v2_5' => 'Flash v2.5 — très rapide, économique (recommandé)',
        'eleven_multilingual_v2' => 'Multilingual v2 — plus naturel, plus lent',
    ];

    public function configured(): bool
    {
        return filled(config('services.elevenlabs.key')) && (bool) (ApplicationSetting::get('assistant.speech_enabled') ?? true);
    }

    public function voiceId(): string
    {
        $v = trim((string) ApplicationSetting::get('assistant.voice_id'));

        return preg_match('/^[A-Za-z0-9]{10,40}$/', $v) ? $v : self::DEFAULT_VOICE;
    }

    public function model(): string
    {
        $m = (string) ApplicationSetting::get('assistant.speech_model');

        return array_key_exists($m, self::MODELS) ? $m : 'eleven_flash_v2_5';
    }

    /** Longueur lue au plus (la réponse affichée reste complète). */
    public const MAX_CHARS = 600;

    /**
     * Découpage de la réponse pour une lecture plus rapide : la première phrase, courte, est générée
     * et jouée pendant que la suite est préparée (2 à 3 morceaux au plus).
     *
     * @return string[]
     */
    public function parts(string $text): array
    {
        $text = trim(mb_substr(preg_replace('/\s+/u', ' ', $text), 0, self::MAX_CHARS));
        if ($text === '') {
            return [];
        }
        $sentences = preg_split('/(?<=[.!?…:;])\s+(?=\S)/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
        $first = array_shift($sentences);
        // Une première phrase trop courte (« C'est noté. ») est jointe à la suivante.
        while ($sentences && mb_strlen($first) < 30) {
            $first .= ' '.array_shift($sentences);
        }
        $parts = [$first];
        $current = '';
        foreach ($sentences as $sentence) {
            if ($current !== '' && mb_strlen($current.' '.$sentence) > 280) {
                $parts[] = $current;
                $current = '';
            }
            $current = trim($current.' '.$sentence);
        }
        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /** MP3 de la phrase lue (600 caractères au plus). */
    public function speak(string $text, string $previous = '', string $next = ''): string
    {
        if (! filled(config('services.elevenlabs.key'))) {
            throw new RuntimeException('Voix non configurée (clé ElevenLabs manquante).');
        }
        $text = trim(mb_substr(preg_replace('/\s+/u', ' ', $text), 0, self::MAX_CHARS));
        if ($text === '') {
            throw new RuntimeException('Rien à lire.');
        }
        $response = Http::withHeaders(['xi-api-key' => config('services.elevenlabs.key'), 'Accept' => 'audio/mpeg'])
            ->connectTimeout(5)->timeout(30)->retry(1, 300, fn ($e) => ! $e instanceof \Illuminate\Http\Client\RequestException || $e->response->serverError(), throw: false)
            ->post(rtrim(config('services.elevenlabs.base_url', 'https://api.elevenlabs.io'), '/').'/v1/text-to-speech/'.$this->voiceId().'?output_format=mp3_44100_64', array_filter([
                'text' => $text,
                'model_id' => $this->model(),
                'language_code' => 'fr',
                // Intonation continue d'un morceau à l'autre.
                'previous_text' => $previous !== '' ? mb_substr($previous, -300) : null,
                'next_text' => $next !== '' ? mb_substr($next, 0, 300) : null,
            ]));
        if (! $response->successful()) {
            $message = str_replace(['Transcription : ', '« Speech to Text »'], ['Voix : ', '« Text to Speech »'], Transcriber::explain($response->status(), (string) $response->body()));
            if (Transcriber::creditsExhausted($response->status(), (string) $response->body())) {
                throw new VoiceCreditsExhausted($message);
            }
            throw new RuntimeException($message);
        }

        return $response->body();
    }
}
