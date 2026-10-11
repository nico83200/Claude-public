<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Services\OrganizationService;
use App\Services\ProfessionalDirectory;
use Tests\TestCase;

/** Intervenants créés depuis les formulaires et fiches contact importées (création ou mise à jour). */
class ProfessionalQuickCreateTest extends TestCase
{
    public function test_forms_offer_quick_creation_and_contact_import(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);

        $this->actingAs($user)->get("/chevaux/{$horse->id}/soins/nouveau")->assertOk()
            ->assertSee('Nouvel intervenant ou contact')->assertSee('id="contact-dialog"', false)->assertSee('Importer une fiche (.vcf)');
        $this->actingAs($user)->get('/budget')->assertOk()->assertSee('Nouvel intervenant ou contact');
        $this->actingAs($user)->get('/calendrier')->assertOk()->assertSee('Nouvel intervenant ou contact');
        $this->actingAs($user)->get("/chevaux/{$horse->id}/intervenants")->assertOk()->assertSee('Nouvel intervenant ou contact');
        $this->actingAs($user)->get('/seances/nouvelle')->assertOk()->assertSee('Nouvel enseignant')->assertSee('list="instructors-list"', false);
        $this->actingAs($user)->get('/intervenants/nouveau')->assertOk()->assertSee('Choisir un contact')->assertSee('Importer une fiche (.vcf)');
    }

    public function test_quick_create_then_update_existing_card_instead_of_duplicating(): void
    {
        $user = $this->user();
        $org = $user->personalOrganization();

        $this->actingAs($user)->postJson('/intervenants/rapide', ['organization_id' => $org->id, 'kind' => 'veterinarian', 'first_name' => 'Julie', 'last_name' => 'Bernard', 'phone' => '06 12 34 56 78'])
            ->assertCreated()->assertJsonPath('created', true)->assertJsonPath('label', 'Julie Bernard – Vétérinaire');

        // Même téléphone sous un autre format (fiche importée du téléphone) : mise à jour, sans effacer ce qui manque.
        $this->actingAs($user)->postJson('/intervenants/rapide', ['organization_id' => $org->id, 'kind' => 'veterinarian', 'first_name' => 'Julie', 'last_name' => 'Bernard',
            'phone' => '+33 6 12 34 56 78', 'email' => 'julie@clinique-equine.fr', 'company' => 'Clinique équine du Var'])
            ->assertOk()->assertJsonPath('created', false);
        $this->assertSame(1, Professional::count());
        $p = Professional::first();
        $this->assertSame('julie@clinique-equine.fr', $p->email);
        $this->assertSame('Clinique équine du Var', $p->company);
        $this->assertSame('+33 6 12 34 56 78', $p->phone);

        // Même email : mise à jour aussi ; une fiche archivée revient dans les listes.
        $p->update(['archived_at' => now()]);
        $this->actingAs($user)->postJson('/intervenants/rapide', ['kind' => 'veterinarian', 'last_name' => 'Bernard', 'email' => 'JULIE@clinique-equine.fr'])->assertOk();
        $this->assertNull($p->fresh()->archived_at);

        // Autre personne : nouvelle fiche ; champ obligatoire manquant : erreur lisible.
        $this->actingAs($user)->postJson('/intervenants/rapide', ['kind' => 'farrier', 'last_name' => 'Petit', 'phone' => '0699999999'])->assertCreated();
        $this->actingAs($user)->postJson('/intervenants/rapide', ['kind' => 'farrier'])->assertStatus(422)->assertJsonValidationErrors('last_name');
        $this->assertSame(2, Professional::count());
    }

    public function test_quick_create_requires_the_right_to_manage_professionals_in_that_space(): void
    {
        $owner = $this->user();
        $stable = $this->stable($owner);
        $rider = $this->user();
        app(OrganizationService::class)->addMember($stable, $rider, 'rider');

        $this->actingAs($rider)->postJson('/intervenants/rapide', ['organization_id' => $stable->id, 'kind' => 'farrier', 'last_name' => 'Petit'])->assertForbidden();
        $this->actingAs($this->user())->postJson('/intervenants/rapide', ['organization_id' => $stable->id, 'kind' => 'farrier', 'last_name' => 'Petit'])->assertForbidden();
        $this->actingAs($owner)->postJson('/intervenants/rapide', ['organization_id' => $stable->id, 'kind' => 'farrier', 'last_name' => 'Petit'])->assertCreated();
        $this->assertSame($stable->id, Professional::first()->organization_id);
    }

    public function test_full_form_detects_imported_duplicate_and_can_update_it(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/intervenants', ['last_name' => 'Roux', 'first_name' => 'Marc', 'kind' => 'farrier', 'phone' => '0611223344'])->assertRedirect();
        $existing = Professional::first();

        $this->actingAs($user)->from('/intervenants/nouveau')->post('/intervenants', ['last_name' => 'Roux', 'first_name' => 'Marc', 'kind' => 'farrier', 'phone' => '+33611223344', 'email' => 'marc@roux.fr'])
            ->assertSessionHas('warning')->assertSessionHas('duplicate_id', $existing->id);
        $this->actingAs($user)->withSession(['duplicate_id' => $existing->id])->get('/intervenants/nouveau')->assertSee('Mettre à jour la fiche existante');

        $this->actingAs($user)->post('/intervenants', ['last_name' => 'Roux', 'first_name' => 'Marc', 'kind' => 'farrier', 'phone' => '+33611223344', 'email' => 'marc@roux.fr', 'merge' => $existing->id])
            ->assertRedirect("/intervenants/{$existing->id}");
        $this->assertSame(1, Professional::count());
        $this->assertSame('marc@roux.fr', $existing->fresh()->email);

        // Fusion vers une fiche d'un autre espace : refusée.
        $other = $this->user();
        $this->actingAs($other)->post('/intervenants', ['last_name' => 'X', 'kind' => 'other', 'merge' => $existing->id])->assertNotFound();
    }

    public function test_phone_normalization(): void
    {
        foreach (['06 12 34 56 78', '+33 6 12 34 56 78', '0033612345678', '06.12.34.56.78'] as $p) {
            $this->assertSame('0612345678', ProfessionalDirectory::normalizePhone($p));
        }
        $this->assertNull(ProfessionalDirectory::normalizePhone('12'));
    }
}
