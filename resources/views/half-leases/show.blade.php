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
