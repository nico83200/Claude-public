<x-layouts.app :title="'Résumé vétérinaire · '.$horse->shortName()">
    <x-horse-header :horse="$horse" />
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <p class="mb-3 text-sm text-slate-600">Les 6 derniers mois du dossier de santé de {{ $horse->shortName() }}, tels que le vétérinaire les verra.</p>
            @include('health._vet-summary-body')
        </div>
        <div class="space-y-4">
            @if ($canShare)
                <x-section title="Partager avec le vétérinaire" data-vet-share-form>
                    <form method="POST" action="{{ route('horses.vet-summary.store', $horse) }}" class="space-y-3" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        <x-field name="recipient" label="Pour (facultatif)" placeholder="ex. Dr Leroy" maxlength="120" />
                        <x-select name="days" label="Lien valable" :options="\App\Models\HealthShare::DURATIONS" value="7" />
                        @if ($aiAvailable)
                            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="with_ai" value="1" class="mt-1" checked> <span>Ajouter une synthèse rédigée par l'assistant <span class="block text-xs text-slate-500">5 à 8 lignes factuelles en tête du résumé, sans avis médical. Compte comme une consigne.</span></span></label>
                        @endif
                        <button class="btn-primary w-full" :disabled="busy"><x-icon name="share" class="size-4" /> <span x-text="busy ? 'Préparation…' : 'Créer le lien'">Créer le lien</span></button>
                    </form>
                    <p class="mt-3 text-xs text-slate-500">Consultable sans compte jusqu'à son expiration, jamais référencé par les moteurs de recherche. Vous pouvez le désactiver à tout moment.</p>
                </x-section>
            @endif
            @if ($shares->isNotEmpty())
                <x-section title="Liens créés">
                    @foreach ($shares as $sh)
                        @php $url = route('vet-summary.public', $sh->token); @endphp
                        <div class="border-t border-slate-100 py-3 text-sm first:border-0" x-data="{ copied: false }" data-vet-share="{{ $sh->id }}">
                            <p class="font-medium">{{ $sh->recipient ?: 'Lien du '.$sh->created_at->format('d/m/Y') }} @if ($sh->isActive())<x-badge color="green">actif</x-badge>@else<x-badge>{{ $sh->revoked_at ? 'désactivé' : 'expiré' }}</x-badge>@endif</p>
                            <p class="text-xs text-slate-500">{{ $sh->isActive() ? 'Jusqu\'au '.$sh->expires_at->format('d/m/Y H:i') : 'Créé le '.$sh->created_at->format('d/m/Y') }} · {{ $sh->views }} consultation(s){{ $sh->ai_summary ? ' · avec synthèse' : '' }}</p>
                            @if ($sh->isActive())
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" class="btn-secondary min-h-9 px-3 text-xs" @click="navigator.share ? navigator.share({ title: @js('Résumé de santé · '.$horse->shortName()), url: @js($url) }).catch(() => {}) : navigator.clipboard.writeText(@js($url)).then(() => copied = true)"><x-icon name="share" class="size-4" /> <span x-text="copied ? 'Lien copié' : 'Partager'">Partager</span></button>
                                    <a href="{{ $url }}" target="_blank" class="btn-ghost min-h-9 px-3 text-xs">Ouvrir</a>
                                    @if ($canShare)<form method="POST" action="{{ route('horses.vet-summary.revoke', [$horse, $sh]) }}">@csrf<button class="btn-ghost min-h-9 px-3 text-xs text-red-600">Désactiver</button></form>@endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </x-section>
            @endif
        </div>
    </div>
</x-layouts.app>
