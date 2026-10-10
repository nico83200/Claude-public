<section class="mx-auto max-w-6xl px-4 py-12">
    @if ($plans->isEmpty())
        <p class="text-center text-slate-500">Les offres seront bientôt disponibles.</p>
    @else
    @php $cols = [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3'][$plans->count()] ?? 'lg:grid-cols-4'; @endphp
    <div class="grid gap-4 sm:grid-cols-2 {{ $cols }}">
        @foreach ($plans as $plan)
            <div class="card flex flex-col p-6">
                <h3 class="text-lg font-bold">{{ $plan->name }}</h3>
                <p class="mt-1 min-h-10 text-sm text-slate-600">{{ $plan->description }}</p>
                <p class="mt-4">
                    @if ($plan->is_default_free || ! $plan->price_monthly)
                        <span class="text-3xl font-extrabold">Gratuit</span>
                    @else
                        <span class="text-3xl font-extrabold">{{ number_format((float) $plan->price_monthly, 2, ',', ' ') }} €</span><span class="text-sm text-slate-500"> TTC / mois</span>
                        @if ($plan->price_yearly)<span class="block text-xs text-slate-500">ou {{ number_format((float) $plan->price_yearly, 2, ',', ' ') }} € / an</span>@endif
                    @endif
                </p>
                <ul class="mt-4 flex-1 space-y-1.5 text-sm text-slate-700">
                    <li>✓ {{ $plan->max_horses === null ? 'Chevaux illimités' : $plan->max_horses.' cheval'.($plan->max_horses > 1 ? 'x' : '') }}</li>
                    @if ($plan->max_members)<li>✓ {{ $plan->max_members }} membre(s)</li>@endif
                    @foreach ($plan->features ?? [] as $f)<li>✓ {{ \App\Models\SubscriptionPlan::FEATURES[$f] ?? $f }}</li>@endforeach
                    @if ($plan->trial_days)<li class="text-brand-700">{{ $plan->trial_days }} jours d'essai</li>@endif
                </ul>
                <a href="{{ auth()->check() ? route('billing.index') : route('register') }}" class="btn-primary mt-6">{{ $plan->is_default_free ? 'Commencer' : 'Choisir' }}</a>
            </div>
        @endforeach
    </div>
    @endif
</section>
