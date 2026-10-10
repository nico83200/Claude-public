@foreach (['success' => 'border-emerald-200 bg-emerald-50 text-emerald-900', 'error' => 'border-red-200 bg-red-50 text-red-900', 'warning' => 'border-amber-200 bg-amber-50 text-amber-900', 'status' => 'border-sky-200 bg-sky-50 text-sky-900'] as $key => $classes)
    @if (session($key))
        <div x-data="{ open: true }" x-show="open" role="status" class="mb-4 flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm {{ $classes }}">
            <span>{{ session($key) === 'two-factor-authentication-enabled' ? 'Authentification à deux facteurs activée : confirmez-la avec un code.' : (session($key) === 'two-factor-authentication-confirmed' ? 'Authentification à deux facteurs confirmée.' : (session($key) === 'two-factor-authentication-disabled' ? 'Authentification à deux facteurs désactivée.' : (session($key) === 'verification-link-sent' ? 'Un nouveau lien de vérification a été envoyé.' : (session($key) === 'profile-information-updated' ? 'Profil mis à jour.' : (session($key) === 'password-updated' ? 'Mot de passe modifié.' : session($key)))))) }}</span>
            <button type="button" @click="open = false" class="opacity-60 hover:opacity-100" aria-label="Fermer"><x-icon name="x" class="size-4" /></button>
        </div>
    @endif
@endforeach
@if ($errors->any() && ! isset($hideErrorSummary))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
        Le formulaire contient {{ $errors->count() > 1 ? $errors->count().' erreurs' : 'une erreur' }} : vérifiez les champs signalés.
    </div>
@endif
