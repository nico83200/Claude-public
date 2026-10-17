@props(['color' => 'slate'])
<span {{ $attributes->merge(['class' => 'chip '.\App\Support\Badge::classes($color)]) }}>{{ $slot }}</span>
