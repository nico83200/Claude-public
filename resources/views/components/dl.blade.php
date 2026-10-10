@props(['items' => []])
<dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
    @foreach ($items as $label => $value)
        @if ($value !== null && $value !== '')
            <div><dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">{{ $label }}</dt><dd class="mt-0.5 text-sm whitespace-pre-line text-slate-800">{{ $value }}</dd></div>
        @endif
    @endforeach
</dl>
