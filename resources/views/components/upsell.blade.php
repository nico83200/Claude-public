@props(['offer', 'compact' => false])
{{-- Proposition d'offre supérieure : fonction non incluse ou limite atteinte (voir App\Services\Upsell). --}}
@if ($offer)
    <div {{ $attributes->merge(['class' => 'upsell relative overflow-hidden rounded-3xl bg-gradient-to-br from-gold-50 to-white ring-1 ring-gold-200 '.($compact ? 'p-4' : 'p-5 sm:p-6')]) }} data-upsell="{{ $offer['key'] }}">
        <div class="flex items-start gap-4">
            <span class="flex {{ $compact ? 'size-10' : 'size-12' }} shrink-0 items-center justify-center rounded-full bg-brand-900 text-gold-200"><x-icon name="sparkles" class="{{ $compact ? 'size-5' : 'size-6' }}" /></span>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold tracking-wide text-gold-600 uppercase">Offre {{ $offer['plan']->name }}</p>
                <p class="mt-0.5 {{ $compact ? 'text-[15px]' : 'text-[17px]' }} font-semibold text-slate-900">{{ $offer['title'] }}</p>
                @if ($offer['text'] && ! $compact)<p class="mt-1 text-sm text-slate-600">{{ $offer['text'] }}</p>@endif
                @if ($offer['can_upgrade'])
                    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2">
                        <a href="{{ $offer['url'] }}" class="btn-primary min-h-10 px-4">{{ $offer['cta'] }}</a>
                        <span class="text-xs text-slate-500">{{ $offer['trial_days'] ? 'Sans engagement, résiliable à tout moment.' : $offer['note'] }}</span>
                    </div>
                @else
                    <p class="mt-2 text-xs text-slate-500">{{ $offer['note'] }}</p>
                @endif
            </div>
        </div>
    </div>
@endif
