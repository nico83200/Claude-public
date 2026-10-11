{{-- Création rapide d'un intervenant depuis un formulaire (soin, rendez-vous, dépense, cours) --}}
@php $contactConfig = ['url' => route('professionals.quick'), 'csrf' => csrf_token()]; @endphp
<script type="application/json" id="contact-config">@json($contactConfig)</script>
<dialog id="contact-dialog" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl p-0 backdrop:bg-slate-900/40" x-data="contactImport()" @close="$store.contactForm.isOpen = false">
    <form class="space-y-3 p-5" @submit.prevent="$store.contactForm.save($el)">
        <div class="flex items-center justify-between">
            <h2 class="text-[17px] font-semibold">Nouvel intervenant</h2>
            <button type="button" class="btn-ghost px-2" @click="$store.contactForm.close()" aria-label="Fermer"><x-icon name="x" /></button>
        </div>
        @include('professionals._import')
        <div class="grid gap-3 sm:grid-cols-2">
            <x-select name="kind" id="cd_kind" label="Fonction" :options="\App\Models\Professional::KINDS" required />
            <x-field name="company" id="cd_company" label="Cabinet / société" />
            <x-field name="first_name" id="cd_first_name" label="Prénom" autocomplete="off" />
            <x-field name="last_name" id="cd_last_name" label="Nom" required autocomplete="off" />
            <x-field name="phone" id="cd_phone" type="tel" label="Téléphone" />
            <x-field name="email" id="cd_email" type="email" label="Email" />
            <x-field name="address" id="cd_address" label="Adresse" class="sm:col-span-2" />
        </div>
        <input type="hidden" name="notes">
        <p class="text-xs text-slate-500">Si une fiche avec le même email, le même téléphone ou le même nom existe déjà, elle est mise à jour au lieu d'être recréée.</p>
        <p class="text-sm text-red-600" x-show="$store.contactForm.error" x-text="$store.contactForm.error" x-cloak></p>
        <div class="flex gap-2">
            <button class="btn-primary flex-1" :disabled="$store.contactForm.saving" x-text="$store.contactForm.saving ? 'Enregistrement…' : 'Enregistrer et choisir'">Enregistrer et choisir</button>
            <button type="button" class="btn-ghost" @click="$store.contactForm.close()">Annuler</button>
        </div>
    </form>
</dialog>
<div x-data="{ msg: '', t: null }" @jc-toast.window="msg = $event.detail; clearTimeout(t); t = setTimeout(() => msg = '', 3500)"
     x-show="msg" x-transition x-cloak role="status" class="fixed inset-x-4 bottom-28 z-[90] mx-auto max-w-sm rounded-2xl bg-brand-900 px-4 py-3 text-center text-sm text-white shadow-xl lg:bottom-8">
    <span x-text="msg"></span>
</div>
