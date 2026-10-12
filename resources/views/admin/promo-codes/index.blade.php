<x-layouts.app title="Codes promo · Admin">
    <x-page-header title="Codes promo et accès offerts" />
    @include('admin._nav')

    {{-- Générateur de QR code : code et source facultatifs, aperçu, lien, téléchargement SVG (impression) ou PNG. --}}
    <section class="card mb-5 p-4 sm:p-5" data-qr-generator x-data="{
        code: @js($qrCodes->first()?->code ?? ''), src: '', copied: false, busy: false,
        base: @js(url('/register')), endpoint: @js(route('admin.promo-codes.qr-generator')),
        query() { const q = new URLSearchParams(); if (this.code) q.set('code', this.code); if (this.src) q.set('src', this.src); return q.toString(); },
        get link() { const q = this.query(); return this.base + (q ? '?' + q : ''); },
        get preview() { const q = this.query(); return this.endpoint + (q ? '?' + q : ''); },
        get download() { const q = this.query(); return this.endpoint + '?' + (q ? q + '&' : '') + 'download=1'; },
        get name() { return ['qr-jackcie', this.code.toLowerCase(), this.src].filter(Boolean).join('-'); },
        copy() { navigator.clipboard.writeText(this.link).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); }); },
        async png() {
            this.busy = true;
            try {
                const svg = await (await fetch(this.preview, { credentials: 'same-origin' })).text();
                const url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
                const img = new Image(); await new Promise((ok, ko) => { img.onload = ok; img.onerror = ko; img.src = url; });
                const c = document.createElement('canvas'); c.width = c.height = 1200;
                const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, 1200, 1200); x.drawImage(img, 0, 0, 1200, 1200);
                URL.revokeObjectURL(url);
                const a = document.createElement('a'); a.href = c.toDataURL('image/png'); a.download = this.name + '.png'; a.click();
            } finally { this.busy = false; }
        },
    }">
        <h2 class="text-[17px] font-semibold">Générateur de QR code</h2>
        <p class="mt-1 text-sm text-slate-500">Choisissez un code promo et une source : le QR code mène à la page d'inscription avec le code déjà saisi, et chaque scan est compté pour la source choisie.</p>
        <div class="mt-4 grid gap-5 md:grid-cols-[1fr_15rem]">
            <div class="space-y-3">
                <div><label class="label" for="qr_code">Code promo</label>
                    <select id="qr_code" x-model="code" class="input"><option value="">Sans code (inscription simple)</option>@foreach ($qrCodes as $c)<option value="{{ $c->code }}">{{ $c->code }} · {{ $c->label }}</option>@endforeach</select></div>
                <div><label class="label" for="qr_src">Source</label>
                    <select id="qr_src" x-model="src" class="input"><option value="">Aucune source</option>@foreach ($sources as $s)<option value="{{ $s->key }}">{{ $s->label }}</option>@endforeach</select>
                    <p class="mt-1 text-xs text-slate-500">Une source manque ? Ajoutez-la dans « Sources de provenance » ci-dessous.</p></div>
                <div><span class="label">Lien</span><p class="break-all rounded-xl bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700" x-text="link"></p></div>
                <div class="flex flex-wrap gap-2">
                    <a :href="download" class="btn-primary"><x-icon name="download" class="size-4" /> SVG (impression)</a>
                    <button type="button" class="btn-secondary" @click="png()" :disabled="busy"><x-icon name="download" class="size-4" /> <span x-text="busy ? 'Préparation…' : 'PNG (réseaux, documents)'"></span></button>
                    <button type="button" class="btn-ghost" @click="copy()" x-text="copied ? 'Lien copié ✓' : 'Copier le lien'"></button>
                </div>
            </div>
            <div class="flex items-start justify-center"><img :src="preview" alt="Aperçu du QR code" class="aspect-square w-56 rounded-2xl bg-white p-2 ring-1 ring-slate-200" width="224" height="224"></div>
        </div>
    </section>

    <details class="card mb-5 p-4 sm:p-5" @if ($errors->any() && ! old('_source')) open @endif>
        <summary class="cursor-pointer font-semibold">Créer un code</summary>
        <form method="POST" action="{{ route('admin.promo-codes.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2" x-data="{ type: @js(old('type', 'free_access')), kind: @js(old('discount_kind', 'percent')), duration: @js(old('duration', 'once')) }">
            @csrf
            <div class="sm:col-span-2 grid gap-2 sm:grid-cols-2">
                @foreach (\App\Models\PromoCode::TYPES as $t => $tl)
                    <label class="card flex cursor-pointer items-start gap-3 p-3" :class="type === '{{ $t }}' && 'ring-2 ring-brand-500'">
                        <input type="radio" name="type" value="{{ $t }}" x-model="type" class="mt-1">
                        <span><span class="font-medium">{{ $tl }}</span><span class="block text-xs text-slate-500">{{ $t === 'discount' ? 'Remise appliquée par Stripe sur le paiement (pourcentage ou montant).' : 'Licence offerte pendant N jours, sans carte bancaire. Expire automatiquement.' }}</span></span>
                    </label>
                @endforeach
            </div>
            <x-field name="code" label="Code" :value="$suggested" required help="Lettres, chiffres, - et _ ; converti en majuscules." />
            <x-field name="label" label="Libellé interne" required placeholder="Salon du cheval 2026" />

            <template x-if="type === 'free_access'"><div class="contents">
                <x-select name="subscription_plan_id" label="Offre accordée" :options="$plans" placeholder="Choisir…" />
                <x-field name="free_days" type="number" label="Durée offerte (jours)" value="30" min="1" max="3650" />
            </div></template>

            <template x-if="type === 'discount'"><div class="contents">
                @unless ($stripeReady)<p class="sm:col-span-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Stripe n'est pas configuré : configurez-le dans <a href="{{ route('admin.integrations.edit') }}" class="link">Intégrations</a> avant de créer une réduction.</p>@endunless
                <x-select name="discount_kind" label="Type de remise" :options="['percent' => 'Pourcentage', 'amount' => 'Montant fixe (€)']" x-model="kind" />
                <div x-show="kind === 'percent'"><x-field name="percent_off" type="number" step="0.01" label="Réduction (%)" min="1" max="100" /></div>
                <div x-show="kind === 'amount'"><x-field name="amount_off" type="number" step="0.01" label="Réduction (€)" min="0.5" /></div>
                <x-select name="duration" label="Appliquée" :options="['once' => 'Sur la première facture', 'repeating' => 'Pendant plusieurs mois', 'forever' => 'Pendant tout l\'abonnement']" x-model="duration" />
                <div x-show="duration === 'repeating'"><x-field name="duration_months" type="number" label="Nombre de mois" min="1" max="36" /></div>
                <fieldset class="sm:col-span-2"><legend class="label">Offres concernées (aucune cochée = toutes)</legend>
                    <div class="flex flex-wrap gap-x-4">@foreach ($plans as $id => $name)<x-checkbox name="plan_ids[]" :value="$id" :label="$name" :checked="in_array($id, old('plan_ids', []))" />@endforeach</div>
                </fieldset>
            </div></template>

            <x-field name="starts_at" type="date" label="Valable à partir du (optionnel)" />
            <x-field name="expires_at" type="date" label="Valable jusqu'au (optionnel)" />
            <x-field name="max_redemptions" type="number" label="Nombre maximal d'utilisations (vide = illimité)" min="1" />
            <div class="self-end"><x-checkbox name="first_subscription_only" label="Réservé aux nouveaux clients" /></div>
            <div class="sm:col-span-2"><button class="btn-primary">Créer le code</button></div>
        </form>
    </details>

    <details class="card mb-5 p-4 sm:p-5" data-sources @if ($errors->any() && old('_source')) open @endif>
        <summary class="cursor-pointer font-semibold">Sources de provenance <span class="font-normal text-slate-500">({{ $sources->count() }})</span></summary>
        <p class="mt-2 text-sm text-slate-500">Une source par support : affiches, Instagram, flyers, partenaire, newsletter… Chaque code promo propose ensuite un lien et un QR code par source, et les statistiques comptent les scans, les clics et les inscriptions de chacune.</p>
        <div class="mt-3 divide-y divide-slate-100">
            @foreach ($sources as $src)
                <div class="flex flex-wrap items-center gap-2 py-2">
                    <form method="POST" action="{{ route('admin.promo-codes.sources.update', $src) }}" class="flex min-w-0 flex-1 items-center gap-2">@csrf @method('PATCH')
                        <input name="label" value="{{ $src->label }}" maxlength="80" class="input min-w-0 flex-1" aria-label="Nom de la source {{ $src->key }}">
                        <span class="hidden shrink-0 font-mono text-xs text-slate-500 sm:inline">src={{ $src->key }}</span>
                        <button class="btn-ghost text-sm">Renommer</button>
                    </form>
                    <x-confirm-delete :action="route('admin.promo-codes.sources.destroy', $src)" :message="'Retirer la source « '.$src->label.' » ? Les arrivées déjà comptées restent dans les statistiques.'" class="text-sm text-red-600">Retirer</x-confirm-delete>
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('admin.promo-codes.sources.store') }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_14rem_auto] sm:items-end">
            @csrf <input type="hidden" name="_source" value="1">
            <x-field name="label" label="Nouvelle source" placeholder="Flyers sellerie Dupont" maxlength="80" required />
            <x-field name="key" label="Identifiant (facultatif)" placeholder="flyer-dupont" maxlength="40" help="Calculé à partir du nom si vide." />
            <button class="btn-primary mb-0.5"><x-icon name="plus" class="size-4" /> Ajouter</button>
        </form>
    </details>

    <div class="mb-3 flex gap-2 text-sm">
        <a href="{{ route('admin.promo-codes.index') }}" class="{{ request('type') ? 'btn-ghost' : 'btn-secondary' }}">Tous</a>
        @foreach (\App\Models\PromoCode::TYPES as $t => $tl)<a href="{{ route('admin.promo-codes.index', ['type' => $t]) }}" class="{{ request('type') === $t ? 'btn-secondary' : 'btn-ghost' }}">{{ $t === 'discount' ? 'Réductions' : 'Accès gratuits' }}</a>@endforeach
    </div>
    @if ($codes->isEmpty())
        <x-empty title="Aucun code">Créez un code de réduction ou un accès gratuit temporaire.</x-empty>
    @else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Code</th><th>Avantage</th><th>Utilisations</th><th>Validité</th><th>État</th><th></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @foreach ($codes as $c)
            <tr>
                <td><span class="font-mono font-semibold">{{ $c->code }}</span><span class="block text-xs text-slate-500">{{ $c->label }}</span></td>
                <td class="text-sm">{{ $c->summary() }}</td>
                <td>{{ $c->redemptions_count }}{{ $c->max_redemptions ? ' / '.$c->max_redemptions : '' }}</td>
                <td class="text-xs">{{ $c->starts_at?->format('d/m/Y') ?? '—' }} → {{ $c->expires_at?->format('d/m/Y') ?? '∞' }}</td>
                <td>@if ($r = $c->unavailableReason())<x-badge color="amber" :title="$r">Inutilisable</x-badge>@else<x-badge color="green">Valable</x-badge>@endif</td>
                <td><a href="{{ route('admin.promo-codes.show', $c) }}" class="link">Détail</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    {{ $codes->links() }}
    @endif
</x-layouts.app>
