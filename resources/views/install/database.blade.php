<x-layouts.install :step="2" title="Base de données MySQL">
    <p class="mb-5 text-sm text-slate-600">Renseignez les accès fournis par votre hébergeur (MySQL 8 ou MariaDB 10.6 minimum). Les tables et les données de référence seront créées automatiquement ; une base existante n'est jamais vidée.</p>
    @unless ($envWritable)
        <p class="mb-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Le fichier <code>.env</code> n'est pas modifiable par le serveur : l'installation fonctionnera, puis vous devrez copier la configuration affichée à la fin.</p>
    @endunless
    <form method="POST" action="{{ route('install.database.save') }}" class="space-y-4" x-data="{ busy: false }" @submit="busy = true">
        @csrf
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field name="host" label="Hôte" :value="$defaults['host']" required class="sm:col-span-2" help="Souvent « localhost » ou « 127.0.0.1 »." />
            <x-field name="port" type="number" label="Port" :value="$defaults['port']" required />
        </div>
        <x-field name="database" label="Nom de la base" :value="$defaults['database']" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="username" label="Utilisateur" :value="$defaults['username']" required autocomplete="off" />
            <x-field name="password" type="password" label="Mot de passe" autocomplete="new-password" />
        </div>
        <x-checkbox name="create_database" label="Créer la base si elle n'existe pas" help="Nécessite le droit CREATE ; sinon créez-la depuis l'interface de votre hébergeur." :checked="old('create_database')" />
        <button class="btn-primary w-full" :disabled="busy"><span x-show="! busy">Tester la connexion et créer les tables</span><span x-show="busy" x-cloak>Installation de la base… (jusqu'à une minute)</span></button>
    </form>
</x-layouts.install>
