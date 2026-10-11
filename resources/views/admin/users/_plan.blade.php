{{-- Offre en cours d'un espace (App\Services\PlanSummary) --}}
<div class="py-0.5" data-plan-kind="{{ $s['kind'] }}">
    <span class="font-medium text-slate-900">{{ $s['plan'] ?? 'Aucune offre' }}</span>
    <x-badge :color="$s['color']">{{ $s['kind_label'] }}</x-badge>
    @if ($showOrg ?? false)<span class="text-xs text-slate-500">· {{ $s['organization']->name }}</span>@endif
    <span class="block text-xs text-slate-500">
        @if ($s['price']){{ $s['price'] }} · @endif{{ $s['deadline_label'] }}@if ($s['deadline']) {{ $s['deadline']->format('d/m/Y') }}@endif
    </span>
</div>
