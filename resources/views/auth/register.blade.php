<x-layouts.guest title="Créer un compte">
{{-- Inscription possible partout, y compris dans le navigateur d'un téléphone : l'installation est proposée juste après. --}}
<div>
    <h1 class="mb-1 text-xl font-bold">Créer un compte</h1>
    <p class="mb-6 text-sm text-slate-500">Déjà inscrit ? <a href="{{ route('login') }}" class="link">Se connecter</a></p>
    @if (! \App\Models\FeatureFlag::enabled('registration'))
        <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Les inscriptions sont temporairement fermées.</p>
    @else
    @if (session('referral_name'))
        <p class="mb-5 rounded-2xl bg-gold-50 px-4 py-3 text-sm text-slate-800 ring-1 ring-gold-200" data-referral-banner>Bienvenue ! Vous êtes invité par <strong>{{ session('referral_name') }}</strong> à rejoindre Jack&Cie.</p>
    @endif
    @if (session('signup_code'))
        <p class="mb-5 rounded-2xl bg-brand-50 px-4 py-3 text-sm text-brand-900" data-signup-banner>@php $signupPromo = \App\Models\PromoCode::where('code', \App\Models\PromoCode::normalize((string) session('signup_code')))->first(); @endphp
            Code <strong>{{ session('signup_code') }}</strong>{{ $signupPromo?->free_days && ! $signupPromo->unavailableReason() ? ' : '.$signupPromo->free_days.' jours offerts' : '' }}, activé à la création de votre compte, sans carte bancaire.</p>
    @endif
    @if (\App\Services\Auth\OidcProvider::available())
        <x-social-buttons />
        <p class="mt-3 text-center text-xs text-slate-500">En continuant avec Google ou Apple, vous acceptez les <a href="{{ route('legal.terms') }}" target="_blank" class="link">conditions d'utilisation</a> et la <a href="{{ route('legal.privacy') }}" target="_blank" class="link">politique de confidentialité</a>.</p>
        <div class="my-6 flex items-center gap-3 text-xs text-slate-400" role="separator"><span class="h-px flex-1 bg-slate-200"></span>ou avec votre email<span class="h-px flex-1 bg-slate-200"></span></div>
    @endif
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <x-field name="name" label="Nom et prénom" required autofocus autocomplete="name" />
        <x-field name="email" type="email" label="Adresse email" required autocomplete="email" />
        <x-field name="password" type="password" label="Mot de passe" required autocomplete="new-password" help="10 caractères minimum, avec lettres et chiffres." />
        <x-field name="password_confirmation" type="password" label="Confirmer le mot de passe" required autocomplete="new-password" />
        @php $signupCode = old('promo_code', session('signup_code')); @endphp
        <div x-data="{ open: @js(filled($signupCode) || $errors->has('promo_code')) }" data-signup-code>
            <button type="button" class="text-sm link" x-show="!open" @click="open = true; $nextTick(() => $refs.code.focus())">J'ai un code promo</button>
            <div x-show="open" x-cloak>
                <label for="promo_code" class="label">Code promo <span class="font-normal text-slate-500">(facultatif)</span></label>
                <input id="promo_code" name="promo_code" x-ref="code" value="{{ $signupCode }}" maxlength="40" autocomplete="off" autocapitalize="characters" class="input uppercase" @error('promo_code') aria-invalid="true" aria-describedby="promo_code_error" @enderror>
                @error('promo_code')<p id="promo_code_error" class="mt-1 text-sm text-red-600">{{ $message }}</p>@else<p class="mt-1 text-xs text-slate-500">Un code d'accès gratuit est activé dès la création du compte, sans carte bancaire.</p>@enderror
            </div>
        </div>
        <label class="flex cursor-pointer items-start gap-3 text-sm text-slate-700">
            <input type="checkbox" name="terms" value="1" @checked(old('terms')) class="mt-0.5 size-5 rounded border-slate-300 text-brand-600">
            <span>J'accepte les <a href="{{ route('legal.terms') }}" target="_blank" class="link">conditions d'utilisation</a> et la <a href="{{ route('legal.privacy') }}" target="_blank" class="link">politique de confidentialité</a>.</span>
        </label>
        @error('terms')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <button class="btn-primary w-full">Créer mon compte</button>
    </form>
    @endif
</div>
</x-layouts.guest>
