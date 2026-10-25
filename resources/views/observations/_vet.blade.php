{{-- Vétérinaire à joindre tout de suite : celui du cheval, sinon le premier vétérinaire des intervenants. --}}
@php
    $p = $vet['professional'] ?? null;
    $tel = $p?->phone ? preg_replace('/[^0-9+]/', '', $p->phone) : null;
@endphp
<div class="card p-5" data-attention-vet>
    @if ($p)
        <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">{{ $vet['source'] === 'horse' ? 'Vétérinaire de '.$horse->shortName() : 'Vétérinaire de vos intervenants' }}</p>
        <p class="mt-1 text-lg font-semibold text-slate-900">{{ $p->fullName() }}</p>
        <p class="text-sm text-slate-500">{{ collect([$p->company, $p->phone])->filter()->implode(' · ') }}</p>
        @if ($tel)
            <div class="mt-3 grid grid-cols-2 gap-2">
                <a href="tel:{{ $tel }}" class="btn-primary min-h-12 justify-center" data-attention-call><x-icon name="phone" class="size-4" /> Appeler</a>
                <a href="sms:{{ $tel }}?&body={{ rawurlencode($sms ?? '') }}" class="btn-secondary min-h-12 justify-center"><x-icon name="sms" class="size-4" /> SMS</a>
            </div>
        @elseif ($p->email)
            <a href="mailto:{{ $p->email }}" class="btn-secondary mt-3 w-full justify-center"><x-icon name="send" class="size-4" /> Écrire à {{ $p->email }}</a>
        @else
            <p class="mt-2 text-sm text-slate-500">Pas de numéro enregistré pour ce vétérinaire.</p>
        @endif
        @if ($vet['source'] === 'list' && auth()->user()->memberships->contains('organization_id', $horse->organization_id))
            <a href="{{ route('horses.professionals', $horse) }}" class="mt-2 inline-flex min-h-11 items-center text-sm link">Indiquer le vétérinaire de {{ $horse->shortName() }}</a>
        @endif
    @else
        <p class="font-semibold text-slate-900">Aucun vétérinaire enregistré</p>
        <p class="mt-1 text-sm text-slate-500">Ajoutez-le une fois : son numéro sera ici, à portée de main, la prochaine fois.</p>
        @if (auth()->user()->memberships->contains('organization_id', $horse->organization_id))
            <a href="{{ route('professionals.create') }}" class="btn-secondary mt-3 w-full justify-center"><x-icon name="plus" class="size-4" /> Ajouter un vétérinaire</a>
        @endif
    @endif
</div>
