<x-layouts.public title="Politique de confidentialité">
<article class="mx-auto max-w-3xl space-y-4 px-4 py-12 text-sm leading-relaxed text-slate-700 [&_h2]:mt-6 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-slate-900">
    <h1 class="text-2xl font-bold text-slate-900">Politique de confidentialité</h1>
    <p class="rounded-lg bg-amber-50 p-3 text-amber-900">Modèle à faire valider juridiquement et à compléter (raison sociale, adresse, DPO, hébergeur) depuis l'administration avant la mise en production.</p>
    <h2>Responsable du traitement</h2>
    <p>{{ \App\Models\ApplicationSetting::get('legal_entity', '[Raison sociale à compléter]') }} — {{ \App\Models\ApplicationSetting::get('legal_address', '[adresse à compléter]') }}. Contact : {{ \App\Models\ApplicationSetting::get('dpo_email', $support) }}.</p>
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
    <p>{!! nl2br(e(\App\Models\ApplicationSetting::get('subprocessors', "Hébergement : [à compléter]\nPaiement : Stripe Payments Europe Ltd.\nEnvoi d'emails : [à compléter]"))) !!}</p>
    <h2>Stockage hors ligne</h2>
    <p>Le mode écurie conserve sur votre appareil une copie limitée des chevaux que vous avez choisis. Cette copie expire automatiquement, est effacée à la déconnexion et peut être purgée à distance depuis « Paramètres > Appareils ». Une copie déjà présente sur un appareil resté hors ligne ne peut pas être effacée instantanément : elle l'est dès sa reconnexion ou à expiration.</p>
    <h2>Durées de conservation</h2>
    <p>Données du compte : durée du contrat. Après résiliation : conservation en lecture seule et export possible pendant {{ config('equine.billing.recovery_days') }} jours minimum. Éléments supprimés : purge définitive 30 jours après suppression. Factures : durée légale (10 ans).</p>
    <h2>Vos droits</h2>
    <p>Accès, rectification, effacement, portabilité (export JSON), limitation et opposition : depuis « Paramètres > Mes données » ou par email à {{ $support }}. Réclamation possible auprès de la CNIL.</p>
</article>
</x-layouts.public>
