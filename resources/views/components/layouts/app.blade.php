<!DOCTYPE html>
<html lang="fr">
<head>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="app-user" content="{{ auth()->id() }}">
    @include('partials.head')
</head>
<body class="min-h-dvh" x-data="{ menu: false }">
@php
    $nav = [
        ['dashboard', 'Tableau de bord', 'home', true],
        ['horses.index', 'Chevaux', 'horse', true],
        ['calendar.index', 'Calendrier', 'calendar', in_array('calendar.view', $navPerms)],
        ['sessions.index', 'Séances', 'session', in_array('sessions.view', $navPerms)],
        ['exercises.index', 'Exercices', 'book', true],
        ['health.index', 'Santé et soins', 'health', in_array('health.view', $navPerms) || in_array('treatments.view', $navPerms)],
        ['professionals.index', 'Intervenants', 'users', in_array('health.view', $navPerms) || in_array('professionals.manage', $navPerms)],
        ['budget.index', 'Budget', 'wallet', in_array('expenses.view', $navPerms) || in_array('expenses.org_view', $navPerms)],
        ['sharing.index', 'Partages', 'share', true],
        ['organization.show', 'Organisation', 'building', true],
        ['billing.index', 'Abonnement', 'card', in_array('billing.manage', $navPerms)],
        ['settings.profile', 'Paramètres', 'cog', true],
    ];
@endphp
<div class="lg:flex">
    {{-- Barre latérale (ordinateur) / tiroir (mobile) --}}
    <div x-cloak x-show="menu" @click="menu = false" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"></div>
    <aside :class="menu ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0">
        <div class="flex h-16 items-center justify-between px-5">
            <a href="{{ route('dashboard') }}" aria-label="Accueil"><x-logo /></a>
            <button class="btn-ghost lg:hidden" @click="menu = false" aria-label="Fermer le menu"><x-icon name="x" /></button>
        </div>
        @if ($userMemberships->count() > 1)
            <form method="POST" action="{{ route('organizations.switch') }}" class="px-4 pb-3">
                @csrf
                <label for="org-switch" class="sr-only">Espace</label>
                <select id="org-switch" name="organization_id" class="input text-sm" onchange="this.form.submit()">
                    @foreach ($userMemberships as $m)
                        <option value="{{ $m->organization_id }}" @selected($m->organization_id === $currentOrganization?->id)>{{ $m->organization->name }}</option>
                    @endforeach
                </select>
            </form>
        @elseif ($currentOrganization)
            <p class="px-5 pb-3 text-xs text-slate-500">{{ $currentOrganization->name }}</p>
        @endif
        <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4" aria-label="Navigation principale">
            @foreach ($nav as [$route, $label, $icon, $visible])
                @if ($visible)
                    @php $active = request()->routeIs(\Illuminate\Support\Str::before($route, '.').'*'); @endphp
                    <a href="{{ route($route) }}" class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium {{ $active ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" @if ($active) aria-current="page" @endif>
                        <x-icon :name="$icon" /> {{ $label }}
                    </a>
                @endif
            @endforeach
            <a href="{{ route('offline.app') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium text-slate-600 hover:bg-slate-50"><x-icon name="offline" /> Mode écurie (hors ligne)</a>
            <button type="button" x-data x-show="! $store.install.installed" @click="$store.install.install(); menu = false" class="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-left text-sm font-medium text-brand-700 hover:bg-brand-50"><x-icon name="download" /> Installer sur mon téléphone</button>
            @can('super-admin')
                <a href="{{ route('admin.dashboard') }}" class="mt-3 flex min-h-11 items-center gap-3 rounded-lg bg-slate-900 px-3 text-sm font-medium text-white"><x-icon name="shield" /> Administration</a>
            @endcan
        </nav>
        <div class="border-t border-slate-200 p-4 text-sm">
            <p class="truncate font-medium text-slate-800">{{ auth()->user()->name }}</p>
            <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-2" data-logout>
                @csrf
                <button class="btn-ghost -ml-3 px-3 text-sm"><x-icon name="logout" class="size-4" /> Se déconnecter</button>
            </form>
        </div>
    </aside>

    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-2 border-b border-slate-200 bg-white/95 px-3 backdrop-blur sm:px-5">
            <button class="btn-ghost px-2 lg:hidden" @click="menu = true" aria-label="Ouvrir le menu"><x-icon name="menu" /></button>
            <form action="{{ route('search') }}" method="GET" class="relative min-w-0 flex-1 sm:max-w-md" role="search">
                <label for="global-search" class="sr-only">Rechercher</label>
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input id="global-search" name="q" value="{{ request('q') }}" placeholder="Rechercher…" class="input py-2 pl-9 text-sm">
            </form>
            <div class="ml-auto flex items-center gap-1">
                @include('partials.sync-indicator')
                <a href="{{ route('notifications.index') }}" class="btn-ghost relative px-2" aria-label="Notifications">
                    <x-icon name="bell" />
                    @if ($unreadNotifications > 0)<span class="absolute top-1.5 right-1 flex size-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white">{{ min($unreadNotifications, 9) }}</span>@endif
                </a>
            </div>
        </header>

        @if ($currentEntitlements && ! $currentEntitlements['writable'])
            <div class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
                <strong>Lecture seule —</strong> {{ $currentEntitlements['read_only_reason'] }}
                @if (in_array('billing.manage', $navPerms)) <a href="{{ route('billing.index') }}" class="link">Gérer l'abonnement</a>@endif
            </div>
        @endif

        <main class="mx-auto max-w-6xl px-4 pt-5 pb-28 sm:px-6 lg:pb-10">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Navigation basse (mobile) : actions fréquentes --}}
<nav class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-5 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Navigation rapide">
    @foreach ([['dashboard', 'Accueil', 'home'], ['horses.index', 'Chevaux', 'horse'], ['sessions.index', 'Séances', 'session'], ['calendar.index', 'Agenda', 'calendar'], ['offline.app', 'Écurie', 'offline']] as [$route, $label, $icon])
        @php $active = request()->routeIs(\Illuminate\Support\Str::before($route, '.').'*'); @endphp
        <a href="{{ route($route) }}" class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] {{ $active ? 'text-brand-700' : 'text-slate-500' }}"><x-icon :name="$icon" class="size-6" />{{ $label }}</a>
    @endforeach
</nav>
@include('partials.install')
@stack('scripts')
</body>
</html>
