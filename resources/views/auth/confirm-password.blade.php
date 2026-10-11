@php
    $user = auth()->user();
    $linked = $user ? $user->socialAccounts()->pluck('provider')->all() : [];
@endphp
<x-layouts.guest title="Confirmer le mot de passe">
    <h1 class="mb-2 text-xl font-bold">Confirmation requise</h1>
    <p class="mb-6 text-sm text-slate-600">Pour cette opération sensible, confirmez {{ $user?->has_password ? 'votre mot de passe' : 'votre identité' }}.</p>
    @if ($linked)
        <x-social-buttons intent="confirm" label="Confirmer avec" :only="$linked" />
    @endif
    @if ($user?->has_password)
        @if ($linked)<div class="my-6 flex items-center gap-3 text-xs text-slate-400" role="separator"><span class="h-px flex-1 bg-slate-200"></span>ou avec votre mot de passe<span class="h-px flex-1 bg-slate-200"></span></div>@endif
        <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
            @csrf
            <x-field name="password" type="password" label="Mot de passe" required autofocus autocomplete="current-password" />
            <button class="btn-primary w-full">Confirmer</button>
        </form>
    @elseif (! $linked || ! \App\Services\Auth\OidcProvider::available())
        <p class="text-sm text-slate-600">Votre compte n'a pas encore de mot de passe. <a href="{{ route('password.request') }}" class="link">Définissez-en un</a> (lien envoyé par email), puis revenez.</p>
    @endif
</x-layouts.guest>
