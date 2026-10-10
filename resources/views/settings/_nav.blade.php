<nav class="-mx-4 mb-5 overflow-x-auto border-b border-slate-200 px-4 sm:mx-0 sm:px-0"><div class="flex gap-1">
    @foreach (['settings.profile' => 'Profil', 'settings.security' => 'Sécurité', 'settings.devices' => 'Appareils hors ligne', 'settings.privacy' => 'Mes données'] as $route => $label)
        <a href="{{ route($route) }}" class="shrink-0 border-b-2 px-3 py-2.5 text-sm font-medium {{ request()->routeIs($route) ? 'border-brand-600 text-brand-800' : 'border-transparent text-slate-500' }}">{{ $label }}</a>
    @endforeach
</div></nav>
