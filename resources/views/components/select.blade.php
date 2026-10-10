@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false, 'help' => null])
@php $id = 'f_'.str_replace(['[', ']', '.'], '_', $name); $errKey = str_replace(['[', ']'], ['.', ''], $name); $current = old($errKey, $value); @endphp
<div {{ $attributes->only('class') }}>
    @if ($label)<label for="{{ $id }}" class="label">{{ $label }}@if ($required)<span class="text-red-600"> *</span>@endif</label>@endif
    <select id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif {{ $attributes->except('class')->merge(['class' => 'input']) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) $current === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @if ($help)<p class="mt-1 text-xs text-slate-500">{{ $help }}</p>@endif
    @error($errKey)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
