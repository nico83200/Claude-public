{{-- Page Santé du menu : onglets Santé, Alimentation, Suivi, Poids, et choix du cheval pour les trois derniers. --}}
@php
    $hubTab = $healthHub['tab'] ?? 'sante';
    $hubTabs = ['sante' => 'Santé', 'alimentation' => 'Alimentation', 'suivi' => 'Suivi', 'poids' => 'Poids'];
    $hubHorse = $healthHub['horse'] ?? null;
@endphp
<nav class="jc-sticky-strip no-scrollbar -mx-4 mb-3 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Sections de la santé" data-health-tabs>
    <div class="flex w-max gap-1.5 rounded-full bg-white p-1 shadow-sm ring-1 ring-slate-200/70">
        @foreach ($hubTabs as $key => $label)
            @php $params = $key === 'sante' ? [] : array_filter(['onglet' => $key, 'cheval' => $hubHorse?->id]); @endphp
            <a href="{{ route('health.index', $params) }}" @if ($hubTab === $key) aria-current="page" @endif
               class="pill min-h-10 px-4 {{ $hubTab === $key ? 'bg-brand-800 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}" data-health-tab="{{ $key }}">{{ $label }}</a>
        @endforeach
    </div>
</nav>
@if ($hubTab !== 'sante' && ($healthHub['horses'] ?? collect())->count() > 1)
    <nav class="no-scrollbar -mx-4 mb-5 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Cheval" data-health-horses>
        <div class="flex w-max gap-2">
            @foreach ($healthHub['horses'] as $h)
                <a href="{{ route('health.index', ['onglet' => $hubTab, 'cheval' => $h->id]) }}" @if ($hubHorse?->id === $h->id) aria-current="page" @endif
                   class="pill min-h-10 gap-2 pl-1.5 {{ $hubHorse?->id === $h->id ? 'bg-brand-50 text-brand-900 ring-2 ring-brand-500' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100' }}">
                    @if ($h->mainPhotoUrl())<img src="{{ $h->mainPhotoUrl() }}" alt="" class="size-7 rounded-full object-cover">@else<span class="flex size-7 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-800">{{ mb_strtoupper(mb_substr($h->shortName(), 0, 1)) }}</span>@endif
                    {{ $h->shortName() }}
                </a>
            @endforeach
        </div>
    </nav>
@endif
