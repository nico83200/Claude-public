<x-layouts.guest title="Créer un compte">
    <h1 class="mb-1 text-xl font-bold">Créer un compte</h1>
    <p class="mb-6 text-sm text-slate-500">Déjà inscrit ? <a href="{{ route('login') }}" class="link">Se connecter</a></p>
    @if (! \App\Models\FeatureFlag::enabled('registration'))
        <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Les inscriptions sont temporairement fermées.</p>
    @else
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <x-field name="name" label="Nom et prénom" required autofocus autocomplete="name" />
        <x-field name="email" type="email" label="Adresse email" required autocomplete="email" />
        <x-field name="password" type="password" label="Mot de passe" required autocomplete="new-password" help="10 caractères minimum, avec lettres et chiffres." />
        <x-field name="password_confirmation" type="password" label="Confirmer le mot de passe" required autocomplete="new-password" />
        <label class="flex cursor-pointer items-start gap-3 text-sm text-slate-700">
            <input type="checkbox" name="terms" value="1" @checked(old('terms')) class="mt-0.5 size-5 rounded border-slate-300 text-brand-600">
            <span>J'accepte les <a href="{{ route('legal.terms') }}" target="_blank" class="link">conditions d'utilisation</a> et la <a href="{{ route('legal.privacy') }}" target="_blank" class="link">politique de confidentialité</a>.</span>
        </label>
        @error('terms')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <button class="btn-primary w-full">Créer mon compte</button>
    </form>
    @endif
</x-layouts.guest>
