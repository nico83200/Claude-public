@props(['title', 'icon' => 'doc'])
<div class="card flex flex-col items-center px-6 py-10 text-center">
    <div class="mb-3 rounded-full bg-brand-50 p-3 text-brand-600"><x-icon :name="$icon" class="size-7" /></div>
    <p class="font-semibold text-slate-800">{{ $title }}</p>
    @if (trim($slot))<div class="mt-2 max-w-md text-sm text-slate-500">{{ $slot }}</div>@endif
    @isset($actions)<div class="mt-4 flex flex-wrap justify-center gap-2">{{ $actions }}</div>@endisset
</div>
