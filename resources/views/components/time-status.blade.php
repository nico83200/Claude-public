@props(['status'])
{{-- Statut d'une séance ou d'un rendez-vous selon l'heure (App\Support\TimeStatus), tenu à jour sur la page ouverte. --}}
@php $live = $status->clientData(); @endphp
<span {{ $attributes->merge(['class' => 'chip '.\App\Support\Badge::classes($status->color)]) }} data-status="{{ $status->key }}" @if ($live) data-time-status="{{ json_encode($live) }}" @endif>{{ $status->label }}</span>
