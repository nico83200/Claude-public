<x-layouts.app title="Partages">
    <x-page-header title="Partages" subtitle="Demi-pensions, cavaliers et accès reçus" />
    @php $labels = \App\Support\Perm::horseLabels(); @endphp
    {{-- Téléphone : raccourcis fixés sous l'en-tête. --}}
    <nav class="jc-sticky-strip no-scrollbar -mx-4 mb-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Sections" data-sharing-nav>
        @if ($invitations->isNotEmpty())<a href="#en-attente" class="pill shrink-0 bg-red-50 text-red-800 ring-1 ring-red-200">En attente ({{ $invitations->count() }})</a>@endif
        <a href="#je-partage" class="pill shrink-0 bg-white text-slate-700 ring-1 ring-slate-200">Je partage ({{ $managed->count() }})</a>
        <a href="#recus" class="pill shrink-0 bg-white text-slate-700 ring-1 ring-slate-200">Reçus ({{ $received->count() }})</a>
    </nav>
    @if ($invitations->isNotEmpty())
        <section id="en-attente" class="mb-4 scroll-mt-32" data-pending-invitations>
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-slate-900">{{ \App\Support\Fr::count($invitations->count(), 'invitation en attente', 'invitations en attente') }}</h2>
                @if ($invitations->count() - $halfLeaseInvitations->count() > 1)
                    <form method="POST" action="{{ route('invitations.accept-all') }}" data-confirm="Accepter les {{ $invitations->count() - $halfLeaseInvitations->count() }} invitations ? (Les demi-pensions s'acceptent une à une, depuis leur fiche.)" data-confirm-ok="Tout accepter" data-confirm-tone="neutral">@csrf<button class="btn-secondary min-h-10" data-accept-all>Tout accepter</button></form>
                @endif
            </div>
            @foreach ($invitations as $inv)
                @if ($lease = $halfLeaseInvitations[$inv->id] ?? null)
                    <div class="mb-3 rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm" data-invitation="{{ $inv->id }}" data-half-lease-invitation="{{ $lease->id }}">
                        <p><strong>{{ $inv->inviter->name }}</strong> vous propose une demi-pension sur <strong>{{ $inv->horse?->shortName() }}</strong>.</p>
                        <p class="mt-1 text-slate-600">{{ app(\App\Services\HalfLeases::class)->summary($lease) }}</p>
                        <a href="{{ route('half-leases.show', $lease) }}" class="btn-primary mt-3 min-h-11 w-full justify-center">Voir la proposition</a>
                    </div>
                    @continue
                @endif
                <div class="mb-3 rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm" data-invitation="{{ $inv->id }}">
                    <p><strong>{{ $inv->inviter->name }}</strong> {{ $inv->type === 'horse_access' ? 'vous donne accès à '.$inv->horse?->shortName() : 'vous invite à rejoindre '.$inv->organization?->name }}.</p>
                    @if ($inv->type === 'horse_access')
                        <details class="mt-1 text-xs text-slate-600">
                            <summary class="cursor-pointer">{{ \App\Support\Fr::count(count($inv->permissions ?? []), 'droit', 'droits') }}{{ $inv->access_expires_at ? ' · jusqu\'au '.$inv->access_expires_at->format('d/m/Y') : '' }}</summary>
                            <span class="mt-1 flex flex-wrap gap-1">@foreach ($inv->permissions ?? [] as $perm)<span class="chip bg-white text-slate-700 ring-1 ring-slate-200">{{ $labels[$perm] ?? $perm }}</span>@endforeach</span>
                        </details>
                    @endif
                    <div class="mt-3 flex gap-2">
                        <form method="POST" action="{{ route('invitations.respond', $inv) }}" class="flex-1">@csrf<input type="hidden" name="decision" value="accept"><input type="hidden" name="stay" value="1"><button class="btn-primary min-h-11 w-full justify-center">Accepter</button></form>
                        <form method="POST" action="{{ route('invitations.respond', $inv) }}" class="flex-1">@csrf<input type="hidden" name="decision" value="decline"><button class="btn-secondary min-h-11 w-full justify-center">Refuser</button></form>
                    </div>
                </div>
            @endforeach
        </section>
    @endif
    <div class="grid gap-4 lg:grid-cols-2">
        <x-section title="Chevaux que je partage" id="je-partage" class="scroll-mt-32">
            @forelse ($managed as $horse)
                <div class="border-t border-slate-100 py-3 first:border-0">
                    <div class="flex items-center justify-between"><a href="{{ route('horses.show', $horse) }}" class="font-semibold link">{{ $horse->shortName() }}</a><a href="{{ route('horses.sharing', $horse) }}" class="btn-secondary text-sm">Gérer</a></div>
                    @forelse ($horse->accessGrants as $g)<p class="text-sm text-slate-600">{{ $g->user->name }} — {{ $g->label ?? 'Partage' }} {{ $g->expires_at ? '· jusqu\'au '.$g->expires_at->format('d/m/Y') : '' }}{{ $g->paused_at ? ' · suspendu par la personne invitée' : '' }}</p>@empty<p class="text-sm text-slate-400">Aucun partage actif.</p>@endforelse
                </div>
            @empty <p class="text-sm text-slate-500">Vous ne gérez aucun cheval.</p> @endforelse
        </x-section>
        <x-section title="Accès qui m'ont été donnés" id="recus" class="scroll-mt-32">
            @forelse ($received as $g)
                <div class="border-t border-slate-100 py-3 text-sm first:border-0" data-received-grant>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            @if ($g->isActive())
                                <a href="{{ route('horses.show', $g->horse) }}" class="font-medium link">{{ $g->horse->shortName() }}</a>
                            @else
                                <span class="font-medium">{{ $g->horse->shortName() }}</span>
                            @endif
                            <span class="text-slate-600"> — par {{ $g->grantor?->name }}</span>
                            <span class="block text-xs text-slate-500">{{ $g->label && $g->label !== 'Partage' ? $g->label.' · ' : '' }}{{ count($g->permissions) }} droit(s){{ $g->starts_at?->isFuture() ? ' · à partir du '.$g->starts_at->format('d/m/Y') : '' }}{{ $g->expires_at ? ' · expire le '.$g->expires_at->format('d/m/Y') : '' }}</span>
                            @if ($leaseId = $halfLeaseGrants[$g->id] ?? null)
                                <a href="{{ route('half-leases.show', $leaseId) }}" class="mt-1 inline-flex text-xs font-medium link" data-half-lease-link>Fiche de la demi-pension</a>
                            @endif
                            @if ($g->paused_at)
                                <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Suspendu depuis le {{ $g->paused_at->format('d/m/Y') }}</span>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-wrap justify-end gap-1">
                            @if ($g->paused_at)
                                <form method="POST" action="{{ route('sharing.received.resume', $g) }}">@csrf<button class="btn-secondary min-h-9 px-3 text-sm">Reprendre</button></form>
                            @else
                                <form method="POST" action="{{ route('sharing.received.pause', $g) }}" data-confirm="{{ 'Suspendre votre accès à '.$g->horse->shortName().' ? Le cheval n\'apparaîtra plus dans votre compte et vous ne recevrez plus ses rappels, jusqu\'à ce que vous le repreniez.' }}" data-confirm-ok="Suspendre" data-confirm-tone="neutral">@csrf<button class="btn-ghost min-h-9 px-3 text-sm">Suspendre</button></form>
                            @endif
                            <form method="POST" action="{{ route('sharing.received.leave', $g) }}" data-confirm="{{ 'Quitter définitivement le partage de '.$g->horse->shortName().' ? Pour le retrouver, le propriétaire devra vous inviter à nouveau.' }}" data-confirm-ok="Quitter le partage">@csrf<button class="btn-ghost min-h-9 px-3 text-sm text-red-600">Quitter</button></form>
                        </div>
                    </div>
                </div>
            @empty <p class="text-sm text-slate-500">Aucun accès partagé.</p> @endforelse
            @if ($received->isNotEmpty())
                <p class="mt-3 text-xs text-slate-500">« Suspendre » met l'accès en pause (vous pouvez le reprendre) ; « Quitter » y met fin définitivement. Le propriétaire est prévenu.</p>
            @endif
        </x-section>
    </div>
</x-layouts.app>
