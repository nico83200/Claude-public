@props(['name' => 'professional_id', 'label' => 'Intervenant', 'options' => [], 'value' => null, 'placeholder' => '—', 'help' => null, 'org' => null, 'kind' => null, 'required' => false])
{{-- Liste des intervenants + « Nouvel intervenant » (fenêtre de création rapide, import de contact) --}}
@php
    $orgModel = $org instanceof \App\Models\Organization ? $org : (is_numeric($org) ? \App\Models\Organization::find($org) : ($currentOrganization ?? null));
    $canCreate = $orgModel && auth()->user()?->canInOrg($orgModel, \App\Support\Perm::PROFESSIONALS_MANAGE);
    $fieldId = $attributes->get('id', 'f_'.str_replace(['[', ']', '.'], '_', $name));
@endphp
<div {{ $attributes->only('class') }}>
    <x-select :name="$name" :id="$fieldId" :label="$label" :options="$options" :value="$value" :placeholder="$placeholder" :required="$required" :help="$canCreate ? null : $help" />
    @if ($canCreate)
        <button type="button" class="mt-1.5 inline-flex items-center gap-1 text-sm link" data-new-professional
                @click="$store.contactForm.open({ org: {{ $orgModel->id }}, kind: @js($kind), target: '#{{ $fieldId }}' })">
            <x-icon name="plus" class="size-4" /> Nouvel intervenant ou contact
        </button>
    @endif
</div>
