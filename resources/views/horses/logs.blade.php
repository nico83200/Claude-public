<x-layouts.app :title="'Suivi quotidien · '.$horse->shortName()">
    <x-horse-header :horse="$horse" />
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="order-2 space-y-2 lg:order-1 lg:col-span-2">
            @forelse ($logs as $log)
                <div class="card p-4 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-semibold">{{ $log->logged_at->translatedFormat('l j F Y, H:i') }}</span><span class="flex items-center gap-1 text-xs text-slate-500">{{ $log->author?->name }}
                        @if (\App\Http\Controllers\DailyLogController::canEdit($log, $perms))
                            <a href="{{ route('horses.logs.edit', [$horse, $log]) }}" class="btn-ghost size-11 justify-center p-0" aria-label="Modifier la saisie" data-log-edit><x-icon name="pencil" class="size-4" /></a>
                        @endif
                    </span></div>
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
        <div class="order-1 space-y-3 lg:order-2">
            {{-- Une journée sans histoire se note en un geste. --}}
            <form method="POST" action="{{ route('horses.logs.store', $horse) }}" data-log-all-good>
                @csrf
                <input type="hidden" name="appetite" value="good"><input type="hidden" name="general_state" value="good"><input type="hidden" name="all_good" value="1">
                <button class="btn-primary min-h-12 w-full gap-2"><x-icon name="check" class="size-5" /> Tout va bien aujourd'hui</button>
            </form>
            <details class="card p-4" @if ($errors->any()) open @endif data-log-detailed>
                <summary class="flex min-h-11 cursor-pointer items-center font-semibold">Saisie détaillée</summary>
                <form method="POST" action="{{ route('horses.logs.store', $horse) }}" class="mt-3 space-y-3">
                    @csrf
                    @include('horses._log-form', ['log' => null])
                    <button class="btn-primary w-full">Enregistrer</button>
                </form>
            </details>
        </div>
        @endif
    </div>
</x-layouts.app>
