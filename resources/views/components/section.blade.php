@props(['title' => null, 'action' => null, 'actionLabel' => null])
<section {{ $attributes->merge(['class' => 'card p-4 sm:p-5']) }}>
    @if ($title)
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
            @if ($action)<a href="{{ $action }}" class="text-sm link">{{ $actionLabel ?? 'Voir tout' }}</a>@endif
            @isset($headerActions){{ $headerActions }}@endisset
        </div>
    @endif
    {{ $slot }}
</section>
