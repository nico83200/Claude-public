@php $fmt = fn ($d) => $d ? \App\Support\Fr::day($d) : null; @endphp
<x-layouts.app :title="'Demi-pension · '.$horse->shortName()">
    <x-page-header :title="'Demi-pension de '.$lease->riderName()" :subtitle="$horse->shortName().($manager ? '' : ' · proposée par '.$lease->creator?->name)" :back="$manager ? route('horses.sharing', $horse) : route('sharing.index')">
        @if ($manager && $lease->isOpen())<a href="{{ route('half-leases.edit', $lease) }}" class="btn-secondary"><x-icon name="pencil" class="size-4" /> Modifier</a>@endif
    </x-page-header>
    @include('sharing._invite-link', ['inviteHorse' => $horse, 'inviteIntro' => 'Je te propose la demi-pension de '.$horse->shortName().' sur Jack&Cie : '.$summary.'.'])

    <div class="grid max-w-3xl gap-4">
        {{-- Proposition en attente : la personne accepte ou refuse ; le propriétaire peut relancer ou annuler. --}}
        @if ($lease->status === 'proposed')
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" data-half-lease-proposal>
                @if ($rider && ! $manager)
                    <p class="font-semibold">{{ $lease->creator?->name }} vous propose cette demi-pension.</p>
                    <p class="mt-1">En acceptant, vous accédez au dossier de {{ $horse->shortName() }} avec les droits ci-dessous, et vos jours habituels s'inscrivent dans les jours de monte à partir du {{ $fmt($lease->starts_on) }}.</p>
                    @if ($pendingInvitation)
                        <div class="mt-3 flex gap-2">
                            <form method="POST" action="{{ route('invitations.respond', $pendingInvitation) }}" class="flex-1">@csrf<input type="hidden" name="decision" value="accept"><button class="btn-primary min-h-11 w-full justify-center" data-half-lease-accept>Accepter</button></form>
                            <form method="POST" action="{{ route('invitations.respond', $pendingInvitation) }}" class="flex-1" data-confirm="Refuser cette demi-pension ?" data-confirm-ok="Refuser">@csrf<input type="hidden" name="decision" value="decline"><button class="btn-secondary min-h-11 w-full justify-center">Refuser</button></form>
                        </div>
                    @else
                        <p class="mt-2">Cette proposition a expiré : demandez à {{ $lease->creator?->name }} de vous la renvoyer.</p>
                    @endif
                @else
                    <p class="font-semibold">En attente de la réponse de {{ $lease->name }}</p>
                    <p class="mt-1">Proposition envoyée à {{ $lease->email }}{{ $lease->invitation ? ' le '.$lease->invitation->created_at->format('d/m/Y') : '' }}{{ $pendingInvitation ? ', valable jusqu\'au '.$pendingInvitation->expires_at->format('d/m/Y') : ' — lien expiré' }}. Son accès s'ouvrira dès son accord.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('half-leases.resend', $lease) }}">@csrf<button class="btn-secondary min-h-11"><x-icon name="send" class="size-4" /> Renvoyer</button></form>
                        <form method="POST" action="{{ route('half-leases.cancel', $lease) }}" data-confirm="Annuler la proposition ? Le lien envoyé ne fonctionnera plus." data-confirm-ok="Annuler la proposition">@csrf<button class="btn-ghost min-h-11 text-red-700">Annuler la proposition</button></form>
                    </div>
                @endif
            </div>
        @endif

        <x-section title="Conditions" data-half-lease-terms>
            <div class="mb-3"><x-badge :color="$lease->statusColor()">{{ $lease->statusLabel() }}</x-badge></div>
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-xs text-slate-500">Début</dt><dd class="font-medium text-slate-900">{{ $fmt($lease->starts_on) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Période d'essai</dt><dd class="font-medium text-slate-900">{{ $lease->trial_ends_on ? 'Jusqu\'au '.$fmt($lease->trial_ends_on) : 'Aucune' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Fin</dt><dd class="font-medium text-slate-900">{{ $lease->ends_on ? $fmt($lease->ends_on) : 'Sans date de fin' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Préavis</dt><dd class="font-medium text-slate-900">{{ \App\Models\HalfLease::NOTICES[$lease->notice_days] ?? 'Aucun' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Jours habituels</dt><dd class="font-medium text-slate-900">{{ $lease->weekdays ? ucfirst($lease->daysLabel()) : 'À convenir' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Tarif</dt><dd class="font-medium text-slate-900">{{ $lease->priceLabel() ?? 'Non précisé' }}</dd></div>
            </dl>
            @if ($lease->notes)<p class="mt-3 rounded-2xl bg-slate-50 p-3 text-sm whitespace-pre-line text-slate-700">{{ $lease->notes }}</p>@endif
            @if ($lease->status === 'active')
                <a href="{{ route('horses.riding-days', $horse) }}" class="btn-ghost mt-3 min-h-10 px-2 text-sm"><x-icon name="calendar" class="size-4" /> Jours de monte</a>
            @endif
        </x-section>

        {{-- Mensualités : budget du propriétaire (noté « Reçu » par lui seul) ou relevé de l'écurie. --}}
        @if ((float) $lease->monthly_amount > 0 && $lease->status !== 'proposed')
            @php $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' €'; @endphp
            <x-section title="Mensualités" data-half-lease-fees>
                @if ($next)
                    <p class="mb-2 text-sm text-slate-600">Prochaine mensualité : <strong class="text-slate-900">{{ $eur($next['amount']) }}</strong> le {{ \App\Support\Fr::day($next['due']) }}.</p>
                @endif
                @if ($viaStatement)<p class="mb-2 text-sm text-slate-600">Facturée chaque mois sur le relevé de l'écurie, avec les cours.</p>@endif
                @forelse ($fees as $fee)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 py-2.5 text-sm first:border-0" data-half-lease-fee>
                        <span class="min-w-0"><span class="block font-medium text-slate-900">{{ $fee['month'] }}</span>
                            <span class="block text-xs text-slate-500">{{ $fee['statement'] ? 'Relevé n° '.$fee['statement']->number : 'Échéance du '.$fee['due']?->format('d/m/Y') }}</span></span>
                        <span class="flex shrink-0 items-center gap-2">
                            <span class="whitespace-nowrap tabular-nums">{{ $eur($fee['amount']) }}</span>
                            @if ($fee['paid'])
                                <x-badge color="green">{{ $fee['paid_on'] ? 'Reçue le '.$fee['paid_on']->format('d/m') : 'Payée' }}</x-badge>
                            @else
                                <x-badge :color="$fee['due'] && $fee['due']->lt(today()->subDays(\App\Services\HalfLeaseFees::OVERDUE_DAYS)) ? 'red' : 'amber'">{{ $manager ? 'À recevoir' : 'À régler' }}</x-badge>
                                @if ($canReceive && $fee['income'])
                                    <form method="POST" action="{{ route('budget.incomes.receive', $fee['income']) }}">@csrf<input type="hidden" name="received" value="1"><button class="btn-secondary min-h-9 px-3 text-sm" data-fee-receive>Reçu</button></form>
                                @endif
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ $viaStatement ? 'Les mensualités apparaîtront avec les relevés envoyés par l\'écurie.' : 'La première mensualité arrivera à son échéance.' }}</p>
                @endforelse
                @if (! $manager)<p class="mt-2 text-xs text-slate-500">{{ $viaStatement ? 'Payée quand l\'écurie note le relevé réglé.' : 'Notée reçue par '.$lease->creator?->name.' à réception de votre paiement.' }}</p>@endif
                @if ($manager && $lease->isRunning())
                    {{-- Mois sans mensualité : vacances, cheval au repos… --}}
                    @php $months = collect(range(0, 11))->mapWithKeys(fn ($i) => [today()->startOfMonth()->addMonthsNoOverflow($i)->format('Y-m') => ucfirst(today()->startOfMonth()->addMonthsNoOverflow($i)->translatedFormat('F Y'))])->except($lease->skipped_months ?? [])->except($fees->where('paid', true)->map(fn ($f) => $f['due']?->format('Y-m'))->filter()->all()); @endphp
                    <form method="POST" action="{{ route('half-leases.skip-month', $lease) }}" class="mt-3 flex flex-wrap items-end gap-2 rounded-2xl bg-slate-50 p-3" data-skip-month>
                        @csrf
                        <x-select name="month" label="Mois sans mensualité" :options="$months" class="min-w-44 flex-1" />
                        <button class="btn-secondary min-h-11">Ne pas compter ce mois</button>
                    </form>
                    @foreach ($lease->skipped_months ?? [] as $m)
                        <form method="POST" action="{{ route('half-leases.skip-month', $lease) }}" class="mt-1 flex items-center justify-between gap-2 text-sm">@csrf<input type="hidden" name="month" value="{{ $m }}"><input type="hidden" name="restore" value="1">
                            <span class="text-slate-600">Sans mensualité : {{ \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $m.'-01')->translatedFormat('F Y') }}</span><button class="btn-ghost min-h-9 px-2 text-sm">Rétablir</button></form>
                    @endforeach
                @endif
            </x-section>
        @endif

        <x-section title="Contacts">
            <ul class="space-y-1 text-sm">
                <li><span class="text-slate-500">Demi-pension :</span> <span class="font-medium">{{ $lease->riderName() }}</span> · <a href="mailto:{{ $lease->email }}" class="link">{{ $lease->email }}</a>@if ($lease->phone) · <a href="tel:{{ preg_replace('/[^0-9+]/', '', $lease->phone) }}" class="link">{{ $lease->phone }}</a>@endif</li>
                <li><span class="text-slate-500">Propriétaire :</span> <span class="font-medium">{{ $lease->creator?->name }}</span>@if ($lease->creator) · <a href="mailto:{{ $lease->creator->email }}" class="link">{{ $lease->creator->email }}</a>@endif</li>
            </ul>
        </x-section>

        <x-section title="Accès au dossier">
            <p class="text-sm text-slate-600">{{ $lease->status === 'proposed' ? 'Après acceptation' : 'Droits' }} :</p>
            <span class="mt-2 flex flex-wrap gap-1">@foreach (app(\App\Services\HalfLeases::class)->permissions($lease) as $perm)<span class="chip bg-slate-100 text-slate-700">{{ $labels[$perm] ?? $perm }}</span>@endforeach</span>
            <p class="mt-2 text-xs text-slate-500">Le budget du cheval reste réservé au propriétaire.</p>
        </x-section>

        {{-- Fin : par l'un ou l'autre, dernier jour au choix (préavis proposé). --}}
        @if ($lease->isRunning() && ($manager || $lease->user_id === auth()->id()))
            <x-section title="Mettre fin à la demi-pension" data-half-lease-end>
                <form method="POST" action="{{ route('half-leases.end', $lease) }}" class="flex flex-wrap items-end gap-2" data-confirm="Mettre fin à la demi-pension ? L'accès au dossier s'arrêtera le soir du dernier jour." data-confirm-ok="Mettre fin">
                    @csrf
                    <x-field name="last_day" type="date" label="Dernier jour" :value="$lease->noticeDate()->format('Y-m-d')" :min="today()->format('Y-m-d')" class="min-w-44 flex-1" required />
                    <button class="btn-secondary min-h-11 text-red-700">Mettre fin</button>
                </form>
                <p class="mt-2 text-xs text-slate-500">{{ $lease->notice_days ? 'Date proposée selon le préavis ('.\App\Models\HalfLease::NOTICES[$lease->notice_days].').' : 'Aucun préavis convenu.' }} Les jours habituels redeviennent libres après ; l'historique reste.</p>
            </x-section>
        @endif
    </div>
</x-layouts.app>
