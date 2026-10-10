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
                    @forelse ($horse->accessGrants as $g)<p class="text-sm text-slate-600">{{ $g->user->name }} — {{ $g->label ?? 'Partage' }} {{ $g->expires_at ? '· jusqu\'au '.$g->expires_at->format('d/m/Y') : '' }}</p>@empty<p class="text-sm text-slate-400">Aucun partage actif.</p>@endforelse
                </div>
            @empty <p class="text-sm text-slate-500">Vous ne gérez aucun cheval.</p> @endforelse
        </x-section>
        <x-section title="Accès qui m'ont été donnés">
            @forelse ($received as $g)
                <a href="{{ route('horses.show', $g->horse) }}" class="block border-t border-slate-100 py-2 text-sm first:border-0"><span class="font-medium">{{ $g->horse->shortName() }}</span> — par {{ $g->grantor?->name }}<span class="block text-xs text-slate-500">{{ count($g->permissions) }} droit(s) {{ $g->expires_at ? '· expire le '.$g->expires_at->format('d/m/Y') : '' }}</span></a>
            @empty <p class="text-sm text-slate-500">Aucun accès partagé.</p> @endforelse
        </x-section>
    </div>
</x-layouts.app>
