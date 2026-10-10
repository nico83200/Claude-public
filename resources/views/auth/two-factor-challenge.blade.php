<x-layouts.guest title="Double authentification">
    <div x-data="{ recovery: false }">
        <h1 class="mb-2 text-xl font-bold">Double authentification</h1>
        <p class="mb-6 text-sm text-slate-600" x-show="! recovery">Saisissez le code à 6 chiffres affiché par votre application d'authentification.</p>
        <p class="mb-6 text-sm text-slate-600" x-cloak x-show="recovery">Saisissez l'un de vos codes de récupération.</p>
        <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4">
            @csrf
            <div x-show="! recovery"><x-field name="code" label="Code" inputmode="numeric" autocomplete="one-time-code" autofocus /></div>
            <div x-cloak x-show="recovery"><x-field name="recovery_code" label="Code de récupération" autocomplete="off" /></div>
            <button class="btn-primary w-full">Valider</button>
        </form>
        <button type="button" class="mt-4 text-sm link" @click="recovery = ! recovery" x-text="recovery ? 'Utiliser un code d\'authentification' : 'Utiliser un code de récupération'"></button>
    </div>
</x-layouts.guest>
