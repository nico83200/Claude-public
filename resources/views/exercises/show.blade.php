<x-layouts.app :title="$exercise->name">
    <x-page-header :title="$exercise->name" :subtitle="collect([$exercise->category?->name, \App\Models\Exercise::LEVELS[$exercise->level], \App\Models\Exercise::SCOPES[$exercise->scope]])->filter()->implode(' · ')" :back="route('exercises.index')">
        <form method="POST" action="{{ route('exercises.duplicate', $exercise) }}">@csrf<button class="btn-secondary">Dupliquer</button></form>
        @if ($canEdit)
            <a href="{{ route('exercises.edit', $exercise) }}" class="btn-secondary">Modifier</a>
            <form method="POST" action="{{ route('exercises.archive', $exercise) }}">@csrf<button class="btn-ghost">{{ $exercise->archived_at ? 'Réactiver' : 'Archiver' }}</button></form>
        @endif
    </x-page-header>
    <div class="grid gap-4 lg:grid-cols-3">
        <x-section class="lg:col-span-2">
            <x-dl :items="['Objectif pédagogique' => $exercise->objective, 'Description' => $exercise->description, 'Consignes' => $exercise->instructions, 'Prérequis' => $exercise->prerequisites, 'Matériel' => $exercise->equipment, 'Erreurs fréquentes' => $exercise->common_mistakes, 'Points de vigilance' => $exercise->vigilance, 'Critères de réussite' => $exercise->success_criteria]" />
            @if ($exercise->steps)
                <p class="mt-4 text-xs font-medium tracking-wide text-slate-500 uppercase">Étapes</p>
                <ol class="mt-1 list-decimal pl-5 text-sm">@foreach ($exercise->steps as $step)<li>{{ $step }}</li>@endforeach</ol>
            @endif
        </x-section>
        <x-section>
            <x-dl :items="['Discipline' => \App\Models\Horse::DISCIPLINES[$exercise->discipline] ?? ($exercise->discipline === 'flat' ? 'Travail sur le plat' : ($exercise->discipline === 'all' ? 'Toutes' : null)), 'Durée indicative' => $exercise->duration_minutes ? $exercise->duration_minutes.' min' : null, 'Répétitions' => $exercise->repetitions, 'Auteur' => $exercise->author?->name, 'Version' => $exercise->version]" />
            @if ($exercise->media_url)<a href="{{ $exercise->media_url }}" target="_blank" rel="noopener" class="mt-3 block text-sm link">Voir le média</a>@endif
            @if ($exercise->tags->isNotEmpty())<div class="mt-3 flex flex-wrap gap-1">@foreach ($exercise->tags as $t)<span class="chip bg-slate-100 text-slate-600">#{{ $t->name }}</span>@endforeach</div>@endif
            @if ($exercise->scope === 'default')<p class="mt-3 text-xs text-slate-500">Exercice de la bibliothèque par défaut : dupliquez-le pour l'adapter à votre cheval.</p>@endif
        </x-section>
    </div>
</x-layouts.app>
