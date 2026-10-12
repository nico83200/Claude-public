@props(['professional', 'sms' => null])
{{-- Intervenant : nom vers sa fiche (si l'espace la partage) ; sur téléphone, boutons Appeler et SMS. --}}
@php
    $p = $professional;
    $canOpen = auth()->user()?->memberships->contains('organization_id', $p->organization_id);
    $tel = $p->phone ? preg_replace('/[^0-9+]/', '', $p->phone) : null;
@endphp
<div {{ $attributes->merge(['class' => 'rounded-2xl bg-slate-50 p-3']) }} data-professional-contact>
    <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Intervenant</p>
    @if ($canOpen)
        <a href="{{ route('professionals.show', $p) }}" class="mt-0.5 block font-semibold link">{{ $p->fullName() }}</a>
    @else
        <p class="mt-0.5 font-semibold text-slate-900">{{ $p->fullName() }}</p>
    @endif
    <p class="text-sm text-slate-500">{{ collect([$p->kindLabel(), $p->company, $p->phone])->filter()->implode(' · ') }}</p>
    @if ($tel)
        <div x-data x-show="$store.install.phone" x-cloak class="mt-3 grid grid-cols-2 gap-2" data-phone-actions>
            <a href="tel:{{ $tel }}" class="btn-primary justify-center"><x-icon name="phone" class="size-4" /> Appeler</a>
            <a href="sms:{{ $tel }}{{ $sms ? '?&body='.rawurlencode($sms) : '' }}" class="btn-secondary justify-center"><x-icon name="sms" class="size-4" /> SMS</a>
        </div>
    @endif
</div>
