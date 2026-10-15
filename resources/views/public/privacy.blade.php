<x-layouts.public title="Politique de confidentialité">
<article class="mx-auto max-w-3xl space-y-4 px-4 py-12 text-sm leading-relaxed text-slate-700 [&_h2]:mt-6 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-slate-900">
    <h1 class="text-2xl font-bold text-slate-900">Politique de confidentialité</h1>
    @if (auth()->user()?->is_super_admin)
        <p class="rounded-lg bg-amber-50 p-3 text-amber-900">Visible des super-administrateurs seulement : texte type, à faire relire par un juriste. Identité, hébergeur, contact et sous-traitants se règlent dans Administration › Réglages.</p>
    @endif
    <h2>Responsable du traitement</h2>
    <p>{{ $legal->entity() }}@if ($legal->siret()) (SIRET {{ $legal->siret() }})@endif — {{ $legal->address() }}. Contact pour vos données : <a href="mailto:{{ $legal->contact() }}" class="link">{{ $legal->contact() }}</a>.</p>
    <h2>Données traitées et finalités</h2>
    <ul class="list-disc pl-5">
        <li>Compte : nom, email, mot de passe chiffré, préférences — exécution du contrat.</li>
        <li>Données des chevaux, soins, séances, dépenses et documents saisis — exécution du contrat.</li>
        <li>Facturation : gérée par Stripe ; aucune donnée de carte bancaire n'est stockée par l'application — obligations légales et contrat.</li>
        <li>Journaux de sécurité (connexions, opérations sensibles) — intérêt légitime (sécurité), conservés 3 ans maximum.</li>
    </ul>
    <h2>Partage</h2>
    <p>Les informations d'un cheval ne sont visibles que par les personnes auxquelles un accès a été explicitement donné (propriétaire, membres d'écurie selon leur rôle, demi-pensions avec droits choisis). L'administration de la plateforme n'a pas d'accès implicite au contenu métier.</p>
    <h2>Sous-traitants</h2>
    <p>Ces prestataires traitent des données pour notre compte, uniquement pour fournir le service :</p>
    <ul class="list-disc pl-5" data-subprocessors>
        @foreach ($legal->subprocessors() as $line)<li>{{ $line }}</li>@endforeach
    </ul>
    <h2>Durées de conservation</h2>
    <p>Données du compte : durée du contrat. Après résiliation : conservation en lecture seule et export possible pendant {{ config('equine.billing.recovery_days') }} jours minimum. Éléments supprimés : purge définitive 30 jours après suppression. Factures : durée légale (10 ans).</p>
    <h2>Vos droits</h2>
    <p>Accès, rectification, effacement, portabilité (export JSON), limitation et opposition : depuis « Paramètres > Mes données » ou par email à {{ $support }}. Réclamation possible auprès de la CNIL.</p>
</article>
</x-layouts.public>
