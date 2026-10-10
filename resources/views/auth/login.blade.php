<x-layouts.guest title="Connexion">
    <h1 class="mb-1 text-xl font-bold">Connexion</h1>
    <p class="mb-6 text-sm text-slate-500">Pas encore de compte ? <a href="{{ route('register') }}" class="link">Créer un compte</a></p>
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-field name="email" type="email" label="Adresse email" required autofocus autocomplete="username" />
        <x-field name="password" type="password" label="Mot de passe" required autocomplete="current-password" />
        <div class="flex items-center justify-between">
            <x-checkbox name="remember" label="Rester connecté" />
            <a href="{{ route('password.request') }}" class="text-sm link">Mot de passe oublié ?</a>
        </div>
        <button class="btn-primary w-full">Se connecter</button>
    </form>
</x-layouts.guest>
