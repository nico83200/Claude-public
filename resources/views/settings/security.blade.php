<x-layouts.app title="Sécurité">
    <x-page-header title="Paramètres" />
    @include('settings._nav')
    <div class="grid max-w-4xl gap-4 lg:grid-cols-2">
        <x-section title="Mot de passe">
            <form method="POST" action="{{ route('user-password.update') }}" class="space-y-3">
                @csrf @method('PUT')
                <x-field name="current_password" type="password" label="Mot de passe actuel" required autocomplete="current-password" />
                <x-field name="password" type="password" label="Nouveau mot de passe" required autocomplete="new-password" />
                <x-field name="password_confirmation" type="password" label="Confirmer" required autocomplete="new-password" />
                @foreach ($errors->updatePassword->all() as $e)<p class="text-sm text-red-600">{{ $e }}</p>@endforeach
                <button class="btn-primary">Modifier</button>
            </form>
        </x-section>
        <x-section title="Double authentification (2FA)">
            @if ($user->hasConfirmedTwoFactor())
                <p class="mb-3 text-sm text-emerald-700">Activée. Un code de votre application d'authentification est demandé à chaque connexion.</p>
                <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}" class="mb-2">@csrf<button class="btn-secondary w-full">Régénérer les codes de récupération</button></form>
                <details class="mb-2 text-sm"><summary class="cursor-pointer link">Afficher les codes de récupération</summary><ul class="mt-2 grid grid-cols-2 gap-1 font-mono text-xs">@foreach ($user->recoveryCodes() as $code)<li>{{ $code }}</li>@endforeach</ul></details>
                <form method="POST" action="{{ route('two-factor.disable') }}" onsubmit="return confirm('Désactiver la double authentification ?')">@csrf @method('DELETE')<button class="btn-ghost w-full text-red-600">Désactiver</button></form>
            @elseif ($user->two_factor_secret)
                <p class="mb-3 text-sm">Scannez ce QR code avec votre application (Google Authenticator, Aegis, 1Password…) puis saisissez le code affiché.</p>
                <div class="mb-3 inline-block rounded-lg bg-white p-2">{!! $user->twoFactorQrCodeSvg() !!}</div>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-2">@csrf<x-field name="code" label="Code" inputmode="numeric" autocomplete="one-time-code" required />@foreach ($errors->confirmTwoFactorAuthentication->all() as $e)<p class="text-sm text-red-600">{{ $e }}</p>@endforeach<button class="btn-primary w-full">Confirmer</button></form>
            @else
                <p class="mb-3 text-sm text-slate-600">Protégez votre compte avec un code temporaire. {{ $user->is_super_admin ? 'Obligatoire pour l\'administration.' : '' }}</p>
                <form method="POST" action="{{ route('two-factor.enable') }}">@csrf<button class="btn-primary w-full">Activer</button></form>
            @endif
        </x-section>
    </div>
</x-layouts.app>
