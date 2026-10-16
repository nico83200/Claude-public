    @foreach ($invitations as $inv)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm">
            <span><strong>{{ $inv->inviter->name }}</strong> vous invite {{ $inv->type === 'horse_access' ? 'à accéder au dossier de '.$inv->horse?->shortName() : 'à rejoindre '.$inv->organization?->name }}.</span>
            <span class="flex gap-2">
                <form method="POST" action="{{ route('invitations.respond', $inv) }}">@csrf<input type="hidden" name="decision" value="decline"><button class="btn-ghost">Refuser</button></form>
                <form method="POST" action="{{ route('invitations.respond', $inv) }}">@csrf<input type="hidden" name="decision" value="accept"><button class="btn-primary">Accepter</button></form>
            </span>
        </div>
    @endforeach
    @foreach ($pendingAssignments as $a)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
            <span>Demande de prise en charge de <strong>{{ $a->horse->shortName() }}</strong> par {{ $a->organization->name }}.</span>
            <a href="{{ route('organization.show') }}" class="btn-secondary">Examiner</a>
        </div>
    @endforeach
