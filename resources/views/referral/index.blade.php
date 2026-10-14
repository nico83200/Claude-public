<x-layouts.app title="Parrainage">
    <x-page-header title="Parrainage" subtitle="Faites découvrir Jack&Cie à vos amis cavaliers." />
    <div class="grid max-w-5xl gap-4 lg:grid-cols-5">
        <div class="space-y-4 lg:col-span-3">
            <section class="card overflow-hidden" data-referral-hero>
                <div class="bg-brand-900 p-6 text-white">
                    <p class="flex items-center gap-2 text-xs font-semibold tracking-wide text-gold-200 uppercase"><x-icon name="sparkles" class="size-4" /> Offert</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $days }} jours de l'offre {{ $plan?->name ?? 'Propriétaire' }} pour chaque ami inscrit</p>
                    <p class="mt-2 text-[15px] text-white/80">Partagez votre lien. Dès qu'un ami crée son compte avec ce lien et confirme son adresse e-mail, vous recevez {{ $days }} jours gratuits, sans carte bancaire.</p>
                </div>
                <div class="space-y-3 p-5" x-data="{ copied: false, link: @js($link), message: @js($message) }">
                    <label for="ref-link" class="label">Votre lien personnel</label>
                    <div class="flex gap-2">
                        <input id="ref-link" class="input min-w-0 flex-1 font-mono text-sm" readonly value="{{ $link }}" @focus="$el.select()" data-referral-link>
                        <button type="button" class="btn-secondary shrink-0" @click="navigator.clipboard?.writeText(link); copied = true; setTimeout(() => copied = false, 2000)" x-text="copied ? 'Copié' : 'Copier'">Copier</button>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <a href="sms:?&body={{ rawurlencode($message) }}" class="btn-primary justify-center lg:hidden" data-referral-sms><x-icon name="sms" class="size-4" /> Envoyer par SMS</a>
                        <button type="button" class="btn-secondary justify-center" x-show="navigator.share" x-cloak @click="navigator.share({ title: 'Jack&Cie', text: message.replace(link, '').trim(), url: link }).catch(() => {})" data-referral-share><x-icon name="share" class="size-4" /> Partager…</button>
                        <a href="mailto:?subject={{ rawurlencode('Découvre Jack&Cie') }}&body={{ rawurlencode($message) }}" class="btn-secondary justify-center"><x-icon name="send" class="size-4" /> Par e-mail</a>
                    </div>
                </div>
            </section>

            <x-section title="Vos filleuls" data-referral-list>
                @forelse ($referrals as $r)
                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-2.5 first:border-t-0 first:pt-0">
                        <span class="min-w-0"><span class="block truncate text-sm font-medium text-slate-800">{{ $r->referee?->name ?? 'Compte supprimé' }}</span><span class="text-xs text-slate-500">Inscrit le {{ $r->created_at->format('d/m/Y') }}</span></span>
                        <x-badge :color="['rewarded' => 'green', 'used' => 'green', 'banked' => 'amber', 'pending' => 'slate', 'capped' => 'slate'][$r->status] ?? 'slate'">{{ \App\Models\Referral::STATUSES[$r->status] ?? $r->status }}{{ $r->days ? " · ".$r->days." j" : "" }}</x-badge>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Aucun filleul pour l'instant. Partagez votre lien ou faites scanner votre QR code !</p>
                @endforelse
                @if ($banked)
                    <div class="mt-4 rounded-2xl bg-gold-50 p-4 text-sm ring-1 ring-gold-200" data-referral-banked>
                        <p class="font-semibold text-slate-900">{{ $banked }} jours en réserve</p>
                        <p class="mt-1 text-slate-600">Votre abonnement était déjà actif : ces jours vous attendent et s'utilisent quand aucune offre n'est en cours sur votre espace personnel.</p>
                        @if ($canUseBanked)
                            <form method="POST" action="{{ route('referral.use') }}" class="mt-3">@csrf<button class="btn-primary">Utiliser mes {{ $banked }} jours</button></form>
                        @endif
                    </div>
                @endif
            </x-section>
        </div>

        <div class="space-y-4 lg:col-span-2">
            <x-section title="QR code" data-referral-qr>
                <p class="-mt-1 mb-3 text-sm text-slate-500">À faire scanner avec l'appareil photo du téléphone de votre ami : il arrive directement sur l'inscription à Jack&Cie.</p>
                <img src="{{ route('referral.qr') }}" alt="QR code vers l'inscription à Jack&Cie avec votre lien de parrainage" class="mx-auto aspect-square w-full max-w-64 rounded-2xl bg-white p-3 ring-1 ring-slate-200">
            </x-section>
            <x-section title="Comment ça marche ?">
                <ol class="list-decimal space-y-1.5 pl-5 text-sm text-slate-600">
                    <li>Votre ami ouvre votre lien ou scanne votre QR code.</li>
                    <li>Il crée son compte et confirme son adresse e-mail.</li>
                    <li>Vous recevez {{ $days }} jours de l'offre {{ $plan?->name ?? 'Propriétaire' }}, prolongés à chaque nouvel ami (jusqu'à {{ \App\Services\ReferralService::MAX_PER_YEAR }} amis par an).</li>
                </ol>
                <p class="mt-3 text-xs text-slate-500">Si vous avez déjà un abonnement en cours, les jours sont mis en réserve : ils ne remplacent jamais votre offre actuelle.</p>
            </x-section>
        </div>
    </div>
</x-layouts.app>
