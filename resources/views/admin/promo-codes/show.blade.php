<x-layouts.app :title="$promo->code.' · Codes promo'">
    <x-page-header :title="$promo->code" :subtitle="$promo->label"><a href="{{ route('admin.promo-codes.index') }}" class="btn-ghost">← Tous les codes</a></x-page-header>
    @include('admin._nav')
    <div class="grid gap-4 lg:grid-cols-3">
        <x-section title="Code">
            <dl class="grid grid-cols-2 gap-y-1 text-sm">
                <dt class="text-slate-500">Type</dt><dd>{{ \App\Models\PromoCode::TYPES[$promo->type] }}</dd>
                <dt class="text-slate-500">Avantage</dt><dd>{{ $promo->summary() }}</dd>
                @if ($plans->isNotEmpty())<dt class="text-slate-500">Offres</dt><dd>{{ $plans->implode(', ') }}</dd>@endif
                <dt class="text-slate-500">Utilisations</dt><dd>{{ $promo->redemptions_count }}{{ $promo->max_redemptions ? ' / '.$promo->max_redemptions : '' }}</dd>
                <dt class="text-slate-500">Validité</dt><dd>{{ $promo->starts_at?->format('d/m/Y') ?? '—' }} → {{ $promo->expires_at?->format('d/m/Y') ?? '∞' }}</dd>
                <dt class="text-slate-500">Nouveaux clients</dt><dd>{{ $promo->first_subscription_only ? 'Oui' : 'Non' }}</dd>
                @if ($promo->stripe_promotion_code_id)<dt class="text-slate-500">Stripe</dt><dd class="break-all font-mono text-xs">{{ $promo->stripe_promotion_code_id }}</dd>@endif
                <dt class="text-slate-500">Créé</dt><dd>{{ $promo->created_at->format('d/m/Y') }}{{ $promo->author ? ' par '.$promo->author->name : '' }}</dd>
            </dl>
            <p class="mt-3">@if ($r = $promo->unavailableReason())<x-badge color="amber">{{ $r }}</x-badge>@else<x-badge color="green">Valable</x-badge>@endif</p>
            <form method="POST" action="{{ route('admin.promo-codes.update', $promo) }}" class="mt-3">
                @csrf @method('PATCH')
                <input type="hidden" name="is_active" value="{{ $promo->is_active ? 0 : 1 }}">
                <button class="{{ $promo->is_active ? 'btn-danger' : 'btn-primary' }}">{{ $promo->is_active ? 'Désactiver le code' : 'Réactiver le code' }}</button>
            </form>
        </x-section>
        <x-section title="Provenance (QR codes et liens)" class="lg:col-span-3 lg:order-first">
            @include('admin._acquisition', ['rows' => $acquisition])
            <div class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                @foreach (['affiche' => 'Affiches', 'instagram' => 'Instagram'] as $src => $label)
                    <p class="rounded-xl bg-slate-50 px-3 py-2"><span class="font-semibold">Lien {{ $label }} :</span> <span class="break-all font-mono">{{ url('/register').'?code='.$promo->code.'&src='.$src }}</span></p>
                @endforeach
            </div>
        </x-section>
        <x-section title="Utilisations" class="lg:col-span-2">
            @forelse ($redemptions as $r)
                <p class="border-t border-slate-100 py-2 text-sm first:border-0">{{ $r->created_at->format('d/m/Y H:i') }} · <a href="{{ route('admin.organizations.show', $r->organization_id) }}" class="link">{{ $r->organization?->name ?? 'Espace supprimé' }}</a>{{ $r->user ? ' · '.$r->user->name : '' }}</p>
            @empty <p class="text-sm text-slate-500">Pas encore utilisé.</p> @endforelse
            {{ $redemptions->links() }}
        </x-section>
    </div>
</x-layouts.app>
