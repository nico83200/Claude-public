@props(['value' => null])
{{-- Enseignant d'un cours : texte libre, suggestions tirées des intervenants « Enseignant », création rapide. --}}
@php
    $user = auth()->user();
    $instructors = \App\Models\Professional::whereIn('organization_id', $user->memberships->pluck('organization_id'))->where('kind', 'instructor')->whereNull('archived_at')
        ->orderBy('first_name')->get()->map->fullName()->unique()->values();
    $org = $currentOrganization ?? null;
    $canCreate = $org && $user->canInOrg($org, \App\Support\Perm::PROFESSIONALS_MANAGE);
@endphp
<div {{ $attributes->only('class') }}>
    <x-field name="instructor" id="f_instructor" label="Enseignant" :value="$value" placeholder="Prénom ou nom" maxlength="100" list="instructors-list" autocomplete="off" />
    <datalist id="instructors-list">@foreach ($instructors as $name)<option value="{{ $name }}"></option>@endforeach</datalist>
    @if ($canCreate)
        <button type="button" class="mt-1.5 inline-flex items-center gap-1 text-sm link" data-new-professional
                @click="$store.contactForm.open({ org: {{ $org->id }}, kind: 'instructor', target: '#f_instructor', mode: 'text' })">
            <x-icon name="plus" class="size-4" /> Nouvel enseignant
        </button>
    @endif
</div>
