{{-- Justificatif d'une dépense : image ou PDF ouverts dans la visionneuse de l'application, autres fichiers téléchargés. --}}
@props(['expense', 'document', 'group' => null])
@php
    $type = $document->viewerType();
    $url = route('budget.receipt', [$expense, $document]);
    $caption = 'Justificatif · '.($expense->category?->name ?? 'Dépense').($document->original_name ? ' · '.$document->original_name : '');
@endphp
<a href="{{ $url }}" {{ $attributes->merge(['class' => 'link']) }}
   @if ($type) data-lightbox="{{ $group ?? 'receipt-'.$expense->id }}" data-lightbox-type="{{ $type }}" data-lightbox-download="{{ $url }}?telecharger=1" data-caption="{{ $caption }}" @else download @endif
   data-receipt-link>{{ $slot }}</a>
