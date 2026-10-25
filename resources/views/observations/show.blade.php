<x-layouts.app :title="$point->label().' · '.$horse->shortName()">
    @php
        $sevColor = ['info' => 'slate', 'watch' => 'amber', 'alert' => 'red'][$point->severity];
        $trendColor = ['better' => 'green', 'same' => 'slate', 'worse' => 'amber'];
        $sms = 'Bonjour, c\'est '.auth()->user()->name.' pour '.$horse->shortName().' : '.$point->label().'. Pouvez-vous me rappeler ? Merci.';
    @endphp
    <x-page-header :title="$point->label()" :subtitle="$horse->shortName().' · point d\'attention ouvert le '.$point->observed_at->translatedFormat('j F à H:i').($point->author ? ' par '.$point->author->name : '')" :back="route('horses.care.index', $horse)">
        <x-badge :color="$sevColor">{{ \App\Models\HealthObservation::SEVERITIES[$point->severity] }}</x-badge>
        @if ($point->isOpen())<x-badge color="blue" data-attention-status="open">Suivi en cours</x-badge>@else<x-badge color="green" data-attention-status="closed">Clos le {{ $point->resolved_at->format('d/m/Y') }}</x-badge>@endif
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-5 lg:items-start">
        <div class="order-2 space-y-4 lg:order-1 lg:col-span-3">
            {{-- Historique : le départ, chaque nouvelle, la clôture. --}}
            <x-section title="Historique" data-attention-history>
                <ol class="space-y-4">
                    <li class="relative border-l-2 border-slate-200 pl-4">
                        <p class="text-xs text-slate-500">{{ $point->observed_at->translatedFormat('l j F, H:i') }} · {{ $point->author?->name }}</p>
                        <p class="mt-1 text-sm whitespace-pre-line text-slate-800">{{ $point->body }}</p>
                        @include('observations._photos', ['photos' => $point->photos->whereNull('health_observation_update_id')])
                    </li>
                    @foreach ($point->updates as $u)
                        <li class="relative border-l-2 border-slate-200 pl-4" data-attention-update>
                            <p class="text-xs text-slate-500">{{ $u->created_at->translatedFormat('l j F, H:i') }} · {{ $u->author?->name }}</p>
                            @if ($u->trend)<x-badge :color="$trendColor[$u->trend]" class="mt-1">{{ \App\Models\HealthObservation::TRENDS[$u->trend] }}</x-badge>@endif
                            @if ($u->body)<p class="mt-1 text-sm whitespace-pre-line text-slate-800">{{ $u->body }}</p>@endif
                            @include('observations._photos', ['photos' => $u->photos])
                        </li>
                    @endforeach
                    @unless ($point->isOpen())
                        <li class="relative border-l-2 border-brand-300 pl-4" data-attention-closed>
                            <p class="text-xs text-slate-500">{{ $point->resolved_at->translatedFormat('l j F, H:i') }} · {{ $point->resolver?->name }}</p>
                            <p class="mt-1 text-sm font-semibold text-brand-800">Rentré dans l'ordre</p>
                            @if ($point->resolution_note)<p class="text-sm whitespace-pre-line text-slate-700">{{ $point->resolution_note }}</p>@endif
                        </li>
                    @endunless
                </ol>
            </x-section>

            @if ($canUpdate)
                <x-section title="Donner des nouvelles">
                    <form method="POST" action="{{ route('horses.observations.updates.store', [$horse, $point]) }}" enctype="multipart/form-data" class="space-y-3" data-attention-news>
                        @csrf
                        <div class="grid gap-2 sm:grid-cols-3">
                            @foreach (\App\Models\HealthObservation::TRENDS as $key => $label)
                                <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-2xl px-3 ring-1 ring-slate-200 has-[:checked]:bg-brand-50 has-[:checked]:ring-2 has-[:checked]:ring-brand-500">
                                    <input type="radio" name="trend" value="{{ $key }}" @checked(old('trend') === $key) class="accent-brand-700"> <span class="text-sm font-medium">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <x-textarea name="body" rows="3" aria-label="Nouvelle" placeholder="Ce qui a changé, ce que vous avez fait, ce qu'a dit le vétérinaire…" />
                        @include('observations._photo-input', ['id' => 'f_news_photos'])
                        <button class="btn-primary min-h-12 w-full">Ajouter cette nouvelle</button>
                    </form>
                </x-section>
            @endif

            @if ($canClose)
                @if ($point->isOpen())
                    <details class="card p-5" data-attention-close>
                        <summary class="flex min-h-11 cursor-pointer items-center font-semibold">C'est rentré dans l'ordre ?</summary>
                        <form method="POST" action="{{ route('horses.observations.resolve', [$horse, $point]) }}" class="mt-3 space-y-3">
                            @csrf
                            <x-textarea name="note" rows="2" aria-label="Un mot pour clore" placeholder="Un mot pour l'historique (facultatif)" />
                            <button class="btn-secondary min-h-12 w-full">Clore le point d'attention</button>
                        </form>
                    </details>
                @else
                    <form method="POST" action="{{ route('horses.observations.reopen', [$horse, $point]) }}">@csrf<button class="btn-ghost min-h-11">Rouvrir le suivi</button></form>
                @endif
            @endif

            @if ($others->isNotEmpty())
                <x-section title="Autres points d'attention de {{ $horse->shortName() }}">
                    @foreach ($others as $o)
                        <a href="{{ route('horses.observations.show', [$horse, $o]) }}" class="flex min-h-11 items-center justify-between gap-2 border-t border-slate-100 py-2 text-sm first:border-0">
                            <span class="truncate">{{ $o->label() }}</span>
                            <span class="shrink-0 text-xs text-slate-500">{{ $o->observed_at->format('d/m/Y') }}{{ $o->resolved_at ? ' · clos' : ' · en cours' }}</span>
                        </a>
                    @endforeach
                </x-section>
            @endif
        </div>

        <div class="order-1 space-y-4 lg:order-2 lg:col-span-2">
            @if ($point->isOpen())@include('observations._calm', ['severity' => $point->severity])@endif
            @include('observations._vet', ['vet' => $vet, 'horse' => $horse, 'sms' => $sms])
        </div>
    </div>
</x-layouts.app>
