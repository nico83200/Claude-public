<x-layouts.guest title="Invitation">
    <h1 class="mb-2 text-xl font-bold">Invitation</h1>
    @if (! $invitation->isPending())
        <p class="text-sm text-slate-600">Cette invitation n'est plus valable (acceptée, annulée ou expirée).</p>
    @else
        <p class="text-sm text-slate-700"><strong>{{ $invitation->inviter->name }}</strong>
            @if ($invitation->type === 'horse_access') vous invite à accéder au dossier du cheval <strong>{{ $invitation->horse->shortName() }}</strong>.
            @else vous invite à rejoindre <strong>{{ $invitation->organization->name }}</strong> en tant que « {{ $invitation->role?->name }} ».@endif
        </p>
        @if ($invitation->type === 'horse_access')
            <ul class="mt-3 list-disc pl-5 text-sm text-slate-600">@foreach ($invitation->permissions as $p)<li>{{ \App\Support\Perm::horseLabels()[$p] ?? $p }}</li>@endforeach</ul>
            @if ($invitation->access_expires_at)<p class="mt-2 text-xs text-slate-500">Accès jusqu'au {{ $invitation->access_expires_at->format('d/m/Y') }}.</p>@endif
        @endif
        <p class="mt-3 text-xs text-slate-500">Invitation envoyée à {{ $invitation->email }}.</p>
        @auth
            @if (strtolower(auth()->user()->email) === strtolower($invitation->email))
                <div class="mt-5 flex gap-2">
                    <form method="POST" action="{{ route('invitations.accept', $token) }}" class="flex-1">@csrf<button class="btn-primary w-full">Accepter</button></form>
                    <form method="POST" action="{{ route('invitations.decline', $token) }}">@csrf<button class="btn-ghost">Refuser</button></form>
                </div>
            @else
                <p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Vous êtes connecté avec {{ auth()->user()->email }}. Déconnectez-vous et connectez-vous avec l'adresse invitée.</p>
            @endif
        @else
            <div class="mt-5 grid gap-2"><a href="{{ route('register') }}" class="btn-primary">Créer mon compte</a><a href="{{ route('login') }}" class="btn-secondary">J'ai déjà un compte</a></div>
        @endauth
    @endif
</x-layouts.guest>
