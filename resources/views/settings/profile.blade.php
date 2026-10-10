<x-layouts.app title="Paramètres">
    <x-page-header title="Paramètres" />
    @include('settings._nav')
    <form method="POST" action="{{ route('settings.profile.update') }}" class="max-w-xl space-y-4">
        @csrf @method('PUT')
        <x-section title="Profil">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="name" label="Nom affiché" :value="$user->name" required class="sm:col-span-2" />
                <x-field name="email" type="email" label="Email" :value="$user->email" required class="sm:col-span-2" help="Un changement d'adresse nécessite une nouvelle vérification." />
                <x-field name="first_name" label="Prénom" :value="$profile->first_name" />
                <x-field name="last_name" label="Nom" :value="$profile->last_name" />
                <x-field name="phone" type="tel" label="Téléphone" :value="$profile->phone" />
                <x-field name="city" label="Ville" :value="$profile->city" />
                <x-field name="riding_level" label="Niveau équestre" :value="$profile->riding_level" class="sm:col-span-2" />
            </div>
            <div class="mt-3"><x-checkbox name="email_notifications" label="Recevoir les notifications par email" :checked="$profile->email_notifications ?? true" /></div>
        </x-section>
        <button class="btn-primary">Enregistrer</button>
    </form>
</x-layouts.app>
