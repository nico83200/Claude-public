<x-layouts.guest title="Adresse confirmée">
    <div class="text-center">
        <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-brand-100 text-brand-800"><x-icon name="check" class="size-7" /></span>
        <h1 class="mt-4 text-xl font-bold">Adresse email confirmée</h1>
        <p class="mt-2 text-[15px] text-slate-600">Retournez dans l'application Jack&amp;Cie et touchez « J'ai confirmé » (ou rouvrez-la) pour continuer.</p>
        <a href="{{ route('login') }}" x-data x-show="! $store.install.handheld" class="btn-primary mt-6">Se connecter</a>
    </div>
</x-layouts.guest>
