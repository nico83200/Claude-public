{{-- Vignettes ouvrant la visionneuse ; fichiers servis après contrôle d'accès. --}}
@if ($photos->isNotEmpty())
    <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4" data-attention-photos>
        @foreach ($photos as $photo)
            @php $url = route('horses.observations.photo', [$horse, $point, $photo]); @endphp
            <a href="{{ $url }}" data-lightbox="attention-{{ $point->id }}"><img src="{{ $url }}?thumb=1" alt="Photo jointe" loading="lazy" class="aspect-square w-full rounded-xl object-cover"></a>
        @endforeach
    </div>
@endif
