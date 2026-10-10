<x-layouts.public title="Conditions d'utilisation">
<article class="mx-auto max-w-3xl space-y-4 px-4 py-12 text-sm leading-relaxed text-slate-700 [&_h2]:mt-6 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-slate-900">
    <h1 class="text-2xl font-bold text-slate-900">Conditions générales d'utilisation et de vente</h1>
    <p class="rounded-lg bg-amber-50 p-3 text-amber-900">Modèle à faire valider juridiquement avant la mise en production.</p>
    <h2>Service</h2>
    <p>{{ $appName }} est un outil d'organisation et de suivi. Il ne fournit aucun diagnostic, prescription ou conseil vétérinaire : les traitements enregistrés reflètent les prescriptions de professionnels de santé.</p>
    <h2>Abonnements</h2>
    <p>Les abonnements sont mensuels ou annuels, renouvelés automatiquement, payables via Stripe. Le prix applicable est celui affiché lors de la souscription. Toute évolution tarifaire est notifiée au moins 30 jours avant son application ; vous pouvez résilier avant cette date.</p>
    <h2>Résiliation et fin d'abonnement</h2>
    <p>La résiliation prend effet à la fin de la période payée. En cas d'échec de paiement, une période de grâce de {{ config('equine.billing.grace_days') }} jours s'applique, puis l'espace passe en lecture seule. Les données ne sont pas supprimées : elles restent consultables et exportables.</p>
    <h2>Données</h2>
    <p>Vous restez propriétaire des données saisies. Voir la <a href="{{ route('legal.privacy') }}" class="link">politique de confidentialité</a>.</p>
    <h2>Contact</h2>
    <p>{{ $support }}</p>
</article>
</x-layouts.public>
