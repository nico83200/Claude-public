<x-layouts.app :title="'Suivi quotidien · '.$horse->shortName()">
    <x-page-header :title="$horse->shortName()" subtitle="Suivi quotidien" :back="route('horses.show', $horse)" />
    @include('horses._tabs')
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-2 lg:col-span-2">
            @forelse ($logs as $log)
                <div class="card p-4 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-semibold">{{ $log->logged_at->translatedFormat('l j F Y, H:i') }}</span><span class="text-xs text-slate-500">{{ $log->author?->name }}</span></div>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @if ($log->appetite)<x-badge :color="$log->appetite === 'good' ? 'green' : 'amber'">Appétit : {{ \App\Models\DailyLog::APPETITE[$log->appetite] }}</x-badge>@endif
                        @if ($log->general_state)<x-badge :color="$log->general_state === 'good' ? 'green' : ($log->general_state === 'poor' ? 'red' : 'amber')">État : {{ \App\Models\DailyLog::STATE[$log->general_state] }}</x-badge>@endif
                    </div>
                    @if ($log->behavior)<p class="mt-1"><span class="text-slate-500">Comportement :</span> {{ $log->behavior }}</p>@endif
                    @if ($log->activity)<p><span class="text-slate-500">Activité :</span> {{ $log->activity }}</p>@endif
                    @if ($log->observations)<p class="whitespace-pre-line">{{ $log->observations }}</p>@endif
                    @if ($log->anomalies)<p class="mt-1 text-red-700">⚠ {{ $log->anomalies }}</p>@endif
                </div>
            @empty
                <x-empty title="Aucun suivi saisi" icon="doc">Notez chaque jour l'appétit, l'état général et les observations.</x-empty>
            @endforelse
            {{ $logs->links() }}
        </div>
        @if ($canCreate)
        <x-section title="Nouvelle saisie">
            <form method="POST" action="{{ route('horses.logs.store', $horse) }}" class="space-y-3">
                @csrf
                <x-select name="appetite" label="Appétit" :options="\App\Models\DailyLog::APPETITE" value="good" />
                <x-select name="general_state" label="État général" :options="\App\Models\DailyLog::STATE" value="good" />
                <x-field name="behavior" label="Comportement" />
                <x-field name="activity" label="Activité" placeholder="Paddock, marcheur, repos…" />
                <x-textarea name="observations" label="Observations" />
                <x-textarea name="anomalies" label="Anomalies constatées" />
                <button class="btn-primary w-full">Enregistrer</button>
            </form>
        </x-section>
        @endif
    </div>
</x-layouts.app>
