<x-layouts.app title="Assistant">
    <x-page-header title="Assistant" subtitle="Vos consignes et ce que l'assistant a fait">
        <button type="button" @click="$store.assistant.open(true)" class="btn-primary"><x-icon name="mic" class="size-[18px]" /> Parler</button>
    </x-page-header>
    @include('settings._nav')
    @if ($unavailable)<div class="mb-4 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900">{{ $unavailable }}</div>@endif
    <p class="mb-4 text-sm text-slate-500">{{ $used }} consigne(s) ce mois-ci{{ $remaining !== null ? ' · '.$remaining.' restante(s)' : '' }}. Les enregistrements audio ne sont jamais conservés ; le texte des consignes est supprimé après 90 jours. Une action peut être annulée pendant 24 h si l'élément n'a pas été modifié entre-temps.</p>

    @forelse ($requests as $r)
        <article class="card mb-3 p-5">
            <div class="flex items-start justify-between gap-3">
                <p class="text-[15px] font-medium text-slate-900">« {{ $r->input_text }} »</p>
                <span class="shrink-0 text-xs text-slate-400">{{ $r->created_at->translatedFormat('j M, H:i') }}{{ $r->input_mode === 'voice' ? ' · voix' : '' }}</span>
            </div>
            @if ($r->reply)<p class="mt-2 text-sm {{ $r->status === 'error' ? 'text-red-700' : 'text-slate-600' }}">{{ $r->reply }}</p>@endif
            @foreach ($r->actions as $act)
                <div class="mt-3 flex items-center gap-3 rounded-2xl bg-slate-50 p-3 text-sm {{ $act->undone_at ? 'opacity-60' : '' }}">
                    <x-icon name="{{ $act->undone_at ? 'undo' : 'check' }}" class="size-4 shrink-0 {{ $act->undone_at ? 'text-slate-400' : 'text-brand-600' }}" />
                    @if ($act->url && ! $act->undone_at)<a href="{{ $act->url }}" class="min-w-0 flex-1 font-medium text-slate-800 hover:underline">{{ $act->summary }}</a>@else<span class="min-w-0 flex-1 {{ $act->undone_at ? 'line-through' : '' }}">{{ $act->summary }}</span>@endif
                    @if ($act->undone_at)<span class="text-xs text-slate-500">Annulé</span>
                    @elseif ($act->isUndoable())<form method="POST" action="{{ route('assistant.undo', $act) }}">@csrf<button class="btn-ghost min-h-9 px-3 text-xs">Annuler</button></form>@endif
                </div>
            @endforeach
        </article>
    @empty
        <x-empty title="Aucune consigne pour l'instant" icon="sparkles">Touchez le micro et dites par exemple « le maréchal est prévu le 2 décembre à 9 h ».</x-empty>
    @endforelse
    {{ $requests->links() }}
</x-layouts.app>
