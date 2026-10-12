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
            <details class="mt-4 rounded-2xl border border-slate-200 p-3" data-promo-settings @if ($errors->any()) open @endif>
                <summary class="cursor-pointer text-sm font-semibold">Modifier les réglages</summary>
                <form method="POST" action="{{ route('admin.promo-codes.settings', $promo) }}" class="mt-3 space-y-3">
                    @csrf @method('PUT')
                    <x-field name="label" label="Libellé interne" :value="$promo->label" required maxlength="120" />
                    <x-field name="starts_at" type="date" label="Valable à partir du" :value="$promo->starts_at?->toDateString()" />
                    <x-field name="expires_at" type="date" label="Valable jusqu'au (inclus)" :value="$promo->expires_at?->toDateString()" help="Vide : sans date de fin." />
                    <x-field name="max_redemptions" type="number" label="Nombre maximal d'utilisations" :value="$promo->max_redemptions" :min="max(1, $promo->redemptions_count)" help="Vide : illimité. Déjà utilisé {{ $promo->redemptions_count }} fois." />
                    <x-checkbox name="first_subscription_only" label="Réservé aux nouveaux clients" :checked="$promo->first_subscription_only" />
                    @if ($promo->stripe_promotion_code_id)<p class="rounded-xl bg-sky-50 p-2.5 text-xs text-sky-900">Code de réduction Stripe : la validité, la limite ou la restriction « nouveaux clients » ne pouvant pas être modifiées chez Stripe, le code y est remplacé par un code identique avec les nouveaux réglages (utilisations déjà faites déduites).</p>@endif
                    <button class="btn-primary w-full">Enregistrer</button>
                </form>
            </details>
            <p class="mt-3">@if ($r = $promo->unavailableReason())<x-badge color="amber">{{ $r }}</x-badge>@else<x-badge color="green">Valable</x-badge>@endif</p>
            <form method="POST" action="{{ route('admin.promo-codes.update', $promo) }}" class="mt-3">
                @csrf @method('PATCH')
                <input type="hidden" name="is_active" value="{{ $promo->is_active ? 0 : 1 }}">
                <button class="{{ $promo->is_active ? 'btn-danger' : 'btn-primary' }}">{{ $promo->is_active ? 'Désactiver le code' : 'Réactiver le code' }}</button>
            </form>
        </x-section>
        <x-section title="Provenance (QR codes et liens)" class="lg:col-span-3 lg:order-first">
            @include('admin._acquisition', ['rows' => $acquisition])
            <h3 class="mt-5 text-sm font-semibold">Liens et QR codes par source</h3>
            <div class="mt-2 grid gap-2 sm:grid-cols-2" data-source-links>
                @forelse ($sources as $src)
                    @php $link = \App\Services\AcquisitionTracker::link($promo->code, $src->key); @endphp
                    <div class="rounded-2xl bg-slate-50 px-3 py-2.5 text-xs" x-data="{ copied: false }">
                        <p class="font-semibold text-slate-800">{{ $src->label }}</p>
                        <p class="mt-0.5 break-all font-mono text-slate-600">{{ $link }}</p>
                        <p class="mt-1.5 flex gap-3">
                            <button type="button" class="link" @click="navigator.clipboard.writeText(@js($link)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })" x-text="copied ? 'Copié ✓' : 'Copier le lien'">Copier le lien</button>
                            <a href="{{ route('admin.promo-codes.qr', [$promo, $src->key]) }}" class="link">Télécharger le QR code (SVG)</a>
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Aucune source : ajoutez-en dans <a href="{{ route('admin.promo-codes.index') }}" class="link">Codes promo › Sources de provenance</a>.</p>
                @endforelse
            </div>
            <p class="mt-2 text-xs text-slate-500">Nouvelle source (flyer, partenaire…) : <a href="{{ route('admin.promo-codes.index') }}" class="link">ajoutez-la à la liste</a>, son lien et son QR code apparaissent ici.</p>
        </x-section>
        <x-section title="Utilisations" class="lg:col-span-2">
            @forelse ($redemptions as $r)
                <p class="border-t border-slate-100 py-2 text-sm first:border-0">{{ $r->created_at->format('d/m/Y H:i') }} · <a href="{{ route('admin.organizations.show', $r->organization_id) }}" class="link">{{ $r->organization?->name ?? 'Espace supprimé' }}</a>{{ $r->user ? ' · '.$r->user->name : '' }}</p>
            @empty <p class="text-sm text-slate-500">Pas encore utilisé.</p> @endforelse
            {{ $redemptions->links() }}
        </x-section>
    </div>
</x-layouts.app>
