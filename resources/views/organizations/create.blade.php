<x-layouts.app title="Créer une écurie">
    <x-page-header title="Créer un espace écurie" subtitle="Pour gérer plusieurs chevaux, membres, rôles et chevaux en pension" :back="route('organization.show')" />
    <form method="POST" action="{{ route('organizations.store') }}" class="max-w-xl space-y-4">
        @csrf
        <x-section>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="name" label="Nom de l'écurie" required class="sm:col-span-2" />
                <x-field name="email" type="email" label="Email" />
                <x-field name="phone" type="tel" label="Téléphone" />
                <x-field name="address_line" label="Adresse" class="sm:col-span-2" />
                <x-field name="postal_code" label="Code postal" />
                <x-field name="city" label="Ville" />
            </div>
        </x-section>
        <p class="text-sm text-slate-600">Vous en serez propriétaire. Un compte ne peut gérer qu'une seule écurie (offre Écurie). Votre espace personnel reste le vôtre : vos chevaux et vos séances de cavalier y restent, et vous pourrez transférer la gestion d'un cheval vers l'écurie si besoin.</p>
        <button class="btn-primary">Créer l'écurie</button>
    </form>
</x-layouts.app>
