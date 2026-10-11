{{-- Import d'une fiche contact du téléphone (voir resources/js/contacts.js) --}}
<div class="rounded-2xl bg-slate-50 p-3">
    <p class="mb-2 text-xs font-medium text-slate-500">Depuis les contacts du téléphone</p>
    <div class="flex flex-wrap gap-2">
        <button type="button" x-show="pickerSupported" x-cloak @click="pick()" class="btn-secondary min-h-10 px-4 text-sm"><x-icon name="users" class="size-4" /> Choisir un contact</button>
        <label class="btn-secondary min-h-10 cursor-pointer px-4 text-sm">
            <x-icon name="download" class="size-4" /> Importer une fiche (.vcf)
            <input type="file" accept=".vcf,.vcard,text/vcard,text/x-vcard" class="sr-only" @change="file($event)">
        </label>
    </div>
    <p class="mt-2 text-xs text-slate-500" x-show="! pickerSupported">Sur iPhone : Contacts › la fiche › « Partager le contact » › « Enregistrer dans Fichiers », puis importez-la ici.</p>
    <template x-if="choices.length">
        <div class="mt-2 space-y-1">
            <p class="text-xs text-slate-500">Plusieurs fiches dans ce fichier : choisissez.</p>
            <template x-for="(c, i) in choices" :key="i"><button type="button" class="menu-item bg-white" @click="fill(c)" x-text="c.display + (c.phone ? ' · ' + c.phone : '')"></button></template>
        </div>
    </template>
    <p class="mt-2 text-sm text-brand-800" x-show="imported" x-cloak>Fiche de <strong x-text="imported"></strong> importée : vérifiez puis enregistrez.</p>
    <p class="mt-2 text-sm text-red-600" x-show="importError" x-text="importError" x-cloak></p>
</div>
