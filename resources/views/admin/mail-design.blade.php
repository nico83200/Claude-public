<x-layouts.app title="E-mails · Admin">
    <x-page-header title="E-mails envoyés" subtitle="Logo, couleurs et textes de tous les e-mails : général, puis type par type" />
    @include('admin._nav')
    @php $s = $settings; $val = fn ($k) => old($k, $s[$k] ?? ''); @endphp
    <div class="grid gap-4 lg:grid-cols-2" x-data="mailDesign()" data-mail-design>
        <div class="space-y-4">
            <form method="POST" action="{{ route('admin.mail-design.update') }}" class="space-y-4" x-ref="form" @input.debounce.500ms="refresh()" @change="refresh()" data-mail-design-form>
                @csrf @method('PUT')
                <x-section title="Logo et couleurs">
                    <div class="space-y-3">
                        <div>
                            <span class="label">Logo en haut de l'e-mail</span>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                @foreach (\App\Services\MailDesign::LOGOS as $key => $label)
                                    @if ($key !== 'custom' || $customLogo)
                                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm"><input type="radio" name="logo" value="{{ $key }}" @checked($val('logo') === $key)> {{ $label }}</label>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <x-field name="logo_width" type="number" min="40" max="400" label="Largeur du logo (px)" :value="$val('logo_width')" />
                            <div>
                                <label class="label" for="f_align">Alignement</label>
                                <select id="f_align" name="align" class="input">
                                    <option value="center" @selected($val('align') === 'center')>Centré</option>
                                    <option value="left" @selected($val('align') === 'left')>À gauche</option>
                                </select>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="logo_link" value="0"><input type="checkbox" name="logo_link" value="1" @checked($s['logo_link'])> Le logo ouvre le site</label>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach (['background_color' => 'Fond', 'card_color' => 'Carte', 'text_color' => 'Texte', 'button_color' => 'Bouton', 'button_text_color' => 'Texte du bouton', 'accent_color' => 'Liseré'] as $f => $label)
                                <div>
                                    <label class="label" for="f_{{ $f }}">{{ $label }}</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" value="{{ $val($f) }}" class="h-10 w-12 shrink-0 cursor-pointer rounded border border-slate-200" aria-label="{{ $label }}" @input="$refs['{{ $f }}'].value = $event.target.value">
                                        <input id="f_{{ $f }}" name="{{ $f }}" x-ref="{{ $f }}" value="{{ $val($f) }}" class="input font-mono text-xs" maxlength="7" pattern="#[0-9a-fA-F]{6}">
                                    </div>
                                    @error($f)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-section>

                <x-section title="Textes généraux">
                    <p class="mb-3 text-sm text-slate-600">Utilisés par tous les e-mails, sauf si un type les remplace plus bas. Variables :
                        @foreach (\App\Services\MailDesign::PLACEHOLDERS as $ph => $label)<code class="rounded bg-slate-100 px-1 text-xs" title="{{ $label }}">{{ $ph }}</code> @endforeach
                        · <code class="rounded bg-slate-100 px-1 text-xs">**gras**</code> · une ligne vide sépare les paragraphes.</p>
                    <div class="space-y-3">
                        <div class="grid grid-cols-3 gap-3">
                            <x-field name="subject_prefix" label="Préfixe d'objet" :value="$val('subject_prefix')" maxlength="40" placeholder="ex. [Jack&Cie]" />
                            <x-field name="subject" label="Objet" class="col-span-2" :value="$val('subject')" maxlength="150" />
                        </div>
                        <x-field name="greeting" label="Salutation" :value="$val('greeting')" maxlength="150" />
                        <x-textarea name="intro" label="Texte avant le motif" rows="2" :value="$val('intro')" maxlength="2000" placeholder="ex. Voici un rappel concernant vos chevaux :" />
                        <x-textarea name="message" label="Motif" rows="2" :value="$val('message')" maxlength="2000" help="{message} est le texte du rappel (ex. « Séance monte libre – Jack le 14/10/2026 à 17:30. »)." />
                        <x-textarea name="outro" label="Texte après le motif" rows="2" :value="$val('outro')" maxlength="2000" placeholder="ex. Bonne journée avec vos chevaux !" />
                        <x-field name="button_label" label="Texte du bouton" :value="$val('button_label')" maxlength="150" />
                        <x-textarea name="signature" label="Signature" rows="2" :value="$val('signature')" maxlength="300" />
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="show_fallback_link" value="0"><input type="checkbox" name="show_fallback_link" value="1" @checked($s['show_fallback_link'])> Afficher le lien en clair sous le bouton</label>
                        <x-field name="fallback_text" label="Phrase avant le lien en clair" :value="$val('fallback_text')" maxlength="200" />
                        <x-textarea name="footer" label="Pied de page" rows="3" :value="$val('footer')" maxlength="600" />
                    </div>
                </x-section>

                <x-section title="Type par type">
                    <p class="mb-3 text-sm text-slate-600">Champ vide = texte général. Écrivez <code class="rounded bg-slate-100 px-1 text-xs">-</code> pour ne rien afficher pour ce type. Décocher « Envoyer par e-mail » coupe l'e-mail de ce type pour tous (la notification dans l'application et sur le téléphone reste envoyée).</p>
                    <div class="divide-y divide-slate-100">
                        @foreach ($kinds as $kind => $label)
                            @php $k = $s['kinds'][$kind] ?? []; $account = isset(\App\Services\MailDesign::ACCOUNT_KINDS[$kind]); $custom = collect($k)->except('enabled')->filter()->isNotEmpty(); @endphp
                            <details class="py-2" data-mail-kind="{{ $kind }}" @toggle="if ($event.target.open) { kind = '{{ $kind }}'; refresh() }">
                                <summary class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-800">
                                    <span class="flex-1">{{ $label }}</span>
                                    @if ($custom)<x-badge color="brand">personnalisé</x-badge>@endif
                                    @if (! $account && ($k['enabled'] ?? true) === false)<x-badge color="slate">e-mail coupé</x-badge>@endif
                                    @if ($account)<x-badge color="slate">compte</x-badge>@endif
                                </summary>
                                <div class="mt-3 space-y-3 pl-1">
                                    @unless ($account)
                                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="kinds[{{ $kind }}][enabled]" value="1" @checked($k['enabled'] ?? true)> Envoyer par e-mail</label>
                                    @endunless
                                    <x-field name="kinds[{{ $kind }}][subject]" label="Objet" :value="$k['subject'] ?? ''" maxlength="150" :placeholder="$s['subject']" />
                                    <x-field name="kinds[{{ $kind }}][greeting]" label="Salutation" :value="$k['greeting'] ?? ''" maxlength="150" :placeholder="$s['greeting']" />
                                    <x-textarea name="kinds[{{ $kind }}][intro]" label="Texte avant le motif" rows="2" :value="$k['intro'] ?? ''" maxlength="2000" :placeholder="$s['intro'] ?: 'Texte général (vide)'" />
                                    <x-textarea name="kinds[{{ $kind }}][message]" label="Motif" rows="2" :value="$k['message'] ?? ''" maxlength="2000" :placeholder="$s['message']" />
                                    <x-textarea name="kinds[{{ $kind }}][outro]" label="Texte après le motif" rows="2" :value="$k['outro'] ?? ''" maxlength="2000" :placeholder="$s['outro'] ?: 'Texte général (vide)'" />
                                    <x-field name="kinds[{{ $kind }}][button_label]" label="Texte du bouton" :value="$k['button_label'] ?? ''" maxlength="150" :placeholder="\App\Services\MailDesign::KIND_DEFAULTS[$kind]['button_label'] ?? $s['button_label']" />
                                </div>
                            </details>
                        @endforeach
                    </div>
                </x-section>
                <button class="btn-primary w-full">Enregistrer</button>
            </form>

            <x-section title="Logo personnalisé">
                <form method="POST" action="{{ route('admin.mail-design.logo') }}" enctype="multipart/form-data" class="space-y-3" data-mail-logo-form>
                    @csrf
                    <input type="file" name="logo_file" accept="image/png,image/jpeg,image/gif" required class="block w-full text-sm">
                    @error('logo_file')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <p class="text-xs text-slate-500">PNG, JPG ou GIF, 1 Mo maximum. Une image deux fois plus large que l'affichage reste nette sur les téléphones.</p>
                    <button class="btn-secondary">Envoyer et utiliser ce logo</button>
                </form>
                @if ($customLogo)
                    <form method="POST" action="{{ route('admin.mail-design.logo.destroy') }}" class="mt-3">
                        @csrf @method('DELETE')
                        <img src="{{ route('mail.logo') }}" alt="Logo actuel" class="mb-2 h-12 w-auto">
                        <button class="text-sm text-red-700 underline">Retirer le logo personnalisé</button>
                    </form>
                @endif
            </x-section>
        </div>

        <div class="lg:sticky lg:top-24 lg:self-start">
            <x-section title="Aperçu">
                <div class="mb-3 flex flex-wrap items-end gap-2">
                    <div class="min-w-0 flex-1">
                        <label class="label" for="preview_kind">Exemple</label>
                        <select id="preview_kind" class="input" x-model="kind" @change="refresh()">
                            @foreach ($kinds as $kind => $label)<option value="{{ $kind }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <form method="POST" action="{{ route('admin.mail-design.test') }}" data-mail-test>
                        @csrf
                        <input type="hidden" name="preview_kind" :value="kind">
                        <button class="btn-secondary" title="Envoie l'exemple avec les réglages enregistrés">M'envoyer un essai</button>
                    </form>
                </div>
                <p class="mb-2 truncate text-sm text-slate-600">Objet : <strong class="text-slate-900" x-text="subject">{{ $s['subject'] }}</strong></p>
                <p class="mb-2 text-xs text-red-600" x-show="error" x-text="error" x-cloak></p>
                <iframe x-ref="frame" title="Aperçu de l'e-mail" class="h-[640px] w-full rounded-lg border border-slate-200 bg-white" srcdoc="{{ $preview }}" data-mail-preview></iframe>
                <p class="mt-2 text-xs text-slate-500">L'aperçu suit vos modifications ; elles ne s'appliquent qu'après « Enregistrer ». L'essai part avec les réglages enregistrés, à votre adresse {{ auth()->user()->email }}.</p>
            </x-section>
        </div>
    </div>
    <script>
        function mailDesign() {
            return {
                kind: 'appointment', subject: '', error: '', seq: 0,
                async refresh() {
                    const data = new FormData(this.$refs.form);
                    data.delete('_method');
                    data.set('preview_kind', this.kind);
                    const n = ++this.seq;
                    try {
                        const r = await fetch(@js(route('admin.mail-design.preview')), { method: 'POST', body: data, headers: { Accept: 'application/json' } });
                        const json = await r.json();
                        if (n !== this.seq) return;
                        if (!r.ok) { this.error = Object.values(json.errors || {}).flat()[0] || 'Aperçu indisponible.'; return; }
                        this.error = '';
                        this.subject = json.subject;
                        this.$refs.frame.srcdoc = json.html;
                    } catch (e) { this.error = 'Aperçu indisponible (connexion).'; }
                },
                init() { this.refresh(); },
            };
        }
    </script>
</x-layouts.app>
