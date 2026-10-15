<x-layouts.app title="Sécurité">
    <x-page-header title="Paramètres" />
    @include('settings._nav')
    <div class="grid max-w-4xl gap-4 lg:grid-cols-2">
        <x-section title="Mot de passe">
            @unless ($user->has_password)
                <p class="mb-3 text-sm text-slate-600">Vous vous connectez avec {{ $linked->keys()->map(fn ($p) => \App\Models\SocialAccount::PROVIDERS[$p])->implode(' ou ') ?: 'Google ou Apple' }}. Vous pouvez aussi définir un mot de passe pour vous connecter avec votre email.</p>
                <form method="POST" action="{{ route('settings.password.link') }}">@csrf<button class="btn-primary">Recevoir un lien pour définir un mot de passe</button></form>
            @else
            <form method="POST" action="{{ route('user-password.update') }}" class="space-y-3">
                @csrf @method('PUT')
                <x-field name="current_password" type="password" label="Mot de passe actuel" required autocomplete="current-password" />
                <x-field name="password" type="password" label="Nouveau mot de passe" required autocomplete="new-password" />
                <x-field name="password_confirmation" type="password" label="Confirmer" required autocomplete="new-password" />
                @foreach ($errors->updatePassword->all() as $e)<p class="text-sm text-red-600">{{ $e }}</p>@endforeach
                <button class="btn-primary">Modifier</button>
            </form>
            @endunless
        </x-section>
        @if ($socialProviders)
            <x-section title="Connexion avec Google ou Apple" class="lg:col-span-2">
                <ul class="divide-y divide-slate-100">
                    @foreach ($socialProviders as $p)
                        <li class="flex flex-wrap items-center gap-3 py-3" data-provider="{{ $p }}">
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-slate-900">{{ \App\Models\SocialAccount::PROVIDERS[$p] }}</span>
                                <span class="block text-sm text-slate-500">{{ $linked->has($p) ? 'Associé'.($linked[$p]->email ? ' · '.$linked[$p]->email : '') : 'Non associé' }}</span>
                            </span>
                            @if ($linked->has($p))
                                <form method="POST" action="{{ route('social.unlink', $p) }}" data-confirm="Ne plus pouvoir vous connecter avec {{ \App\Models\SocialAccount::PROVIDERS[$p] }} ?" data-confirm-ok="Dissocier">@csrf @method('DELETE')<button class="btn-ghost text-red-600">Retirer</button></form>
                            @elseif (\App\Services\Auth\OidcProvider::configured($p))
                                <a href="{{ route('social.redirect', $p) }}?intent=link" class="btn-secondary">Associer</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-slate-500">Une fois associé, vous pouvez vous connecter d'un geste, sans mot de passe. La double authentification reste demandée si elle est activée.</p>
            </x-section>
        @endif
        <x-section title="Double authentification (2FA)">
            @if ($user->hasConfirmedTwoFactor())
                <p class="mb-3 text-sm text-emerald-700">Activée. Un code de votre application d'authentification est demandé à chaque connexion.</p>
                <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}" class="mb-2">@csrf<button class="btn-secondary w-full">Régénérer les codes de récupération</button></form>
                <details class="mb-2 text-sm"><summary class="cursor-pointer link">Afficher les codes de récupération</summary><ul class="mt-2 grid grid-cols-2 gap-1 font-mono text-xs">@foreach ($user->recoveryCodes() as $code)<li>{{ $code }}</li>@endforeach</ul></details>
                <form method="POST" action="{{ route('two-factor.disable') }}" data-confirm="Désactiver la double authentification ?" data-confirm-ok="Désactiver">@csrf @method('DELETE')<button class="btn-ghost w-full text-red-600">Désactiver</button></form>
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
