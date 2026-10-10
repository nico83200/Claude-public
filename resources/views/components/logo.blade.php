@props(['compact' => false])
{{-- Logo Jack&Cie : typographie du logo (vert forêt, esperluette or) --}}
<picture {{ $attributes->merge(['class' => 'inline-flex items-center']) }}>
    <source srcset="/brand/jackcie-wordmark.webp" type="image/webp">
    <img src="/brand/jackcie-wordmark.png" alt="Jack&amp;Cie" width="900" height="264" class="{{ $compact ? 'h-7' : 'h-9' }} w-auto">
</picture>
