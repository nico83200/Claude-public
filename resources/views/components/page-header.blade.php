@props(['title', 'subtitle' => null, 'back' => null])
<div class="mb-6 flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
    <div class="max-w-full">
        @if ($back)
            <a href="{{ $back }}" class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800"><x-icon name="back" class="size-4" /> Retour</a>
        @endif
        <h1 class="text-[26px] leading-tight font-bold break-words text-slate-900 sm:text-[32px]">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 text-[15px] text-slate-500">{{ $subtitle }}</p>@endif
    </div>
    @if (trim($slot))<div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>@endif
</div>
