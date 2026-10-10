<x-layouts.app :title="$user->name.' · Admin'">
    <x-page-header :title="$user->name" :subtitle="$user->email" :back="route('admin.users.index')" />
    @include('admin._nav')
    <div class="grid gap-4 lg:grid-cols-3">
        <x-section title="Compte">
            <x-dl :items="['Inscription' => $user->created_at->format('d/m/Y H:i'), 'Email vérifié' => $user->email_verified_at?->format('d/m/Y') ?? 'Non', 'Dernière connexion' => $user->last_login_at?->format('d/m/Y H:i'), '2FA' => $user->hasConfirmedTwoFactor() ? 'Activée' : 'Non', 'Suspension' => $user->suspended_at ? $user->suspended_at->format('d/m/Y').' — '.$user->suspension_reason : null, 'Suppression demandée' => $user->deletion_requested_at?->format('d/m/Y')]" />
            <div class="mt-4">
                @if ($user->suspended_at)
                    <form method="POST" action="{{ route('admin.users.reactivate', $user) }}">@csrf<button class="btn-primary w-full">Réactiver</button></form>
                @elseif (! $user->is_super_admin)
                    <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="space-y-2">@csrf<x-field name="reason" label="Motif de suspension" required /><button class="btn-danger w-full">Suspendre</button></form>
                @else
                    <p class="text-xs text-slate-500">Super-administrateur : géré uniquement en ligne de commande.</p>
                @endif
            </div>
        </x-section>
        <x-section title="Espaces">
            @foreach ($user->memberships as $m)<a href="{{ route('admin.organizations.show', $m->organization_id) }}" class="block py-1 text-sm link">{{ $m->organization?->name }} <span class="text-slate-500">({{ $m->role->name }})</span></a>@endforeach
        </x-section>
        <x-section title="Journal récent">
            @foreach ($logs as $l)<p class="text-xs"><span class="text-slate-500">{{ $l->created_at->format('d/m H:i') }}</span> {{ $l->action }}</p>@endforeach
        </x-section>
    </div>
</x-layouts.app>
