<x-layouts.app :title="'Résumé vétérinaire · '.$horse->shortName()">
    <x-horse-header :horse="$horse" />
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <p class="mb-3 text-sm text-slate-600">Les 6 derniers mois du dossier de santé de {{ $horse->shortName() }}, tels que le vétérinaire les verra.</p>
            @include('health._vet-summary-body')
        </div>
        <div class="space-y-4">
            @if ($canShare)
                <x-section title="Envoyer au vétérinaire" data-vet-share-form>
                    <form method="POST" action="{{ route('horses.vet-summary.store', $horse) }}" class="space-y-3"
                          x-data="{ busy: false, name: @js(old('recipient', '')), email: @js(old('recipient_email', '')), phone: @js(old('recipient_phone', '')), contacts: @js($contacts),
                                    pick(id) { const c = this.contacts.find((x) => String(x.id) === String(id)); if (c) { this.name = c.name; this.email = c.email || ''; this.phone = c.phone || ''; } } }"
                          @submit="busy = true">
                        @csrf
                        @if ($contacts->isNotEmpty())
                            <div>
                                <label class="label" for="vet-contact">Choisir parmi les intervenants</label>
                                <select id="vet-contact" class="input" @change="pick($event.target.value)" data-vet-contact>
                                    <option value="">—</option>
                                    @foreach ($contacts as $c)<option value="{{ $c['id'] }}">{{ $c['label'] }}</option>@endforeach
                                </select>
                            </div>
                        @endif
                        <div><label class="label" for="f_recipient">Destinataire</label><input id="f_recipient" name="recipient" x-model="name" maxlength="120" placeholder="ex. Dr Leroy" class="input"></div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div><label class="label" for="f_recipient_email">E-mail</label><input id="f_recipient_email" type="email" name="recipient_email" x-model="email" maxlength="255" class="input" placeholder="cabinet@exemple.fr">@error('recipient_email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            <div><label class="label" for="f_recipient_phone">Téléphone (SMS)</label><input id="f_recipient_phone" type="tel" inputmode="tel" name="recipient_phone" x-model="phone" maxlength="40" class="input" placeholder="06 12 34 56 78">@error('recipient_phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                        </div>
                        <x-select name="days" label="Le lien reste ouvert pendant" :options="\App\Models\HealthShare::DURATIONS" value="7" />
                        <label class="flex items-start gap-2 text-sm" x-show="email" x-cloak><input type="checkbox" name="send_email" value="1" class="mt-1" checked> <span>Envoyer le lien par e-mail dès sa création</span></label>
                        @if ($aiAvailable)
                            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="with_ai" value="1" class="mt-1" checked> <span>Ajouter une synthèse rédigée par l'assistant <span class="block text-xs text-slate-500">5 à 8 lignes factuelles en tête du résumé, sans avis médical. Compte comme une consigne.</span></span></label>
                        @endif
                        <button class="btn-primary w-full" :disabled="busy"><x-icon name="share" class="size-4" /> <span x-text="busy ? 'Préparation…' : 'Créer le lien'">Créer le lien</span></button>
                    </form>
                    <p class="mt-3 text-xs text-slate-500">Le destinataire ouvre le résumé sans compte, jusqu'à la date choisie ; ce n'est jamais référencé par les moteurs de recherche. Vous pouvez prolonger ou désactiver le lien à tout moment.</p>
                </x-section>
            @endif
            @if ($shares->isNotEmpty())
                <x-section title="Liens créés">
                    @foreach ($shares as $sh)
                        @php
                            $url = route('vet-summary.public', $sh->token);
                            $sms = $sh->message(auth()->user()->name);
                            $tel = preg_replace('/[^0-9+]/', '', (string) $sh->recipient_phone);
                            $fresh = session('share') === $sh->id;
                        @endphp
                        <div class="border-t border-slate-100 py-3 text-sm first:border-0 {{ $fresh ? '-mx-2 rounded-2xl bg-brand-50 px-2' : '' }}" x-data="{ copied: false, more: {{ $fresh ? 'true' : 'false' }} }" data-vet-share="{{ $sh->id }}">
                            <p class="font-medium">{{ $sh->recipient ?: 'Lien du '.$sh->created_at->format('d/m/Y') }} @if ($sh->isActive())<x-badge color="green">ouvert</x-badge>@else<x-badge>{{ $sh->revoked_at ? 'désactivé' : 'expiré' }}</x-badge>@endif</p>
                            <p class="text-xs text-slate-500">{{ $sh->isActive() ? 'Ouvert jusqu\'au '.$sh->expires_at->translatedFormat('j M Y à H:i') : 'Créé le '.$sh->created_at->format('d/m/Y') }} · {{ $sh->views }} consultation(s){{ $sh->ai_summary ? ' · avec synthèse' : '' }}{{ $sh->emailed_at ? ' · e-mail envoyé le '.$sh->emailed_at->format('d/m à H:i') : '' }}</p>
                            @if ($sh->isActive())
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @if ($tel)<a href="sms:{{ $tel }}?&body={{ rawurlencode($sms) }}" class="btn-secondary min-h-9 px-3 text-xs" data-vet-sms><x-icon name="sms" class="size-4" /> SMS</a>@endif
                                    <button type="button" class="btn-secondary min-h-9 px-3 text-xs" @click="navigator.clipboard.writeText(@js($url)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"><span x-text="copied ? 'Lien copié ✓' : 'Copier le lien'">Copier le lien</span></button>
                                    <button type="button" class="btn-ghost min-h-9 px-3 text-xs" x-show="navigator.share" @click="navigator.share({ title: @js('Résumé de santé · '.$horse->shortName()), text: @js($sms), url: @js($url) }).catch(() => {})"><x-icon name="share" class="size-4" /> Partager</button>
                                    <a href="{{ $url }}" target="_blank" class="btn-ghost min-h-9 px-3 text-xs">Ouvrir</a>
                                    @if ($canShare)<button type="button" class="btn-ghost min-h-9 px-3 text-xs" @click="more = ! more" :aria-expanded="more">E-mail, durée…</button>@endif
                                </div>
                                @if ($canShare)
                                    <div x-show="more" x-cloak class="mt-3 space-y-3 rounded-2xl bg-white p-3 ring-1 ring-slate-200">
                                        <form method="POST" action="{{ route('horses.vet-summary.email', [$horse, $sh]) }}" class="flex flex-wrap items-end gap-2" data-vet-email-form>
                                            @csrf
                                            <div class="min-w-0 flex-1"><label class="label" for="em-{{ $sh->id }}">Envoyer par e-mail à</label><input id="em-{{ $sh->id }}" type="email" name="recipient_email" value="{{ $sh->recipient_email }}" required class="input" placeholder="cabinet@exemple.fr"></div>
                                            <button class="btn-primary min-h-11 px-4"><x-icon name="send" class="size-4" /> {{ $sh->emailed_at ? 'Renvoyer' : 'Envoyer' }}</button>
                                        </form>
                                        @if (! $tel)<p class="text-xs text-slate-500">Pour l'envoyer par SMS, utilisez « Partager » ou « Copier le lien » puis collez-le dans votre message.</p>@endif
                                        <form method="POST" action="{{ route('horses.vet-summary.duration', [$horse, $sh]) }}" class="flex flex-wrap items-end gap-2" data-vet-duration-form>
                                            @csrf @method('PUT')
                                            <div class="min-w-0 flex-1"><label class="label" for="du-{{ $sh->id }}">Garder le lien ouvert encore</label>
                                                <select id="du-{{ $sh->id }}" name="days" class="input">@foreach (\App\Models\HealthShare::DURATIONS as $d => $label)<option value="{{ $d }}" @selected($d === 7)>{{ $label }}</option>@endforeach</select></div>
                                            <button class="btn-secondary min-h-11 px-4">Appliquer</button>
                                        </form>
                                        <form method="POST" action="{{ route('horses.vet-summary.revoke', [$horse, $sh]) }}">@csrf<button class="text-xs font-medium text-red-600">Désactiver le lien maintenant</button></form>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </x-section>
            @endif
        </div>
    </div>
</x-layouts.app>
