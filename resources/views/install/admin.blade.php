<x-layouts.install :step="4" title="Compte super‑administrateur">
    <p class="mb-5 text-sm text-slate-600">Ce compte gère les offres, les abonnements, les licences et les utilisateurs. Il n'a pas d'accès automatique aux dossiers des chevaux de vos clients.</p>
    <form method="POST" action="{{ route('install.admin.save') }}" class="space-y-4">
        @csrf
        <x-field name="name" label="Nom" required autocomplete="name" />
        <x-field name="email" type="email" label="Email" required autocomplete="email" />
        <x-field name="password" type="password" label="Mot de passe" required autocomplete="new-password" help="12 caractères minimum, avec majuscules, minuscules, chiffres et symboles." />
        <x-field name="password_confirmation" type="password" label="Confirmer le mot de passe" required autocomplete="new-password" />
        <button class="btn-primary w-full">Créer le compte et terminer</button>
    </form>
</x-layouts.install>
