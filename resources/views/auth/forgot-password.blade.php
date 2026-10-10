<x-layouts.guest title="Mot de passe oublié">
    <h1 class="mb-1 text-xl font-bold">Mot de passe oublié</h1>
    <p class="mb-6 text-sm text-slate-500">Indiquez votre adresse : si un compte existe, vous recevrez un lien de réinitialisation.</p>
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-field name="email" type="email" label="Adresse email" required autofocus />
        <button class="btn-primary w-full">Envoyer le lien</button>
    </form>
    <p class="mt-4 text-center text-sm"><a href="{{ route('login') }}" class="link">Retour à la connexion</a></p>
</x-layouts.guest>
