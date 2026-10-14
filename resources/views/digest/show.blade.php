@php $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' €'; $d = $digest; @endphp
<x-layouts.app title="Ma semaine">
    <x-page-header title="Ma semaine" :subtitle="'Du '.$d['from']->translatedFormat('l j F').' au '.$d['to']->translatedFormat('l j F')" />
    @if ($intro || $introPending)
        <div class="mb-4 flex gap-3 rounded-3xl border border-brand-100 bg-brand-50/60 p-4 sm:p-5" data-digest-intro
             x-data="{ text: @js($intro), loading: @js($introPending) }"
             x-init="if (loading) fetch(@js(route('digest.intro')), { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } })
                 .then((r) => r.ok ? r.json() : {}).then((j) => { text = j.text || null }).catch(() => {}).finally(() => { loading = false })"
             x-show="loading || text" x-cloak>
            <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-white text-brand-700"><x-icon name="sparkles" class="size-5" /></span>
            <div class="min-w-0 flex-1 self-center">
                <p x-show="text" x-text="text" class="text-[15px] leading-relaxed text-slate-800">{{ $intro }}</p>
                <div x-show="loading" class="space-y-2" aria-live="polite">
                    <span class="sr-only">L'assistant rédige le résumé de votre semaine…</span>
                    <span class="block h-3 w-11/12 animate-pulse rounded-full bg-brand-100"></span>
                    <span class="block h-3 w-3/4 animate-pulse rounded-full bg-brand-100"></span>
                    <span class="block text-xs text-brand-700/70">L'assistant résume votre semaine…</span>
                </div>
            </div>
        </div>
    @endif
    @unless ($enabled)
        <p class="mb-4 rounded-2xl bg-slate-100 p-4 text-sm text-slate-600">Le récapitulatif du dimanche soir est désactivé. <a href="{{ route('settings.notifications') }}" class="link">Le réactiver</a></p>
    @endunless
    <div class="grid gap-4 lg:grid-cols-3" data-digest>
        <div class="space-y-4 lg:col-span-2">
            <x-section title="Au programme" :action="route('calendar.index', ['view' => 'week', 'date' => $d['from']->toDateString()])" action-label="Agenda">
                @forelse ($d['events'] as $o)
                    <a href="{{ route('calendar.show', $o['event']) }}" class="-mx-2 flex items-center gap-4 rounded-2xl px-2 py-2.5 hover:bg-slate-50" data-digest-event>
                        <span class="w-24 shrink-0 text-sm"><span class="block font-semibold">{{ ucfirst($o['at']->translatedFormat('D j')) }}</span><span class="text-slate-500">{{ $o['event']->all_day ? 'Journée' : $o['at']->format('H:i') }}</span></span>
                        <span class="min-w-0"><span class="block truncate font-medium text-slate-900">{{ $o['event']->title }}</span>@if ($o['event']->horse && ! str_contains($o['event']->title, $o['event']->horse->shortName()))<span class="text-sm text-slate-500">{{ $o['event']->horse->shortName() }}</span>@endif</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">Rien de prévu dans l'agenda. Un rendez-vous à noter ? Dites-le à l'assistant.</p>
                @endforelse
            </x-section>
            @if ($d['debriefs']->isNotEmpty())
                <x-section title="Bilans de séance à faire">
                    @foreach ($d['debriefs'] as $s)
                        <a href="{{ route('sessions.debrief', $s) }}" class="-mx-2 flex items-center justify-between gap-3 rounded-2xl px-2 py-2.5 hover:bg-slate-50"><span><span class="block font-medium">{{ $s->title() }}</span><span class="text-sm text-slate-500">{{ $s->horse->shortName() }} · {{ $s->scheduled_at->translatedFormat('l j F') }}</span></span><span class="btn-secondary min-h-9 px-3 text-sm">Faire le bilan</span></a>
                    @endforeach
                </x-section>
            @endif
        </div>
        <div class="space-y-4">
            <x-section title="Budget du mois" :action="route('budget.index')" action-label="Budget">
                <p class="text-2xl font-bold">{{ $eur($d['month_total']) }}</p>
                <p class="text-sm text-slate-500">{{ $d['month_count'] }} dépense(s) en {{ now()->translatedFormat('F') }}</p>
            </x-section>
            @if ($d['payments']->isNotEmpty())
                <x-section title="Paiements à prévoir">
                    @foreach ($d['payments'] as $e)
                        <a href="{{ route('budget.show', $e) }}" class="flex justify-between gap-2 border-t border-slate-100 py-2 text-sm first:border-0"><span><span class="block font-medium">{{ $e->comment ?: $e->category?->name }}</span><span class="{{ $e->isOverdue() ? 'text-red-600' : 'text-slate-500' }}">{{ $e->isOverdue() ? 'En retard' : $e->spent_on->format('d/m') }}{{ $e->horse ? ' · '.$e->horse->shortName() : '' }}</span></span><span class="font-semibold">{{ $eur($e->amount) }}</span></a>
                    @endforeach
                </x-section>
            @endif
            @if ($d['treatments']->isNotEmpty())
                <x-section title="Traitements en cours">
                    @foreach ($d['treatments'] as $t)
                        <a href="{{ route('horses.care.index', $t->horse) }}" class="block border-t border-slate-100 py-2 text-sm first:border-0"><span class="block font-medium">{{ $t->product }}</span><span class="text-slate-500">{{ $t->horse->shortName() }}{{ $t->ends_on ? ' · jusqu\'au '.$t->ends_on->format('d/m') : '' }}</span></a>
                    @endforeach
                </x-section>
            @endif
        </div>
    </div>
</x-layouts.app>
