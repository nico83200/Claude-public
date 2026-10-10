<?php

namespace Tests\Feature;

use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\HorseBreed;
use App\Models\HorseDocument;
use App\Models\HorseExternalSource;
use App\Models\SubscriptionPlan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HorseTest extends TestCase
{
    public function test_owner_creates_and_updates_a_horse(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/chevaux/nouveau')->assertOk();
        $this->actingAs($user)->post('/chevaux', [
            'official_name' => 'JAPPELOUP DE LUZE', 'usual_name' => 'Jappe', 'sex' => 'gelding', 'birth_year' => 2015,
            'horse_breed_id' => HorseBreed::where('name', 'Selle Français')->value('id'),
            'identifiers' => ['sire' => '12345678a', 'ueln' => '250001234567890'], 'i_am_owner' => '1',
        ])->assertRedirect();

        $horse = Horse::where('official_name', 'JAPPELOUP DE LUZE')->firstOrFail();
        $this->assertSame('12345678A', $horse->identifier('sire'));
        $this->assertDatabaseHas('horse_ownerships', ['horse_id' => $horse->id, 'user_id' => $user->id, 'role' => 'owner']);
        $this->actingAs($user)->get("/chevaux/{$horse->id}")->assertOk()->assertSee('Jappe');

        $this->actingAs($user)->put("/chevaux/{$horse->id}", ['official_name' => 'JAPPELOUP DE LUZE', 'sex' => 'gelding', 'coat' => 'Bai', 'identifiers' => ['sire' => '']])->assertRedirect();
        $this->assertSame('Bai', $horse->fresh()->coat);
        $this->assertNull($horse->fresh()->identifier('sire'));
        $this->assertSame(2, $horse->fresh()->version);
    }

    public function test_identifier_formats_are_validated(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/chevaux', ['official_name' => 'X', 'sex' => 'male', 'identifiers' => ['ueln' => 'trop-court']])->assertSessionHasErrors('identifiers.ueln');
    }

    public function test_plan_horse_limit_is_enforced_and_archiving_frees_a_slot(): void
    {
        $user = $this->user(); // offre gratuite : 1 cheval
        $first = $this->horseFor($user);
        $this->actingAs($user)->post('/chevaux', ['official_name' => 'SECOND', 'sex' => 'male'])->assertSessionHasErrors('official_name');
        $this->assertDatabaseMissing('horses', ['official_name' => 'SECOND']);

        $this->actingAs($user)->post("/chevaux/{$first->id}/archiver")->assertRedirect();
        $this->actingAs($user)->post('/chevaux', ['official_name' => 'SECOND', 'sex' => 'male'])->assertRedirect();
        $this->assertDatabaseHas('horses', ['official_name' => 'SECOND']);
    }

    public function test_archived_horse_is_read_only(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $horse->update(['archived_at' => now()]);
        $this->actingAs($user)->get("/chevaux/{$horse->id}")->assertOk();
        $this->actingAs($user)->put("/chevaux/{$horse->id}", ['official_name' => 'Y', 'sex' => 'male'])->assertForbidden();
    }

    public function test_expired_license_makes_space_read_only_but_keeps_data_and_exports(): void
    {
        $user = $this->user();
        $org = $user->personalOrganization();
        $horse = $this->horseFor($user);
        SubscriptionPlan::where('is_default_free', true)->update(['is_default_free' => false]);
        $this->grantLicense($org, 'particulier', 'expired');

        $this->actingAs($user)->get("/chevaux/{$horse->id}")->assertOk()->assertSee('Lecture seule');
        $this->actingAs($user)->put("/chevaux/{$horse->id}", ['official_name' => 'Z', 'sex' => 'male'])->assertForbidden();
        $this->actingAs($user)->get("/chevaux/{$horse->id}/export/fiche.pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('horses', ['id' => $horse->id]);
    }

    public function test_documents_are_private_and_permission_checked(): void
    {
        Storage::fake('local');
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner);
        $this->actingAs($owner)->post("/chevaux/{$horse->id}/documents", ['file' => UploadedFile::fake()->create('ordonnance.pdf', 100, 'application/pdf'), 'category' => 'prescription', 'is_sensitive' => '1'])->assertRedirect();
        $doc = HorseDocument::firstOrFail();
        Storage::disk('local')->assertExists($doc->path);
        $this->assertStringNotContainsString('public', $doc->path);

        $this->actingAs($owner)->get("/chevaux/{$horse->id}/documents/{$doc->id}")->assertOk();
        $stranger = $this->user();
        $this->actingAs($stranger)->get("/chevaux/{$horse->id}/documents/{$doc->id}")->assertForbidden();
        // Accès partagé sans droit santé : document sensible refusé.
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $stranger->id, 'permissions' => ['horse.view', 'documents.view']]);
        $this->actingAs($stranger)->get("/chevaux/{$horse->id}/documents/{$doc->id}")->assertForbidden();
    }

    public function test_photo_upload_is_resized_and_served_through_controller(): void
    {
        Storage::fake('local');
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $this->actingAs($owner)->post("/chevaux/{$horse->id}/photos", ['photo' => UploadedFile::fake()->image('cheval.jpg', 3000, 2000)])->assertRedirect();
        $photo = $horse->photos()->firstOrFail();
        [$w] = getimagesizefromstring(Storage::disk('local')->get($photo->path));
        $this->assertLessThanOrEqual(1600, $w);
        $this->assertSame((string) $photo->id, $horse->fresh()->main_photo_path);
        $this->actingAs($owner)->get("/chevaux/{$horse->id}/photos/{$photo->id}?thumb=1")->assertOk();
        $this->actingAs($this->user())->get("/chevaux/{$horse->id}/photos/{$photo->id}")->assertForbidden();
    }

    public function test_identity_search_import_requires_explicit_choice_and_records_provenance(): void
    {
        Http::fake([
            'www.wikidata.org/*' => Http::sequence()
                ->push(['search' => [['id' => 'Q1'], ['id' => 'Q2']]])
                ->push(['entities' => [
                    'Q1' => ['labels' => ['fr' => ['value' => 'Jappeloup']], 'claims' => ['P31' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q726']]]]], 'P21' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q44148']]]]], 'P569' => [['mainsnak' => ['datavalue' => ['value' => ['time' => '+1975-04-05T00:00:00Z', 'precision' => 11]]]]], 'P22' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q10']]]]]]],
                    'Q2' => ['labels' => ['fr' => ['value' => 'Jappeloup (film)']], 'claims' => ['P31' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q11424']]]]]]],
                ]])
                ->push(['entities' => ['Q10' => ['labels' => ['fr' => ['value' => 'Tyrol II']]], 'Q44148' => ['labels' => ['fr' => ['value' => 'mâle']]]]]),
        ]);
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');

        $response = $this->actingAs($user)->post('/chevaux/rechercher', ['name' => 'Jappeloup'])->assertOk()->assertSee('Jappeloup')->assertSee('Source de référence')->assertDontSee('(film)');
        $token = $response->viewData('token');
        $candidate = $response->viewData('candidates')[0];
        $this->assertSame('Tyrol II', $candidate->fields['sire_name']);
        $this->assertSame(0, Horse::count()); // rien d'importé sans validation

        $this->actingAs($user)->post('/chevaux/rechercher/apercu', ['token' => $token, 'candidate' => $candidate->id])->assertOk()->assertSee('Confirmer');
        $this->actingAs($user)->post('/chevaux/rechercher/importer', ['token' => $token, 'candidate' => $candidate->id, 'fields' => ['official_name', 'birth_date', 'sire_name']])->assertRedirect();

        $horse = Horse::firstOrFail();
        $this->assertSame('Jappeloup', $horse->official_name);
        $this->assertSame('1975-04-05', $horse->birth_date->toDateString());
        $this->assertSame('unknown', $horse->sex); // non sélectionné → non importé
        $this->assertDatabaseHas('horse_relationships', ['horse_id' => $horse->id, 'relation' => 'sire', 'related_name' => 'Tyrol II']);
        $this->assertSame(3, HorseExternalSource::where('horse_id', $horse->id)->count());
        $this->assertDatabaseHas('horse_external_sources', ['field' => 'birth_date', 'source' => 'Wikidata', 'reliability' => 'reference', 'validation' => 'confirmed', 'url' => 'https://www.wikidata.org/wiki/Q1']);
    }

    public function test_identity_search_handles_source_failure_and_offers_manual_entry(): void
    {
        Http::fake(['www.wikidata.org/*' => Http::response('Too many requests', 429)]);
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $this->actingAs($user)->post('/chevaux/rechercher', ['name' => 'Inconnu'])->assertOk()->assertSee('momentanément indisponible')->assertSee('Saisie manuelle');
    }

    public function test_transfer_to_own_stable_keeps_history(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $stable = $this->stable($user);
        $this->actingAs($user)->post("/chevaux/{$horse->id}/transferer", ['organization_id' => $stable->id])->assertRedirect();
        $this->assertSame($stable->id, $horse->fresh()->organization_id);
        $this->assertSame(1, Horse::count());
    }

    public function test_pedigree_can_be_edited_and_displayed(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->actingAs($user)->put("/chevaux/{$horse->id}/genealogie", ['studbook' => 'SF', 'rel' => ['sire' => ['name' => 'GALOUBET A'], 'dam_sire' => ['name' => 'ALME']]])->assertRedirect();
        $this->actingAs($user)->get("/chevaux/{$horse->id}/genealogie")->assertOk()->assertSee('GALOUBET A')->assertSee('ALME');
    }

    public function test_offline_selection_is_limited(): void
    {
        config(['equine.offline.max_horses' => 1]);
        $user = $this->user();
        $this->grantLicense($user->personalOrganization());
        $h1 = $this->horseFor($user);
        $h2 = $this->horseFor($user);
        $this->actingAs($user)->post("/chevaux/{$h1->id}/hors-ligne")->assertSessionHas('success');
        $this->actingAs($user)->post("/chevaux/{$h2->id}/hors-ligne")->assertSessionHas('error');
    }
}
