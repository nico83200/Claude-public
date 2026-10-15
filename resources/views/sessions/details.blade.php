<x-layouts.app :title="'Modifier la séance · '.$session->horse->shortName()">
    <x-page-header title="Modifier la séance" :subtitle="$session->horse->shortName().' · '.$session->scheduled_at->translatedFormat('l j F à H:i')" :back="route('sessions.show', $session)" />
    <div class="max-w-3xl space-y-4">
        @if ($session->isCompleted())
            <x-section title="Notes">
                <p class="-mt-1 mb-3 text-sm text-slate-500">Séance terminée : seules les notes restent modifiables. Pour le bilan, utilisez « Bilan » ; pour changer la date ou les exercices, dupliquez la séance.</p>
                <form method="POST" action="{{ route('sessions.update', $session) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <input type="hidden" name="return_to" value="show">
                    <x-textarea name="notes" label="Notes" :value="$session->notes" />
                    <button class="btn-primary">Enregistrer</button>
                </form>
            </x-section>
        @else
            <x-section>
                @include('sessions._details-form', ['returnTo' => 'show'])
            </x-section>
            @if ($session->usesExercises())<p class="text-sm text-slate-500">Pour changer les exercices, utilisez <a href="{{ route('sessions.edit', $session) }}" class="link">Préparer</a>.</p>@endif
        @endif
    </div>
</x-layouts.app>
