<x-layouts.guest title="Vérification de l'email">
    <h1 class="mb-2 text-xl font-bold">Vérifiez votre adresse email</h1>
    <p class="mb-6 text-sm text-slate-600">Un lien de confirmation vous a été envoyé. Cliquez dessus pour activer votre compte.</p>
    <a href="{{ route('dashboard') }}" class="btn-primary mb-3 w-full">J'ai confirmé mon adresse</a>
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button class="btn-secondary w-full">Renvoyer le lien</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">@csrf<input type="hidden" name="pwa" data-standalone><button class="text-sm link">Se déconnecter</button></form>
</x-layouts.guest>
