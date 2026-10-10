<nav class="-mx-4 mb-5 overflow-x-auto border-b border-slate-200 px-4 sm:mx-0 sm:px-0" aria-label="Administration"><div class="flex gap-1">
    @foreach (['admin.dashboard' => 'Indicateurs', 'admin.users.*' => 'Utilisateurs', 'admin.organizations.*' => 'Organisations', 'admin.plans.*' => 'Offres', 'admin.billing.*' => 'Facturation', 'admin.data-requests.*' => 'RGPD', 'admin.audit.*' => 'Journal', 'admin.errors.*' => 'Erreurs', 'admin.settings.*' => 'Paramètres'] as $route => $label)
        <a href="{{ route(str_replace('.*', '.index', $route) === 'admin.settings.index' ? 'admin.settings.edit' : str_replace('.*', '.index', $route)) }}" class="shrink-0 border-b-2 px-3 py-2.5 text-sm font-medium {{ request()->routeIs($route) ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500' }}">{{ $label }}</a>
    @endforeach
</div></nav>
