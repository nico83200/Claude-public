<x-layouts.app title="Paramètres">
    <x-page-header title="Paramètres" />
    @include('settings._nav')
    <form method="POST" action="{{ route('settings.profile.update') }}" class="max-w-xl space-y-4">
        @csrf @method('PUT')
        <x-section title="Profil">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="name" label="Nom affiché" :value="$user->name" required class="sm:col-span-2" />
                <x-field name="email" type="email" label="Email" :value="$user->email" required class="sm:col-span-2" help="Un changement d'adresse nécessite une nouvelle vérification." />
                <x-field name="first_name" label="Prénom" :value="$profile->first_name" />
                <x-field name="last_name" label="Nom" :value="$profile->last_name" />
                <x-field name="phone" type="tel" label="Téléphone" :value="$profile->phone" />
                <x-field name="city" label="Ville" :value="$profile->city" />
                @php $galopOptions = collect(\App\Models\UserProfile::GALOPS)->mapWithKeys(fn ($l) => [$l => $l]); if ($profile->riding_level && ! $profile->galop()) { $galopOptions->put($profile->riding_level, $profile->riding_level); } @endphp
                <div class="sm:col-span-2" data-riding-level>
                    <x-select name="riding_level" label="Niveau équestre (galop)" :options="$galopOptions" :value="$profile->galop() ? 'Galop '.$profile->galop() : $profile->riding_level" placeholder="Non renseigné" />
                    <p class="mt-1 text-xs text-slate-500">Galops 1 à 3 : débutant · 4 et 5 : intermédiaire · 6 et plus : expert. Sert à proposer des exercices adaptés.</p>
                </div>
            </div>
        </x-section>
        <button class="btn-primary">Enregistrer</button>
    </form>

    {{-- Apparence : appliquée tout de suite, enregistrée sur le compte (et sur cet appareil). --}}
    @php $theme = $profile->theme ?? 'light'; @endphp
    <x-section title="Apparence" class="mt-6 max-w-xl">
        <form method="POST" action="{{ route('settings.theme') }}" x-data="{ theme: @js($theme) }"
              @change="theme = $event.target.value; window.jcSetTheme(theme); fetch($el.action, { method: 'PUT', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ theme }) })">
            @csrf @method('PUT')
            <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Apparence">
                @foreach (['light' => ['Clair', 'sun'], 'dark' => ['Sombre', 'moon'], 'auto' => ['Automatique', 'device']] as $value => [$label, $icon])
                    <label class="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border p-3 text-sm font-medium transition" :class="theme === @js($value) ? 'border-brand-500 bg-brand-50 text-brand-900 ring-2 ring-brand-100' : 'border-slate-200 text-slate-600 hover:bg-slate-50'">
                        <input type="radio" name="theme" value="{{ $value }}" class="sr-only" @checked($theme === $value)>
                        <x-icon :name="$icon" class="size-6" /> {{ $label }}
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-slate-500">« Automatique » suit le réglage clair / sombre de votre téléphone ou de votre ordinateur.</p>
            <noscript><button class="btn-secondary mt-3">Enregistrer</button></noscript>
        </form>
    </x-section>
</x-layouts.app>
