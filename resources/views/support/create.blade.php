<x-layouts.app title="Assistance">
    <x-page-header title="Assistance" subtitle="Une question, un souci, une idée ? Écrivez-nous, nous vous répondons par e-mail." />
    <div class="grid max-w-4xl gap-4 lg:grid-cols-3">
        <form method="POST" action="{{ route('support.send') }}" enctype="multipart/form-data" class="lg:col-span-2" data-support-form
              x-data x-init="$refs.page.value = document.referrer && new URL(document.referrer).origin === location.origin ? new URL(document.referrer).pathname : ''">
            @csrf
            <x-section>
                <div class="space-y-4">
                    <x-select name="topic" label="Sujet" :options="$topics" :value="old('topic', request('sujet', 'question'))" required />
                    <x-textarea name="message" label="Votre message" rows="7" required maxlength="5000" placeholder="Décrivez votre demande : ce que vous faisiez, ce qui s'est passé, sur quel appareil…" />
                    <div>
                        <label for="f_attachment" class="label">Capture d'écran <span class="font-normal text-slate-500">(facultatif)</span></label>
                        <input id="f_attachment" type="file" name="attachment" accept="image/*,application/pdf" class="input py-2 text-sm">
                        @error('attachment')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@else<p class="mt-1 text-xs text-slate-500">Image ou PDF, 5 Mo au plus.</p>@enderror
                    </div>
                    <input type="hidden" name="page" x-ref="page">
                    <p class="text-xs text-slate-500">La réponse arrivera à <strong>{{ auth()->user()->email }}</strong>. Pour nous aider, la version de l'application, votre offre et le type d'appareil sont joints au message.</p>
                    <button class="btn-primary w-full sm:w-auto"><x-icon name="send" class="size-4" /> Envoyer le message</button>
                </div>
            </x-section>
        </form>
        <div class="space-y-4">
            <x-section title="Avant d'écrire">
                <ul class="space-y-2 text-sm text-slate-600">
                    <li><a href="{{ route('videos.index') }}" class="link">Tutoriels vidéo</a> : prise en main pas à pas.</li>
                    <li><a href="{{ route('settings.notifications') }}" class="link">Notifications</a> : diagnostic si les rappels n'arrivent pas.</li>
                    @if (auth()->user()->canInOrg($currentOrganization, 'billing.manage'))<li><a href="{{ route('billing.index') }}" class="link">Abonnement</a> : factures, changement d'offre, résiliation.</li>@endif
                </ul>
            </x-section>
            <x-section title="Par e-mail">
                <p class="text-sm text-slate-600">Vous pouvez aussi nous écrire directement à <a href="mailto:{{ $email }}" class="link">{{ $email }}</a>.</p>
            </x-section>
        </div>
    </div>
</x-layouts.app>
