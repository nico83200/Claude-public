<x-layouts.app title="Assistant IA · Admin">
    <x-page-header title="Assistant IA" subtitle="Consignes vocales : compréhension par Claude, transcription par ElevenLabs" />
    @include('admin._nav')

    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([['Consignes ce mois', $stats['requests'], $stats['voice'].' à la voix'], ['Actions réalisées', $stats['actions'], $stats['undone'].' annulée(s)'], ['Erreurs', $stats['errors'], $stats['requests'] ? round($stats['errors'] / $stats['requests'] * 100).' %' : '—'], ['Temps de réponse moyen', $stats['avg_ms'] ? number_format($stats['avg_ms'] / 1000, 1, ',', ' ').' s' : '—', number_format($stats['input_tokens'] / 1000, 0, ',', ' ').' k jetons lus · '.number_format($stats['output_tokens'] / 1000, 0, ',', ' ').' k écrits']] as [$label, $value, $sub])
            <div class="card p-4"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-bold">{{ $value }}</p><p class="text-xs text-slate-400">{{ $sub }}</p></div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.assistant.update') }}" class="grid gap-4 lg:grid-cols-5">
        @csrf @method('PUT')
        <div class="space-y-4 lg:col-span-3">
            <x-section title="Indications pour l'IA">
                <p class="mb-3 text-sm text-slate-600">Texte libre ajouté aux consignes de l'assistant pour tous les utilisateurs : habitudes, vocabulaire, préférences de séance… Il complète les règles intégrées (dates, droits, durée des séances) sans pouvoir les contourner.</p>
                <x-textarea name="instructions" label="Indications" :value="$config->instructions()" rows="12"
                    :placeholder="implode(PHP_EOL, ['Exemples :', '- Les séances du matin commencent à 9 h, celles du soir à 18 h.', '- « Le carré » désigne la carrière de dressage, « le rond » le rond de longe.', '- Toujours prévoir 15 minutes de pas en échauffement pour les chevaux de plus de 15 ans.', '- Le maréchal habituel est M. Durand.'])" />
                <p class="mt-1 text-xs text-slate-400">8 000 caractères au plus. Ce texte est envoyé à Anthropic avec chaque consigne.</p>
            </x-section>
            <x-section title="Séances créées à l'oral">
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="session_min" type="number" label="Durée minimale (min)" :value="$config->sessionMinutes()[0]" min="10" max="240" required />
                    <x-field name="session_max" type="number" label="Durée maximale (min)" :value="$config->sessionMinutes()[1]" min="10" max="240" required />
                </div>
                <p class="mt-2 text-xs text-slate-500">Utilisé quand la consigne ne précise pas de durée. Les exercices sont choisis d'abord dans la bibliothèque ; un exercice libre est ajouté à la séance seulement si aucun ne convient.</p>
            </x-section>
            <x-section title="Réponse vocale et récapitulatif" data-speech-settings>
                @php $speaker = app(\App\Services\Assistant\Speaker::class); @endphp
                <x-checkbox name="speech_enabled" label="Lire les réponses à voix haute (ElevenLabs)" :checked="(bool) (\App\Models\ApplicationSetting::get('assistant.speech_enabled') ?? true)" help="Chaque utilisateur l'active ou la coupe avec le haut-parleur de l'assistant ; par défaut, la réponse est lue quand la consigne a été dictée. La clé ElevenLabs doit avoir la permission « Text to Speech »." />
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <x-field name="voice_id" label="Identifiant de la voix" :value="\App\Models\ApplicationSetting::get('assistant.voice_id')" :placeholder="\App\Services\Assistant\Speaker::DEFAULT_VOICE" maxlength="40" help="Bibliothèque de voix ElevenLabs › copier l'ID." />
                    <x-select name="speech_model" label="Modèle de voix" :options="\App\Services\Assistant\Speaker::MODELS" :value="$speaker->model()" />
                </div>
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <x-checkbox name="digest_ai" label="Récapitulatif de la semaine rédigé par l'assistant" :checked="(bool) (\App\Models\ApplicationSetting::get('digest_ai') ?? true)" help="Introduction personnalisée de 2 à 3 phrases, dans l'e-mail du dimanche et en haut de la page « Ma semaine » (rédigée à nouveau seulement quand la semaine change), pour les espaces dont l'offre inclut l'assistant, sans décompter de consigne. Sinon, le texte standard." />
                </div>
            </x-section>
        </div>
        <div class="space-y-4 lg:col-span-2">
            <x-section title="Fonctionnement">
                <x-checkbox name="enabled" label="Assistant activé" :checked="$config->enabled()" help="Disponible pour les espaces dont l'offre inclut « Assistant vocal (IA) », dans la limite du quota mensuel (Administration › Offres)." />
                <div class="mt-3 space-y-3">
                    <x-select name="model" label="Modèle Claude" :options="\App\Services\Assistant\AssistantConfig::MODELS" :value="$config->model()" />
                    <x-select name="effort" label="Réflexion" :options="\App\Services\Assistant\AssistantConfig::EFFORTS" :value="$config->effort()" />
                    <div data-result-seconds>
                        <x-select name="result_seconds" label="Affichage de la réponse" :options="\App\Services\Assistant\AssistantConfig::RESULT_DELAYS" :value="$config->resultSeconds()" />
                        <p class="mt-1 text-xs text-slate-500">Ensuite (ou dès que l'utilisateur ferme), l'élément concerné s'ouvre : rendez-vous de l'agenda, soin, séance… ; à défaut, le tableau de bord. L'annulation reste possible quelques secondes sur la page ouverte, puis dans l'historique.</p>
                    </div>
                </div>
            </x-section>
            <x-section title="Crédits IA" data-credit-settings>
                @php $packs = \App\Services\Assistant\AssistantCredits::packs(); @endphp
                <x-checkbox name="credits_enabled" label="Vendre des crédits IA" :checked="\App\Services\Assistant\AssistantCredits::enabled()" help="Quand le quota mensuel d'un espace est épuisé, l'assistant propose d'acheter des crédits (1 crédit = 1 consigne ou 1 lecture IA). Paiement unique par Stripe ; les crédits n'expirent pas." />
                <div class="mt-3 space-y-2">
                    @for ($i = 0; $i < 3; $i++)
                        <div class="grid grid-cols-2 gap-3">
                            <x-field :name="'packs['.$i.'][credits]'" type="number" min="0" max="10000" :label="'Pack '.($i + 1).' : crédits'" :value="$packs[$i]['credits'] ?? null" />
                            <x-field :name="'packs['.$i.'][price]'" type="number" step="0.01" min="0" max="1000" label="Prix (€)" :value="isset($packs[$i]) ? number_format($packs[$i]['price_cents'] / 100, 2, '.', '') : null" />
                        </div>
                    @endfor
                </div>
                <p class="mt-2 text-xs text-slate-500">Laissez une ligne vide pour ne pas proposer ce pack. Prix minimum 0,50 € (Stripe).</p>
            </x-section>
            <x-section title="Clés d'accès">
                <div class="space-y-3">
                    <div>
                        <x-field name="anthropic_key" type="password" label="Clé API Anthropic (sk-ant-…)" autocomplete="new-password"
                            :help="$masked['anthropic.key'] ? 'Enregistrée ('.$masked['anthropic.key'].') — laisser vide pour la conserver.' : ($configured['anthropic'] ? 'Définie dans le .env.' : 'Non renseignée : l\'assistant est inactif.')" />
                        @if ($masked['anthropic.key'])<x-checkbox name="clear[]" value="anthropic.key" label="Effacer la clé enregistrée" />@endif
                    </div>
                    <div>
                        <x-field name="elevenlabs_key" type="password" label="Clé API ElevenLabs (transcription)" autocomplete="new-password"
                            :help="$masked['elevenlabs.key'] ? 'Enregistrée ('.$masked['elevenlabs.key'].') — laisser vide pour la conserver.' : ($configured['elevenlabs'] ? 'Définie dans le .env.' : 'Non renseignée : seule la saisie au clavier est possible.')" />
                        @if ($masked['elevenlabs.key'])<x-checkbox name="clear[]" value="elevenlabs.key" label="Effacer la clé enregistrée" />@endif
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500">Clés chiffrées en base, jamais réaffichées. L'audio est envoyé à ElevenLabs puis oublié ; le texte de la consigne, la liste des chevaux et intervenants accessibles et la bibliothèque d'exercices sont envoyés à Anthropic. Mentionnez ces sous-traitants dans Paramètres › Informations légales.</p>
            </x-section>
            <button class="btn-primary w-full">Enregistrer</button>
        </div>
    </form>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-section title="Tester les connexions">
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.assistant.test') }}">@csrf<input type="hidden" name="service" value="anthropic"><button class="btn-secondary" @disabled(! $configured['anthropic'])>Tester Claude</button></form>
                <form method="POST" action="{{ route('admin.assistant.test') }}">@csrf<input type="hidden" name="service" value="elevenlabs"><button class="btn-secondary" @disabled(! $configured['elevenlabs'])>Tester ElevenLabs</button></form>
            </div>
            @if ($configured['elevenlabs'])
                <div class="mt-3" x-data="{ src: null, error: '', busy: false }" data-speech-test>
                    <button type="button" class="btn-ghost text-sm" :disabled="busy" @click="busy = true; error = ''; fetch(@js(route('admin.assistant.voice-test')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } }).then(async r => { if (! r.ok) throw new Error((await r.json()).message); src = URL.createObjectURL(await r.blob()); $nextTick(() => $refs.player.play()) }).catch(e => error = e.message).finally(() => busy = false)">Écouter la voix de l'assistant</button>
                    <audio x-ref="player" :src="src" controls x-show="src" x-cloak class="mt-2 w-full"></audio>
                    <p class="mt-1 text-sm text-red-600" x-show="error" x-text="error" x-cloak></p>
                </div>
            @endif
        </x-section>
        <x-section title="Dernières erreurs">
            @forelse ($recentErrors as $r)
                <p class="border-t border-slate-100 py-2 text-sm first:border-0"><span class="text-xs text-slate-400">{{ $r->created_at->format('d/m H:i') }} · {{ $r->user?->name }}</span><br>« {{ \Illuminate\Support\Str::limit($r->input_text, 80) }} » — <span class="text-red-700">{{ \Illuminate\Support\Str::limit($r->reply, 120) }}</span></p>
            @empty <p class="text-sm text-slate-500">Aucune erreur.</p> @endforelse
        </x-section>
    </div>
</x-layouts.app>
