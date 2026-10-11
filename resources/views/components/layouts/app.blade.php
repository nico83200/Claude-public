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
        ['dashboard', 'Accueil', 'home', true],
        ['horses.index', 'Chevaux', 'horse', true],
        ['calendar.index', 'Agenda', 'calendar', in_array('calendar.view', $navPerms)],
        ['sessions.index', 'Séances', 'session', in_array('sessions.view', $navPerms)],
        ['exercises.index', 'Exercices', 'book', true],
        ['health.index', 'Santé', 'health', in_array('health.view', $navPerms) || in_array('treatments.view', $navPerms)],
        ['professionals.index', 'Intervenants', 'users', in_array('health.view', $navPerms) || in_array('professionals.manage', $navPerms)],
        ['budget.index', 'Budget', 'wallet', in_array('expenses.view', $navPerms) || in_array('expenses.org_view', $navPerms)],
        ['sharing.index', 'Partages', 'share', true],
        ['assistant.history', 'Assistant', 'sparkles', true],
    ];
    $secondary = [
        ['organization.show', 'Organisation', 'building', true],
        ['billing.index', 'Abonnement', 'card', in_array('billing.manage', $navPerms)],
        ['settings.profile', 'Paramètres', 'cog', true],
    ];
    $contextHorseId = optional(request()->route('horse'))->id ?? (is_numeric(request()->route('horse')) ? (int) request()->route('horse') : null);
@endphp
<div class="lg:flex">
    {{-- Barre latérale (ordinateur) / tiroir (mobile) --}}
    <div x-cloak x-show="menu" x-transition.opacity @click="menu = false" class="fixed inset-0 z-40 bg-slate-900/30 backdrop-blur-[2px] lg:hidden"></div>
    <aside :class="menu ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-white transition-transform duration-300 lg:sticky lg:top-0 lg:z-10 lg:h-dvh lg:w-64 lg:translate-x-0 lg:bg-transparent">
        <div class="flex h-16 items-center justify-between px-6">
            <a href="{{ route('dashboard') }}" aria-label="Accueil"><x-logo compact /></a>
            <button class="btn-ghost px-2 lg:hidden" @click="menu = false" aria-label="Fermer le menu"><x-icon name="x" /></button>
        </div>
        @if ($userMemberships->count() > 1)
            <form method="POST" action="{{ route('organizations.switch') }}" class="px-4 pb-3">
                @csrf
                <label for="org-switch" class="sr-only">Espace</label>
                <select id="org-switch" name="organization_id" class="input py-2 text-sm" onchange="this.form.submit()">
                    @foreach ($userMemberships as $m)
                        <option value="{{ $m->organization_id }}" @selected($m->organization_id === $currentOrganization?->id)>{{ $m->organization->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
        <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4" aria-label="Navigation principale">
            @foreach ($nav as [$route, $label, $icon, $visible])
                @if ($visible)
                    @php $active = request()->routeIs(\Illuminate\Support\Str::before($route, '.').'*'); @endphp
                    <a href="{{ route($route) }}" class="flex min-h-11 items-center gap-3 rounded-full px-4 text-[15px] font-medium transition {{ $active ? 'bg-white text-brand-900 shadow-[var(--shadow-card)] lg:bg-white' : 'text-slate-600 hover:bg-white/70 hover:text-slate-900' }}" @if ($active) aria-current="page" @endif>
                        <x-icon :name="$icon" class="size-5 {{ $active ? 'text-brand-700' : '' }}" /> {{ $label }}
                    </a>
                @endif
            @endforeach
            <div class="my-3 border-t border-slate-200/70"></div>
            @foreach ($secondary as [$route, $label, $icon, $visible])
                @if ($visible)
                    @php $active = request()->routeIs(\Illuminate\Support\Str::before($route, '.').'*'); @endphp
                    <a href="{{ route($route) }}" class="flex min-h-10 items-center gap-3 rounded-full px-4 text-sm {{ $active ? 'bg-white font-medium text-brand-900 shadow-[var(--shadow-card)]' : 'text-slate-500 hover:bg-white/70 hover:text-slate-900' }}"><x-icon :name="$icon" class="size-[18px]" /> {{ $label }}</a>
                @endif
            @endforeach
            <button type="button" x-data x-show="! $store.install.installed" @click="$store.install.install(); menu = false" class="flex min-h-10 w-full items-center gap-3 rounded-full px-4 text-left text-sm font-medium text-brand-700 hover:bg-white/70"><x-icon name="download" class="size-[18px]" /> Installer sur mon téléphone</button>
            @can('super-admin')
                <a href="{{ route('admin.dashboard') }}" class="mt-2 flex min-h-10 items-center gap-3 rounded-full bg-slate-900 px-4 text-sm font-medium text-white"><x-icon name="shield" class="size-[18px]" /> Administration</a>
            @endcan
        </nav>
        <div class="flex items-center gap-3 px-6 py-4">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-800">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-slate-800">{{ auth()->user()->name }}</span><span class="block truncate text-xs text-slate-500">{{ $currentOrganization?->name }}</span></span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn-ghost px-2" aria-label="Se déconnecter" title="Se déconnecter"><x-icon name="logout" class="size-[18px]" /></button>
            </form>
        </div>
    </aside>

    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-2 bg-slate-50/85 px-4 backdrop-blur-md sm:px-6" x-data="{ search: false }">
            <button class="btn-ghost -ml-2 px-2 lg:hidden" @click="menu = true" aria-label="Ouvrir le menu"><x-icon name="menu" /></button>
            <a href="{{ route('dashboard') }}" class="lg:hidden" x-show="! search" aria-label="Accueil"><x-logo compact /></a>
            <form action="{{ route('search') }}" method="GET" class="relative min-w-0 flex-1 sm:max-w-md" :class="search ? 'block' : 'hidden sm:block'" role="search">
                <label for="global-search" class="sr-only">Rechercher</label>
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-400" />
                <input id="global-search" name="q" value="{{ request('q') }}" placeholder="Rechercher un cheval, un soin…" class="input rounded-full border-transparent bg-white py-2.5 pl-10 text-sm shadow-[var(--shadow-card)]" x-ref="q">
            </form>
            <div class="ml-auto flex items-center gap-1">
                <button type="button" class="btn-ghost px-2 sm:hidden" @click="search = ! search; $nextTick(() => search && $refs.q.focus())" aria-label="Rechercher"><x-icon name="search" /></button>
                <span x-data x-cloak x-show="! $store.network.online" class="chip bg-amber-100 text-amber-800" role="status">Pas de connexion</span>
                <a href="{{ route('notifications.index') }}" class="btn-ghost relative px-2" aria-label="Notifications">
                    <x-icon name="bell" />
                    @if ($unreadNotifications > 0)<span class="absolute top-1.5 right-1 flex size-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white">{{ min($unreadNotifications, 9) }}</span>@endif
                </a>
                <button type="button" @click="$store.assistant.open()" class="btn-primary ml-1 hidden px-4 lg:inline-flex" aria-label="Parler à l'assistant"><x-icon name="mic" class="size-[18px]" /> Parler</button>
            </div>
        </header>

        @if ($currentEntitlements && ! $currentEntitlements['writable'])
            <div class="mx-4 mb-2 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:mx-6">
                <strong>Lecture seule —</strong> {{ $currentEntitlements['read_only_reason'] }}
                @if (in_array('billing.manage', $navPerms)) <a href="{{ route('billing.index') }}" class="link">Gérer l'abonnement</a>@endif
            </div>
        @endif

        <main class="mx-auto max-w-6xl px-4 pt-4 pb-32 sm:px-6 lg:pb-12">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Barre basse (mobile) : le micro au centre --}}
<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/70 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md lg:hidden" aria-label="Navigation rapide">
    <div class="grid grid-cols-5 items-end">
        @foreach ([['dashboard', 'Accueil', 'home'], ['horses.index', 'Chevaux', 'horse']] as [$route, $label, $icon])
            @php $active = request()->routeIs(\Illuminate\Support\Str::before($route, '.').'*'); @endphp
            <a href="{{ route($route) }}" class="flex min-h-16 flex-col items-center justify-center gap-1 text-[11px] font-medium {{ $active ? 'text-brand-800' : 'text-slate-400' }}"><x-icon :name="$icon" class="size-6" />{{ $label }}</a>
        @endforeach
        <div class="flex justify-center">
            <button type="button" @click="$store.assistant.open(true)" class="relative -mt-7 flex size-16 items-center justify-center rounded-full bg-brand-800 text-white shadow-[var(--shadow-float)] ring-4 ring-white transition active:scale-95" aria-label="Parler à l'assistant">
                <x-icon name="mic" class="size-7" />
            </button>
        </div>
        @php $active = request()->routeIs('calendar*'); @endphp
        <a href="{{ route('calendar.index') }}" class="flex min-h-16 flex-col items-center justify-center gap-1 text-[11px] font-medium {{ $active ? 'text-brand-800' : 'text-slate-400' }}"><x-icon name="calendar" class="size-6" />Agenda</a>
        <button type="button" @click="menu = true" class="flex min-h-16 flex-col items-center justify-center gap-1 text-[11px] font-medium text-slate-400"><x-icon name="menu" class="size-6" />Menu</button>
    </div>
</nav>
@include('partials.assistant', ['contextHorseId' => $contextHorseId])
@include('partials.install', ['bannerBottom' => 'bottom-[calc(5.5rem+env(safe-area-inset-bottom))]'])
@auth @include('partials.push-gate') @endauth
@stack('scripts')
</body>
</html>
