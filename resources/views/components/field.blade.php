@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'help' => null, 'required' => false])
@php $id = $attributes->get('id', 'f_'.str_replace(['[', ']', '.'], '_', $name)); $errKey = str_replace(['[', ']'], ['.', ''], $name); @endphp
<div {{ $attributes->only('class') }}>
    @if ($label)<label for="{{ $id }}" class="label">{{ $label }}@if ($required)<span class="text-red-600"> *</span>@endif</label>@endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($errKey, $value) }}" @if ($required) required @endif
        {{ $attributes->except(['class', 'id'])->merge(['class' => 'input'.($errors->has($errKey) ? ' border-red-500' : '')]) }}>
    @if ($help)<p class="mt-1 text-xs text-slate-500">{{ $help }}</p>@endif
    @error($errKey)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
