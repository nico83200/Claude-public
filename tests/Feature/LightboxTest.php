<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Photos et documents image : ouverts dans la visionneuse de la page, plus dans un nouvel onglet. */
class LightboxTest extends TestCase
{
    public function test_photos_and_image_documents_open_in_the_viewer(): void
    {
        Storage::fake('local');
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $horse = $this->horseFor($user);
        $this->actingAs($user)->post("/chevaux/{$horse->id}/photos", ['photo' => UploadedFile::fake()->image('cheval.jpg', 800, 600), 'caption' => 'Au pré'])->assertRedirect();

        $this->actingAs($user)->get("/chevaux/{$horse->id}")->assertOk()
            ->assertSee('data-lightbox="photos-'.$horse->id.'"', false)->assertSee('data-caption="Au pré"', false)
            ->assertDontSee('target="_blank"><img', false);

        $this->actingAs($user)->post("/chevaux/{$horse->id}/documents", ['file' => UploadedFile::fake()->image('radio.png'), 'title' => 'Radio du boulet', 'category' => 'report'])->assertRedirect();
        $this->actingAs($user)->post("/chevaux/{$horse->id}/documents", ['file' => UploadedFile::fake()->create('ordonnance.pdf', 20, 'application/pdf'), 'title' => 'Ordonnance', 'category' => 'report'])->assertRedirect();
        $page = $this->actingAs($user)->get("/chevaux/{$horse->id}/documents")->assertOk();
        $page->assertSee('data-lightbox="docs-'.$horse->id.'" data-caption="Radio du boulet"', false);
        $this->assertSame(1, substr_count($page->getContent(), 'data-lightbox="docs-'), 'le PDF reste un téléchargement');
    }
}
