<x-layouts.guest title="Connexion">
<x-install-gate purpose="vous connecter">
    <h1 class="mb-1 text-xl font-bold">Connexion</h1>
    <p class="mb-6 text-sm text-slate-500">Pas encore de compte ? <a href="{{ route('register') }}" class="link">Créer un compte</a></p>
    @if (\App\Services\Auth\OidcProvider::available())
        <x-social-buttons />
        <p class="mt-3 text-center text-xs text-slate-500">En continuant avec Google ou Apple, vous acceptez les <a href="{{ route('legal.terms') }}" target="_blank" class="link">conditions d'utilisation</a> et la <a href="{{ route('legal.privacy') }}" target="_blank" class="link">politique de confidentialité</a>.</p>
        <div class="my-6 flex items-center gap-3 text-xs text-slate-400" role="separator"><span class="h-px flex-1 bg-slate-200"></span>ou avec votre email<span class="h-px flex-1 bg-slate-200"></span></div>
    @endif
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
</x-install-gate>
</x-layouts.guest>
