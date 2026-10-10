<x-layouts.guest title="Nouveau mot de passe">
    <h1 class="mb-6 text-xl font-bold">Choisir un nouveau mot de passe</h1>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-field name="email" type="email" label="Adresse email" :value="$request->email" required />
        <x-field name="password" type="password" label="Nouveau mot de passe" required autocomplete="new-password" />
        <x-field name="password_confirmation" type="password" label="Confirmer" required autocomplete="new-password" />
        <button class="btn-primary w-full">Réinitialiser</button>
    </form>
</x-layouts.guest>
