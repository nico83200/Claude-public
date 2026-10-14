<x-layouts.app title="Partages">
    <x-page-header title="Partages" subtitle="Demi-pensions, cavaliers et accès reçus" />
    @foreach ($invitations as $inv)
        <div class="mb-3 rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm">Invitation de <strong>{{ $inv->inviter->name }}</strong> : {{ $inv->type === 'horse_access' ? 'accès à '.$inv->horse?->shortName() : 'rejoindre '.$inv->organization?->name }}. Ouvrez le lien reçu par email pour l'accepter.</div>
    @endforeach
    <div class="grid gap-4 lg:grid-cols-2">
        <x-section title="Chevaux que je partage">
            @forelse ($managed as $horse)
                <div class="border-t border-slate-100 py-3 first:border-0">
                    <div class="flex items-center justify-between"><a href="{{ route('horses.show', $horse) }}" class="font-semibold link">{{ $horse->shortName() }}</a><a href="{{ route('horses.sharing', $horse) }}" class="btn-secondary text-sm">Gérer</a></div>
                    @forelse ($horse->accessGrants as $g)<p class="text-sm text-slate-600">{{ $g->user->name }} — {{ $g->label ?? 'Partage' }} {{ $g->expires_at ? '· jusqu\'au '.$g->expires_at->format('d/m/Y') : '' }}{{ $g->paused_at ? ' · suspendu par la personne invitée' : '' }}</p>@empty<p class="text-sm text-slate-400">Aucun partage actif.</p>@endforelse
                </div>
            @empty <p class="text-sm text-slate-500">Vous ne gérez aucun cheval.</p> @endforelse
        </x-section>
        <x-section title="Accès qui m'ont été donnés">
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
                            <span class="block text-xs text-slate-500">{{ count($g->permissions) }} droit(s){{ $g->starts_at?->isFuture() ? ' · à partir du '.$g->starts_at->format('d/m/Y') : '' }}{{ $g->expires_at ? ' · expire le '.$g->expires_at->format('d/m/Y') : '' }}</span>
                            @if ($g->paused_at)
                                <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Suspendu depuis le {{ $g->paused_at->format('d/m/Y') }}</span>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-wrap justify-end gap-1">
                            @if ($g->paused_at)
                                <form method="POST" action="{{ route('sharing.received.resume', $g) }}">@csrf<button class="btn-secondary min-h-9 px-3 text-sm">Reprendre</button></form>
                            @else
                                <form method="POST" action="{{ route('sharing.received.pause', $g) }}" onsubmit="return confirm(@js('Suspendre votre accès à '.$g->horse->shortName().' ? Le cheval n\'apparaîtra plus dans votre compte et vous ne recevrez plus ses rappels, jusqu\'à ce que vous le repreniez.'))">@csrf<button class="btn-ghost min-h-9 px-3 text-sm">Suspendre</button></form>
                            @endif
                            <form method="POST" action="{{ route('sharing.received.leave', $g) }}" onsubmit="return confirm(@js('Quitter définitivement le partage de '.$g->horse->shortName().' ? Pour le retrouver, le propriétaire devra vous inviter à nouveau.'))">@csrf<button class="btn-ghost min-h-9 px-3 text-sm text-red-600">Quitter</button></form>
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
