<?php

namespace Tests\Feature;

use App\Models\HalfLease;
use App\Models\HorseDocument;
use App\Models\User;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Documents de la demi-pension : contrat, assurance, licence, visibles des deux parties seulement, avec rappels de validité. */
class Version248RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fresh(): void
    {
        app()->forgetInstance(HorseAccess::class);
    }

    private function setUpLease(string $plan = 'particulier'): array
    {
        Carbon::setTestNow('2026-10-17 18:00');
        Storage::fake('local');
        $owner = $this->user(['name' => 'Paul MARTIN']);
        $this->grantLicense($owner->personalOrganization(), $plan);
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);
        $this->actingAs($owner)->post(route('horses.half-leases.store', $horse), [
            'name' => 'Léa DURAND', 'email' => 'lea@exemple.fr', 'starts_on' => '2026-10-01', 'weekdays' => [2], 'monthly_amount' => '0', 'permissions' => Perm::halfLeasePreset(),
        ])->assertSessionHasNoErrors();
        $lease = HalfLease::sole();
        $this->actingAs($lea)->post(route('invitations.respond', $lease->invitation_id), ['decision' => 'accept']);
        $this->fresh();

        return [$owner, $horse, $lea, $lease->fresh()];
    }

    private function upload(User $by, HalfLease $lease, string $category, ?string $validUntil = null, string $name = 'doc.pdf')
    {
        return $this->actingAs($by)->post(route('half-leases.documents.store', $lease), array_filter([
            'file' => UploadedFile::fake()->create($name, 120, 'application/pdf'), 'category' => $category, 'valid_until' => $validUntil,
        ]));
    }

    private function titles(User $user): array
    {
        return $user->notifications()->get()->pluck('data.title')->all();
    }

    public function test_both_sides_add_documents_seen_by_them_only(): void
    {
        [$owner, $horse, $lea, $lease] = $this->setUpLease();
        $this->upload($owner, $lease, 'contract', null, 'contrat-signe.pdf')->assertSessionHas('success');
        $this->upload($lea, $lease, 'insurance', '2026-12-31', 'attestation-rc.pdf')->assertSessionHas('success');
        [$contract, $insurance] = [HorseDocument::where('category', 'contract')->sole(), HorseDocument::where('category', 'insurance')->sole()];
        $this->assertSame([HalfLease::class, $lease->id, '2026-12-31'], [$insurance->attachable_type, $insurance->attachable_id, $insurance->valid_until->toDateString()]);
        $this->assertContains('Document de la demi-pension : Contrat signé', $this->titles($lea));
        $this->assertContains('Document de la demi-pension : Attestation d\'assurance', $this->titles($owner));

        // Fiche : liste et points à fournir.
        $this->actingAs($lea)->get(route('half-leases.show', $lease))->assertOk()->assertSee('data-half-lease-documents', false)
            ->assertSee('Contrat signé · Léa DURAND')->assertSee('jusqu\'au 31/12/2026')->assertSee('à déposer');
        $this->actingAs($lea)->get(route('half-leases.documents.show', [$lease, $contract]))->assertOk();
        $this->actingAs($owner)->get(route('half-leases.documents.show', [$lease, $insurance]))->assertOk();

        // Ni la liste des documents du cheval, ni un membre qui voit les documents, ni un tiers.
        $assistant = $this->memberWith($horse, [Perm::HORSE_VIEW, Perm::DOCUMENTS_VIEW], 'Assistante');
        $this->fresh();
        $this->actingAs($owner)->get(route('horses.documents.index', $horse))->assertOk()->assertDontSee('Attestation d\'assurance · Léa DURAND');
        $this->actingAs($assistant)->get(route('horses.documents.download', [$horse, $insurance]))->assertNotFound();
        $this->actingAs($assistant)->get(route('half-leases.documents.show', [$lease, $insurance]))->assertNotFound();
        $this->actingAs($this->user())->get(route('half-leases.documents.show', [$lease, $insurance]))->assertNotFound();

        // Chacun retire les siens ; le propriétaire peut tout retirer.
        $this->actingAs($lea)->delete(route('half-leases.documents.destroy', [$lease, $contract]))->assertForbidden();
        $this->actingAs($lea)->delete(route('half-leases.documents.destroy', [$lease, $insurance]))->assertSessionHas('success');
        $this->assertSoftDeleted($insurance);
        $this->actingAs($owner)->delete(route('half-leases.documents.destroy', [$lease, $contract]))->assertSessionHas('success');
    }

    public function test_validity_reminders_before_and_after_expiry(): void
    {
        [$owner, , $lea, $lease] = $this->setUpLease();
        $this->upload($lea, $lease, 'license', '2026-11-10');
        $this->artisan('reminders:send')->assertSuccessful();
        $this->artisan('reminders:send');
        $this->assertSame(1, collect($this->titles($lea))->filter(fn ($t) => $t === 'Licence à renouveler : Léa DURAND')->count(), 'Une fois, dans les 30 jours.');
        $this->assertContains('Licence à renouveler : Léa DURAND', $this->titles($owner));

        Carbon::setTestNow('2026-11-11 07:00');
        $this->artisan('reminders:send');
        $this->assertContains('Licence expirée : Léa DURAND', $this->titles($lea));
        $this->fresh();
        $this->actingAs($owner)->get(route('half-leases.show', $lease))->assertSee('Expirée');
    }

    public function test_upload_follows_the_owner_plan(): void
    {
        [, , $lea, $lease] = $this->setUpLease('decouverte');
        $this->upload($lea, $lease, 'insurance')->assertSessionHas('warning');
        $this->assertSame(0, HorseDocument::count());
        $this->actingAs($lea)->get(route('half-leases.show', $lease))->assertSee('Le dépôt de documents n\'est pas inclus dans l\'offre');
    }
}
