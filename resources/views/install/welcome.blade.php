<x-layouts.install :step="1" title="Bienvenue">
    <p class="mb-5 text-sm text-slate-600">Cet assistant prépare Jack&amp;Cie en quelques minutes : connexion à la base MySQL, création des tables, réglages de l'application et création du compte super‑administrateur.</p>
    <h2 class="mb-2 text-base">Vérification du serveur</h2>
    <ul class="mb-6 divide-y divide-sand-100 rounded-xl border border-sand-200 text-sm">
        @foreach ($requirements as $r)
            <li class="flex items-center justify-between gap-3 px-3 py-2">
                <span>{{ $r['label'] }}</span>
                @if ($r['ok'])<x-badge color="green">OK</x-badge>@elseif ($r['required'])<x-badge color="red">Manquant</x-badge>@else<x-badge color="amber">Conseillé</x-badge>@endif
            </li>
        @endforeach
    </ul>
    @if (! $ok)
        <p class="rounded-xl bg-red-50 p-3 text-sm text-red-800">Corrigez les éléments marqués « Manquant » (réglages PHP ou droits des dossiers chez votre hébergeur), puis rechargez cette page.</p>
    @elseif ($needsKey)
        <form method="POST" action="{{ route('install.unlock') }}" class="space-y-3">
            @csrf
            <x-field name="install_key" type="password" label="Clé d'installation (INSTALL_KEY du fichier .env)" required />
            <button class="btn-primary w-full">Continuer</button>
        </form>
    @else
        <a href="{{ route('install.database') }}" class="btn-primary w-full">Commencer l'installation</a>
    @endif
</x-layouts.install>
