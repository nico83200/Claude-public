@props(['name', 'label', 'checked' => false, 'value' => '1', 'help' => null])
<label class="flex min-h-11 cursor-pointer items-start gap-3 py-1">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($checked) {{ $attributes->merge(['class' => 'mt-1 size-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500']) }}>
    <span><span class="text-sm text-slate-800">{{ $label }}</span>@if ($help)<span class="block text-xs text-slate-500">{{ $help }}</span>@endif</span>
</label>
