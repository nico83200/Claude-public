@props(['name', 'label' => null, 'value' => null, 'rows' => 3, 'help' => null, 'required' => false])
@php $id = 'f_'.str_replace(['[', ']', '.'], '_', $name); $errKey = str_replace(['[', ']'], ['.', ''], $name); @endphp
<div {{ $attributes->only('class') }}>
    @if ($label)<label for="{{ $id }}" class="label">{{ $label }}@if ($required)<span class="text-red-600"> *</span>@endif</label>@endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if ($required) required @endif {{ $attributes->except('class')->merge(['class' => 'input']) }}>{{ old($errKey, $value) }}</textarea>
    @if ($help)<p class="mt-1 text-xs text-slate-500">{{ $help }}</p>@endif
    @error($errKey)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
