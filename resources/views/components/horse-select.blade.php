@props(['name' => 'horse_id', 'label' => 'Cheval', 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false, 'help' => null])
{{-- Choix du cheval : masqué quand un seul cheval est possible (il est alors choisi d'office, y compris pour x-model). --}}
@php
    $options = collect($options);
    $only = $options->count() === 1 ? (string) $options->keys()->first() : null;
    // Plusieurs chevaux : le cheval actif est proposé d'office (le choix reste visible).
    if ($only === null && blank(old($name, $value)) && auth()->check()) {
        $focusId = app(\App\Services\HorseAccess::class)->focusHorse(auth()->user())?->id;
        if ($focusId && $options->has($focusId)) {
            $value = $focusId;
            // Avec x-model, l'état peut partir vide : on remet le cheval actif au démarrage.
            $attributes = $attributes->merge(['x-init' => "if (! \$el.value) { \$el.value = '{$focusId}'; \$el.dispatchEvent(new Event('change')) }"]);
        }
    }
    if ($only !== null) {
        $attributes = $attributes->merge(['x-init' => "if (\$el.value !== '{$only}') { \$el.value = '{$only}'; \$el.dispatchEvent(new Event('change')) }"]);
    }
@endphp
@if ($only !== null)
    <div hidden data-horse-implicit="{{ $only }}"><x-select :name="$name" :label="$label" :options="$options" :value="$only" :required="$required" {{ $attributes }} /></div>
@else
    <x-select :name="$name" :label="$label" :options="$options" :value="$value" :placeholder="$placeholder" :required="$required" :help="$help" {{ $attributes }} />
@endif
