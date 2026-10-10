<x-layouts.guest title="Confirmer le mot de passe">
    <h1 class="mb-2 text-xl font-bold">Confirmation requise</h1>
    <p class="mb-6 text-sm text-slate-600">Pour cette opération sensible, confirmez votre mot de passe.</p>
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf
        <x-field name="password" type="password" label="Mot de passe" required autofocus autocomplete="current-password" />
        <button class="btn-primary w-full">Confirmer</button>
    </form>
</x-layouts.guest>
