<x-layouts.app :title="'Point d\'attention · '.$horse->shortName()">
    <x-page-header title="Quelque chose d'inhabituel ?" :subtitle="$horse->shortName().' · on note, on suit, on garde le vétérinaire à portée de main'" :back="url()->previous() !== url()->current() ? url()->previous() : route('dashboard')" />
    @php $sms = 'Bonjour, c\'est '.auth()->user()->name.' pour '.$horse->shortName().'. Pouvez-vous me rappeler ? Merci.'; @endphp
    <div class="grid gap-4 lg:grid-cols-5 lg:items-start">
        <div class="space-y-4 lg:col-span-2">
            @include('observations._calm', ['severity' => old('severity')])
            @include('observations._vet', ['vet' => $vet, 'horse' => $horse, 'sms' => $sms])
        </div>
        <form method="POST" action="{{ route('horses.observations.store', $horse) }}" enctype="multipart/form-data" class="card space-y-4 p-5 sm:p-6 lg:col-span-3" data-attention-form>
            @csrf
            <x-field name="title" label="En quelques mots" maxlength="120" placeholder="Saignement de nez, boiterie, plaie…" />
            <x-textarea name="body" label="Ce que vous avez remarqué" rows="4" required placeholder="Quand, combien de temps, ce que vous avez fait (nettoyage, repos…)" />
            <fieldset>
                <legend class="label">Attention à porter</legend>
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach (\App\Models\HealthObservation::SEVERITIES as $key => $label)
                        <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-2xl px-3 ring-1 ring-slate-200 has-[:checked]:bg-brand-50 has-[:checked]:ring-2 has-[:checked]:ring-brand-500">
                            <input type="radio" name="severity" value="{{ $key }}" @checked(old('severity', 'watch') === $key) class="accent-brand-700"> <span class="text-sm font-medium">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('severity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </fieldset>
            @include('observations._photo-input', ['id' => 'f_attention_photos'])
            <p class="text-xs text-slate-500">Les personnes qui suivent la santé de {{ $horse->shortName() }} sont prévenues. L'appli vous redemandera des nouvelles.</p>
            <button class="btn-primary min-h-12 w-full">Ouvrir le suivi</button>
        </form>
    </div>
</x-layouts.app>
