<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Services\OrganizationService;
use Tests\TestCase;

/** Un compte = son espace personnel, et au plus une écurie (offre Écurie). */
class SingleStableTest extends TestCase
{
    public function test_an_account_can_create_only_one_stable(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/organisation')->assertOk()->assertSee('data-create-stable', false);
        $this->actingAs($user)->get('/espaces/nouveau')->assertOk()->assertSee("Un compte ne peut gérer qu'une seule écurie", false);
        $this->actingAs($user)->post('/espaces', ['name' => 'Écurie des Pins'])->assertRedirect('/organisation');
        $stable = Organization::where('owner_id', $user->id)->where('type', 'stable')->sole();

        $user = $user->fresh();
        $this->actingAs($user)->get('/organisation')->assertOk()->assertDontSee('data-create-stable', false);
        $this->actingAs($user)->get('/espaces/nouveau')->assertRedirect('/organisation')->assertSessionHas('status');
        $this->actingAs($user)->post('/espaces', ['name' => 'Deuxième écurie'])->assertSessionHasErrors('name');
        $this->assertSame(1, Organization::where('owner_id', $user->id)->where('type', 'stable')->count());
        $this->assertSame(1, Organization::where('owner_id', $user->id)->where('type', 'personal')->count(), 'L\'espace personnel (cavalier) est conservé.');

        // Être invité dans l'écurie d'un tiers ne compte pas : on peut toujours créer la sienne.
        $other = $this->user();
        app(OrganizationService::class)->addMember($stable, $other, 'rider');
        $this->actingAs($other->fresh())->post('/espaces', ['name' => 'Écurie de Léa'])->assertRedirect('/organisation');
        $this->assertSame(1, Organization::where('owner_id', $other->id)->where('type', 'stable')->count());
    }
}
