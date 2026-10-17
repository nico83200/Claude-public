<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Horse;
use App\Models\License;
use App\Models\Organization;
use App\Models\PaymentRecord;
use App\Models\StripeEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Services\LoadSimulation;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $active = Subscription::whereIn('stripe_status', ['active', 'trialing', 'past_due'])->with('plan')->get();
        $monthStart = now()->startOfMonth();
        $sim = LoadSimulation::counted();

        return view('admin.dashboard', [
            'counts' => [
                // Simulations de charge comptées (sauf « ?simulations=0 ») ; les données de démonstration jamais.
                'users' => User::when(! $sim, fn ($q) => $q->whereNull('simulation_batch_id'))->count(),
                'organizations' => Organization::where(fn ($q) => $q->where('is_demo', false)->when($sim, fn ($q) => $q->orWhereIn('id', LoadSimulation::organizationIds())))->count(),
                'stables' => Organization::where('type', 'stable')->where('is_demo', false)->count(),
                'horses' => Horse::where(fn ($q) => $q->where('is_demo', false)->when($sim, fn ($q) => $q->orWhereIn('id', LoadSimulation::horseIds())))->count(),
                'active_subscriptions' => $active->count(),
                'trialing' => $active->where('stripe_status', 'trialing')->count(),
                'manual_licenses' => License::where('source', 'manual')->whereIn('status', License::WRITABLE)->count(),
            ],
            'byPlan' => $active->groupBy(fn ($s) => $s->plan?->name ?? '—')->map->count()->sortDesc(),
            // Estimation : abonnements actifs ramenés au mois (hors essais), ce n'est PAS un encaissement.
            'mrrEstimate' => $active->where('stripe_status', '!=', 'trialing')->sum(fn ($s) => $s->interval === 'year' ? (float) $s->unit_amount / 12 : (float) $s->unit_amount),
            'billedThisMonth' => PaymentRecord::where('created_at', '>=', $monthStart)->whereNotIn('status', ['draft', 'void'])->sum('amount_due'),
            'collectedThisMonth' => PaymentRecord::where('status', 'paid')->where('paid_at', '>=', $monthStart)->sum('amount_paid') - PaymentRecord::where('paid_at', '>=', $monthStart)->sum('amount_refunded'),
            'failedPayments' => PaymentRecord::where('status', 'failed')->where('created_at', '>=', now()->subDays(30))->with('organization')->latest()->get(),
            'cancellations' => Subscription::whereNotNull('canceled_at')->where('canceled_at', '>=', now()->subDays(30))->count(),
            'scheduledCancellations' => Subscription::where('cancel_at_period_end', true)->whereIn('stripe_status', ['active', 'trialing'])->count(),
            'signups' => Subscription::where('created_at', '>=', now()->subMonths(11)->startOfMonth())->get(['created_at'])
                ->groupBy(fn ($s) => $s->created_at->format('Y-m'))->map->count(),
            'failedEvents' => StripeEvent::where('status', 'failed')->count(),
        ]);
    }
}
