<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Tests\TestCase;

/** Administration › Utilisateurs : offre en cours (nom, payante ou non, échéance). */
class AdminUserPlansTest extends TestCase
{
    private function stripeLicense(User $user, string $status, array $sub = []): void
    {
        $org = $user->personalOrganization();
        $plan = SubscriptionPlan::where('slug', 'particulier')->first();
        $s = Subscription::create(array_merge([
            'organization_id' => $org->id, 'subscription_plan_id' => $plan->id, 'stripe_subscription_id' => 'sub_'.$user->id,
            'stripe_price_id' => 'price_x', 'interval' => 'month', 'unit_amount' => 6.90, 'currency' => 'EUR', 'stripe_status' => $status,
            'current_period_end' => now()->addMonth(), 'cancel_at_period_end' => false,
        ], $sub));
        License::create(['organization_id' => $org->id, 'subscription_plan_id' => $plan->id, 'subscription_id' => $s->id, 'source' => 'stripe',
            'status' => ($sub['cancel_at_period_end'] ?? false) ? 'cancel_scheduled' : 'active', 'starts_at' => now()->subDay(), 'ends_at' => $s->current_period_end]);
    }

    public function test_users_list_shows_current_plan_kind_and_deadline(): void
    {
        $admin = $this->user(['name' => 'Admin']);
        $admin->forceFill(['is_super_admin' => true])->save();

        $free = $this->user(['name' => 'Gratuit Alice']);
        $paid = $this->user(['name' => 'Payant Bruno']);
        $this->stripeLicense($paid, 'active', ['current_period_end' => now()->setDate(2026, 12, 15)]);
        $trial = $this->user(['name' => 'Essai Chloé']);
        $this->stripeLicense($trial, 'trialing', ['trial_ends_at' => now()->setDate(2026, 10, 25)]);
        $ending = $this->user(['name' => 'Résilié Denis']);
        $this->stripeLicense($ending, 'active', ['cancel_at_period_end' => true, 'current_period_end' => now()->setDate(2026, 11, 2)]);
        $offered = $this->user(['name' => 'Offert Emma']);
        License::create(['organization_id' => $offered->personalOrganization()->id, 'subscription_plan_id' => SubscriptionPlan::where('slug', 'multi-chevaux')->value('id'),
            'source' => 'code', 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->setDate(2027, 1, 31)]);
        $stableOwner = $this->user(['name' => 'Écurie Fanny']);
        $this->stable($stableOwner, 'Écurie des Pins');

        $page = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/utilisateurs')->assertOk()->assertSee('Offre en cours');
        $html = $page->getContent();
        $row = fn (string $name) => substr($html, strpos($html, $name), 1200);

        $this->assertStringContainsString('Découverte', $row('Gratuit Alice'));
        $this->assertStringContainsString('Gratuite', $row('Gratuit Alice'));
        $this->assertStringContainsString('Sans échéance', $row('Gratuit Alice'));

        $this->assertStringContainsString('Propriétaire', $row('Payant Bruno'));
        $this->assertStringContainsString('Payante', $row('Payant Bruno'));
        $this->assertStringContainsString('6,90 € / mois', $row('Payant Bruno'));
        $this->assertStringContainsString('Renouvellement le 15/12/2026', $row('Payant Bruno'));

        $this->assertStringContainsString('Essai gratuit', $row('Essai Chloé'));
        $this->assertStringContainsString('Fin d&#039;essai le 25/10/2026', $row('Essai Chloé'));

        $this->assertStringContainsString('Se termine le 02/11/2026', $row('Résilié Denis'));

        $this->assertStringContainsString('Propriétaire multi-chevaux', $row('Offert Emma'));
        $this->assertStringContainsString('Offerte', $row('Offert Emma'));
        $this->assertStringContainsString('Jusqu&#039;au 31/01/2027', $row('Offert Emma'));

        // Propriétaire de plusieurs espaces : une ligne par espace.
        $this->assertStringContainsString('Écurie des Pins', $row('Écurie Fanny'));
        $this->assertStringContainsString('Écurie', $row('Écurie Fanny'));

        $this->actingAs($admin)->get("/admin/utilisateurs/{$paid->id}")->assertOk()->assertSee('Offre en cours')->assertSee('Renouvellement le 15/12/2026');
    }
}
