@props(['org', 'feature'])
{{-- Étoile dorée : fonction non incluse dans l'offre de l'espace (le lien mène à la proposition d'offre supérieure). --}}
@if ($org && ! app(\App\Services\Entitlements::class)->hasFeature($org, $feature))
    <span class="relative inline-flex"><x-icon name="sparkles" class="size-3.5 text-gold-500" /><span class="sr-only"> (offre supérieure)</span></span>
@endif
