<x-layouts.app title="Santé et soins">
    <x-page-header title="Santé et soins" />
    @include('health._hub-tabs')
    <x-empty title="Aucun cheval à afficher" icon="horse">Ajoutez un cheval, ou demandez l'accès à son dossier, pour suivre son alimentation, son suivi quotidien et son poids.</x-empty>
</x-layouts.app>
