<x-layouts.app :title="'Fiche d\'urgence · '.$horse->shortName()">
    <x-horse-header :horse="$horse" />
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-4">
            <x-section title="Fiche d'urgence">
                <p class="mb-4 text-sm text-slate-600">Un QR code à coller sur le box ou la sellerie : toute personne qui le scanne voit, <strong>sans compte</strong>, le contact à appeler, les intervenants habituels, les traitements en cours et les précautions. Rien d'autre du dossier n'est visible.</p>
                <form method="POST" action="{{ route('horses.emergency.update', $horse) }}" class="space-y-3" data-emergency-form>
                    @csrf @method('PUT')
                    <x-field name="emergency_contact_name" label="Personne à appeler" :value="$horse->emergency_contact_name ?? $owner->name" maxlength="120" />
                    <x-field name="emergency_contact_phone" type="tel" label="Téléphone" :value="$horse->emergency_contact_phone ?? $owner->profile?->phone" maxlength="40" inputmode="tel" placeholder="06 12 34 56 78" />
                    <x-textarea name="emergency_notes" label="À savoir en urgence (facultatif)" rows="3" :value="$horse->emergency_notes" maxlength="1000" placeholder="ex. Allergique à la pénicilline · Tique à l'attache · Assurance : …" />
                    <p class="text-xs text-slate-500">Les précautions de la fiche du cheval et les traitements en cours s'ajoutent automatiquement. Une copie reste consultable sur ce téléphone même sans réseau.</p>
                    @if ($horse->emergency_token)
                        <button class="btn-primary w-full">Enregistrer</button>
                    @else
                        <button class="btn-primary w-full" name="action" value="enable" data-emergency-enable><x-icon name="alert" class="size-4" /> Activer la fiche d'urgence</button>
                    @endif
                </form>
            </x-section>
            @if ($horse->emergency_token)
                {{-- Copie de la fiche gardée dans ce téléphone : consultable à l'écurie sans réseau. --}}
                <script>
                    try { navigator.serviceWorker?.ready.then((r) => r.active?.postMessage({ type: 'cache-emergency', path: @js(parse_url(route('emergency.public', $horse->emergency_token), PHP_URL_PATH)) })); } catch { }
                </script>
                <x-section title="QR code">
                    <div class="flex flex-wrap items-center gap-4">
                        <img src="{{ route('horses.emergency.qr', $horse) }}" alt="QR code de la fiche d'urgence" class="size-32 rounded-xl border border-slate-200">
                        <div class="flex flex-col gap-2">
                            <a href="{{ route('horses.emergency.print', $horse) }}" target="_blank" class="btn-primary" data-emergency-print-link>Imprimer la fiche</a>
                            <a href="{{ route('emergency.public', $horse->emergency_token) }}" target="_blank" class="btn-secondary">Voir la fiche publique</a>
                            <a href="{{ route('horses.emergency.qr', $horse) }}" class="text-sm link">Télécharger le QR code</a>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                        <form method="POST" action="{{ route('horses.emergency.update', $horse) }}" onsubmit="return confirm('Créer un nouveau lien ? L\'ancien QR code ne fonctionnera plus.')">@csrf @method('PUT')<input type="hidden" name="emergency_contact_name" value="{{ $horse->emergency_contact_name }}"><input type="hidden" name="emergency_contact_phone" value="{{ $horse->emergency_contact_phone }}"><input type="hidden" name="emergency_notes" value="{{ $horse->emergency_notes }}"><button class="btn-ghost text-sm" name="action" value="renew">Changer le lien</button></form>
                        <form method="POST" action="{{ route('horses.emergency.update', $horse) }}" onsubmit="return confirm('Désactiver la fiche ? Le QR code ne mènera plus à rien.')">@csrf @method('PUT')<input type="hidden" name="emergency_contact_name" value="{{ $horse->emergency_contact_name }}"><input type="hidden" name="emergency_contact_phone" value="{{ $horse->emergency_contact_phone }}"><input type="hidden" name="emergency_notes" value="{{ $horse->emergency_notes }}"><button class="btn-ghost text-sm text-red-600" name="action" value="disable" data-emergency-disable>Désactiver</button></form>
                    </div>
                </x-section>
            @endif
        </div>
        <div>
            <p class="mb-2 text-sm font-medium text-slate-500">Aperçu de ce qui est visible</p>
            <div class="rounded-3xl bg-slate-100 p-4">
                <p class="mb-3 text-2xl font-bold">{{ $horse->shortName() }}</p>
                @include('emergency._sheet')
            </div>
        </div>
    </div>
</x-layouts.app>
