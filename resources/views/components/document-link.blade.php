{{-- Document d'un cheval (soin, dossier) : image ou PDF ouverts en plein écran dans la visionneuse de l'application, autres fichiers téléchargés. --}}
@props(['horse', 'document', 'group' => null, 'caption' => null])
@php
    $type = $document->viewerType();
    $url = route('horses.documents.download', [$horse, $document]);
@endphp
<a href="{{ $url }}" {{ $attributes->merge(['class' => 'link']) }}
   @if ($type) data-lightbox="{{ $group ?? 'doc-'.$document->id }}" data-lightbox-type="{{ $type }}" data-lightbox-download="{{ $url }}?telecharger=1" data-caption="{{ $caption ?? $document->title }}" @else download @endif
   data-document-link>{{ $slot }}</a>
