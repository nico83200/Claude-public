        {{-- À faire : une seule suggestion à la fois (démarrage, fiche d'urgence, notifications, offre), pour garder l'essentiel en haut. --}}
        <div class="mb-5" data-suggestions
             x-data="{ i: 0, ids: [], refresh() { this.ids = [...this.$el.querySelectorAll('[data-suggestion]')].filter((e) => e.dataset.available !== '0').map((e) => e.dataset.suggestion); if (this.i >= this.ids.length) this.i = 0; }, cur() { return this.ids[this.i]; }, next() { this.i = (this.i + 1) % this.ids.length; } }"
             x-init="$nextTick(() => refresh())" @suggestions-changed="$nextTick(() => refresh())" x-show="ids.length" x-cloak>
        @if ($onboarding)
            @php $doneCount = collect($onboarding)->where('done', true)->count(); $nextStep = collect($onboarding)->firstWhere('done', false); @endphp
            <section data-suggestion="onboarding" x-show="cur() === 'onboarding'" class="card p-5 sm:p-6" data-onboarding x-data="{ all: false }">
                <div class="mb-1 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-[17px] font-semibold">Bien démarrer avec Jack&amp;Cie</h2>
                        <p class="text-sm text-slate-500">{{ $doneCount }} étape{{ $doneCount > 1 ? 's' : '' }} sur {{ count($onboarding) }} · quelques minutes suffisent</p>
                    </div>
                    <form method="POST" action="{{ route('onboarding.dismiss') }}">@csrf<button class="btn-ghost min-h-9 px-2 text-sm text-slate-500" aria-label="Masquer le démarrage">Masquer</button></form>
                </div>
                <div class="mt-3 mb-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-brand-600 transition-all" style="width: {{ round($doneCount / count($onboarding) * 100) }}%"></div></div>
                <ol class="divide-y divide-slate-100">
                    @foreach ($onboarding as $step)
                        {{-- Seule la prochaine étape est affichée ; « Voir toutes les étapes » déplie la liste. --}}
                        <li class="flex items-center gap-3 py-3" data-onboarding-step="{{ $step['key'] }}" data-done="{{ $step['done'] ? '1' : '0' }}" @if (($nextStep['key'] ?? null) !== $step['key']) x-show="all" x-cloak @endif>
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $step['done'] ? 'bg-brand-600 text-white' : 'border-2 border-slate-300 text-slate-400' }}">@if ($step['done'])<x-icon name="check" class="size-4" />@else<span class="text-sm font-semibold">{{ $loop->iteration }}</span>@endif</span>
                            <span class="min-w-0 flex-1"><span class="block font-medium {{ $step['done'] ? 'text-slate-500' : 'text-slate-900' }}">{{ $step['title'] }}</span>@unless ($step['done'])<span class="block text-sm text-slate-500">{{ $step['text'] }}</span>@endunless</span>
                            @if (! $step['done'] && $step['url'])
                                <a href="{{ $step['url'] }}" class="btn-secondary min-h-9 shrink-0 px-4 text-sm">{{ $step['button'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ol>
                <button type="button" class="mt-1 text-sm font-medium text-brand-700" @click="all = ! all" x-text="all ? 'Réduire' : 'Voir toutes les étapes'">Voir toutes les étapes</button>
            </section>
        @endif
        @if ($emergencyCta)
                <section data-suggestion="emergency" x-show="cur() === 'emergency'" class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-red-100" data-emergency-cta>
                <div class="flex items-start gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-700"><x-icon name="alert" /></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-semibold text-slate-900">Préparez la fiche d'urgence de {{ $emergencyCta->shortName() }}</h2>
                        <p class="mt-0.5 text-sm text-slate-600">Un QR code à coller sur le box : en cas de problème, n'importe qui voit qui appeler, le vétérinaire, les traitements en cours et les précautions — sans compte.</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <a href="{{ route('horses.emergency.edit', $emergencyCta) }}" class="btn-primary min-h-10 px-4 text-sm">Préparer la fiche</a>
                            <form method="POST" action="{{ route('onboarding.emergency.dismiss') }}">@csrf<button class="btn-ghost min-h-10 px-3 text-sm text-slate-500" data-emergency-cta-dismiss>Ne plus afficher</button></form>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @if ($horses->isNotEmpty())
        {{-- Invitation à activer les notifications (application installée, pas encore autorisées) --}}
        <div x-data="{ show: false }" x-init="(() => { try { show = ! $store.install.handheld && 'Notification' in window && 'PushManager' in window && Notification.permission === 'default' && ! localStorage.getItem('jc-push-hint') } catch { } })()"
             data-suggestion="push" :data-available="show ? 1 : 0" x-effect="show; $dispatch('suggestions-changed')" x-show="show && cur() === 'push'" x-cloak class="flex items-center gap-3 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-800"><x-icon name="bell" /></span>
            <p class="min-w-0 flex-1 text-sm text-slate-700"><span class="block font-semibold text-slate-900">Ne ratez plus un bilan</span>Recevez un rappel sur cet appareil après vos séances et vos cours.</p>
            <a href="{{ route('settings.notifications') }}" class="btn-primary min-h-10 shrink-0 px-4">Activer</a>
            <button type="button" class="shrink-0 p-1 text-slate-400" aria-label="Plus tard" @click="show = false; try { localStorage.setItem('jc-push-hint', '1') } catch { }"><x-icon name="x" class="size-4" /></button>
        </div>

        {{-- Offre gratuite : proposition d'offre supérieure (masquable 7 jours) --}}
        @if ($upsell)
            <div x-data="{ show: true }" x-init="(() => { try { show = (+localStorage.getItem('jc-upsell-later') || 0) < Date.now() } catch { } })()" data-suggestion="upsell" :data-available="show ? 1 : 0" x-effect="show; $dispatch('suggestions-changed')" x-show="show && cur() === 'upsell'" class="relative">
                <x-upsell :offer="$upsell" />
                <button type="button" class="absolute top-3 right-3 p-1.5 text-slate-400 hover:text-slate-700" aria-label="Plus tard"
                        @click="show = false; try { localStorage.setItem('jc-upsell-later', Date.now() + 7 * 864e5) } catch { }"><x-icon name="x" class="size-4" /></button>
            </div>
        @endif

        @endif
            <div x-show="ids.length > 1" class="mt-2 flex items-center justify-between px-1 text-sm text-slate-500">
                <span x-text="'Suggestion ' + (i + 1) + ' sur ' + ids.length"></span>
                <button type="button" class="inline-flex min-h-9 items-center gap-1 font-medium text-brand-700" @click="next()">Suivante <x-icon name="chevron" class="size-4" /></button>
            </div>
        </div>
