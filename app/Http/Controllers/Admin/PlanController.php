<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanPriceChange;
use App\Models\SubscriptionPlan;
use App\Services\Audit;
use App\Services\Billing\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class PlanController extends Controller
{
    public function __construct(private StripeGateway $stripe) {}

    public function index()
    {
        return view('admin.plans.index', [
            'plans' => SubscriptionPlan::withCount(['subscriptions as active_subscriptions_count' => fn ($q) => $q->whereIn('stripe_status', ['active', 'trialing', 'past_due'])])->orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.plans.form', ['plan' => new SubscriptionPlan(['currency' => 'EUR', 'is_active' => true, 'is_public' => true, 'audience' => 'any', 'features' => []]), 'stripeReady' => $this->stripe->configured()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(4));
        $plan = SubscriptionPlan::create($data);
        if ($request->boolean('create_stripe_prices')) {
            $this->createStripePrices($plan, true, true);
        }
        Audit::log('admin.plan_created', $plan, $data);

        return redirect()->route('admin.plans.index')->with('success', 'Offre créée.');
    }

    public function edit(SubscriptionPlan $plan)
    {
        return view('admin.plans.form', [
            'plan' => $plan, 'stripeReady' => $this->stripe->configured(),
            'subscribers' => $plan->subscriptions()->whereIn('stripe_status', ['active', 'trialing', 'past_due'])->count(),
            'priceChanges' => $plan->priceChanges()->with('author')->latest()->get(),
        ]);
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        $data = $this->validated($request, $plan);
        $monthlyChanged = (string) ($data['price_monthly'] ?? '') !== (string) ($plan->price_monthly ?? '');
        $yearlyChanged = (string) ($data['price_yearly'] ?? '') !== (string) ($plan->price_yearly ?? '');

        DB::transaction(function () use ($request, $plan, $data, $monthlyChanged, $yearlyChanged) {
            if ($monthlyChanged || $yearlyChanged) {
                $strategy = $request->validate([
                    'existing_subscribers' => ['required', Rule::in(['keep_old_price', 'migrate_after_notice'])],
                    'migration_effective_on' => ['nullable', 'required_if:existing_subscribers,migrate_after_notice', 'date', 'after_or_equal:'.now()->addDays(30)->toDateString()],
                ], ['migration_effective_on.after_or_equal' => 'Un préavis d\'au moins 30 jours est requis avant d\'appliquer un nouveau tarif aux abonnés existants.']);
                // Les abonnements existants conservent leur prix Stripe actuel tant qu'aucune migration explicite n'est appliquée.
                PlanPriceChange::create([
                    'subscription_plan_id' => $plan->id, 'changed_by' => $request->user()->id,
                    'old_price_monthly' => $plan->price_monthly, 'new_price_monthly' => $data['price_monthly'] ?? null,
                    'old_price_yearly' => $plan->price_yearly, 'new_price_yearly' => $data['price_yearly'] ?? null,
                    'old_stripe_price_monthly_id' => $plan->stripe_price_monthly_id, 'old_stripe_price_yearly_id' => $plan->stripe_price_yearly_id,
                    'existing_subscribers' => $strategy['existing_subscribers'], 'migration_effective_on' => $strategy['migration_effective_on'] ?? null,
                ]);
                // Les prix Stripe étant immuables, un nouveau prix est nécessaire pour les nouvelles souscriptions.
                if ($monthlyChanged) {
                    $data['stripe_price_monthly_id'] = null;
                }
                if ($yearlyChanged) {
                    $data['stripe_price_yearly_id'] = null;
                }
            }
            $plan->update($data);
        });

        if (($monthlyChanged || $yearlyChanged || $request->boolean('create_stripe_prices')) && $this->stripe->configured()) {
            $this->createStripePrices($plan->fresh(), $monthlyChanged || ! $plan->stripe_price_monthly_id, $yearlyChanged || ! $plan->stripe_price_yearly_id);
        }
        Audit::log('admin.plan_updated', $plan, $data);

        return redirect()->route('admin.plans.edit', $plan)->with('success', 'Offre mise à jour.'.($monthlyChanged || $yearlyChanged ? ' Les abonnés existants conservent leur tarif jusqu\'à une migration explicite.' : ''));
    }

    public function archive(SubscriptionPlan $plan)
    {
        // Jamais de suppression physique d'une offre utilisée : archivage (plus proposée, abonnements existants inchangés).
        $plan->update(['archived_at' => $plan->archived_at ? null : now(), 'is_active' => (bool) $plan->archived_at]);
        Audit::log('admin.plan_archived', $plan, ['archived' => (bool) $plan->archived_at]);

        return back()->with('success', $plan->archived_at ? 'Offre archivée : elle n\'est plus proposée, les abonnements en cours continuent.' : 'Offre réactivée.');
    }

    private function createStripePrices(SubscriptionPlan $plan, bool $monthly, bool $yearly): void
    {
        if (! $this->stripe->configured()) {
            session()->flash('warning', 'Stripe n\'est pas configuré : renseignez les identifiants de prix manuellement.');

            return;
        }
        try {
            $product = $plan->stripe_product_id ?: $this->stripe->createProduct($plan->name, ['plan_id' => (string) $plan->id]);
            $updates = ['stripe_product_id' => $product];
            if ($monthly && $plan->price_monthly) {
                $updates['stripe_price_monthly_id'] = $this->stripe->createPrice($product, (int) round($plan->price_monthly * 100), $plan->currency, 'month', ['plan_id' => (string) $plan->id]);
            }
            if ($yearly && $plan->price_yearly) {
                $updates['stripe_price_yearly_id'] = $this->stripe->createPrice($product, (int) round($plan->price_yearly * 100), $plan->currency, 'year', ['plan_id' => (string) $plan->id]);
            }
            $plan->update($updates);
        } catch (Throwable $e) {
            report($e);
            session()->flash('warning', 'Création des prix Stripe impossible : '.$e->getMessage());
        }
    }

    private function validated(Request $request, ?SubscriptionPlan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_monthly' => ['nullable', 'numeric', 'min:0', 'max:99999', 'decimal:0,2'],
            'price_yearly' => ['nullable', 'numeric', 'min:0', 'max:999999', 'decimal:0,2'],
            'currency' => ['required', 'string', 'size:3'],
            'audience' => ['required', Rule::in(['individual', 'stable', 'any'])],
            'stripe_price_monthly_id' => ['nullable', 'string', 'max:100', 'starts_with:price_'],
            'stripe_price_yearly_id' => ['nullable', 'string', 'max:100', 'starts_with:price_'],
            'max_horses' => ['nullable', 'integer', 'min:0'],
            'max_members' => ['nullable', 'integer', 'min:0'],
            'max_invitations' => ['nullable', 'integer', 'min:0'],
            'storage_mb' => ['nullable', 'integer', 'min:0'],
            'history_months' => ['nullable', 'integer', 'min:0'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'renewal_terms' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'features' => ['array'],
            'features.*' => [Rule::in(array_keys(SubscriptionPlan::FEATURES))],
        ]);
        $data['currency'] = strtoupper($data['currency']);
        $data['features'] = array_values($data['features'] ?? []);
        $data['trial_days'] = $data['trial_days'] ?? 0;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');
        $data['is_public'] = $request->boolean('is_public');
        $data['is_default_free'] = $request->boolean('is_default_free');
        if ($data['is_default_free']) {
            SubscriptionPlan::where('is_default_free', true)->when($plan, fn ($q) => $q->where('id', '!=', $plan->id))->update(['is_default_free' => false]);
        }

        return $data;
    }
}
