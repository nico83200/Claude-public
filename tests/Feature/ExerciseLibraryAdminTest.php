<?php

namespace Tests\Feature;

use App\Models\Exercise;
use Database\Seeders\ExerciseLibrarySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Bibliothèque d'exercices par défaut : contenu livré, gestion par le super-administrateur, démonstrations. */
class ExerciseLibraryAdminTest extends TestCase
{
    private function admin()
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();

        return $admin;
    }

    private function asAdmin($admin)
    {
        return $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    }

    /** Envoie un fichier en morceaux comme le navigateur. */
    private function sendInChunks($admin, Exercise $exercise, string $name, string $bytes, int $size = 2048)
    {
        $id = (string) Str::uuid();
        $parts = str_split($bytes, $size);
        $response = null;
        foreach ($parts as $i => $part) {
            $response = $this->asAdmin($admin)->post(route('admin.exercises.upload', $exercise), [
                'upload_id' => $id, 'index' => $i, 'total' => count($parts), 'name' => $name,
                'chunk' => UploadedFile::fake()->createWithContent('part', $part),
            ], ['Accept' => 'application/json']);
        }

        return $response;
    }

    public function test_library_ships_many_exercises_and_never_overwrites_admin_changes(): void
    {
        $this->seed(ExerciseLibrarySeeder::class);
        $count = count(ExerciseLibrarySeeder::library());
        $this->assertGreaterThanOrEqual(120, $count);
        $this->assertSame($count, Exercise::where('scope', 'default')->count());

        $ex = Exercise::where('seed_key', 'pas-renes-longues')->firstOrFail();
        $ex->update(['name' => 'Pas rênes longues (version écurie)', 'instructions' => 'Consigne revue.']);
        Exercise::where('seed_key', 'volte-de-10-m')->delete();

        $this->seed(ExerciseLibrarySeeder::class);
        $this->assertSame('Consigne revue.', $ex->fresh()->instructions);
        $this->assertSame($count, Exercise::where('scope', 'default')->count(), 'Un exercice supprimé réapparaît, un exercice modifié n\'est pas dupliqué.');
    }

    public function test_super_admin_creates_edits_and_archives_default_exercises(): void
    {
        $this->seed(ExerciseLibrarySeeder::class);
        $admin = $this->admin();
        $user = $this->user();

        $this->asAdmin($admin)->get('/admin/exercices?q=volte')->assertOk()->assertSee('Voltes de 10 m')->assertDontSee('Croisillon d&#039;échauffement', false);
        $this->asAdmin($admin)->post('/admin/exercices', ['name' => 'Huit de chiffre au galop', 'level' => 'advanced', 'objective' => 'Changer de pied au centre.', 'tags_text' => 'galop, changement'])
            ->assertRedirect();
        $new = Exercise::where('name', 'Huit de chiffre au galop')->firstOrFail();
        $this->assertSame('default', $new->scope);
        $this->assertNull($new->user_id);

        $this->asAdmin($admin)->put("/admin/exercices/{$new->id}", ['name' => 'Huit de chiffre au galop', 'level' => 'advanced', 'objective' => 'Changement de pied simple au centre.'])->assertSessionHas('success');
        $this->assertSame(2, $new->fresh()->version);

        // Visible par tous les utilisateurs, mais modifiable seulement dans l'administration.
        $this->actingAs($user)->get("/exercices/{$new->id}")->assertOk()->assertSee('Changement de pied simple au centre.')->assertDontSee('Modifier (administration)');
        $this->actingAs($user)->get("/exercices/{$new->id}/modifier")->assertForbidden();
        $this->asAdmin($admin)->get("/exercices/{$new->id}")->assertSee('Modifier (administration)');

        $this->asAdmin($admin)->post("/admin/exercices/{$new->id}/archiver")->assertSessionHas('success');
        $this->actingAs($user)->get('/exercices?q=Huit+de+chiffre')->assertOk()->assertDontSee('/exercices/'.$new->id.'"', false);

        // Réservé au super-administrateur ; les exercices personnels n'y sont pas modifiables.
        $this->actingAs($user)->get('/admin/exercices')->assertNotFound();
        $this->actingAs($user)->post('/admin/exercices', ['name' => 'Pirate', 'level' => 'all'])->assertNotFound();
        $this->actingAs($user)->post('/exercices', ['name' => 'Mon exercice', 'level' => 'all']);
        $personal = Exercise::where('name', 'Mon exercice')->firstOrFail();
        $this->asAdmin($admin)->get("/admin/exercices/{$personal->id}")->assertNotFound();
    }

    public function test_demonstration_image_and_video_are_uploaded_served_and_replaced(): void
    {
        Storage::fake('local');
        $this->seed(ExerciseLibrarySeeder::class);
        $admin = $this->admin();
        $user = $this->user();
        $ex = Exercise::where('seed_key', 'cession-a-la-jambe')->firstOrFail();

        $png = file_get_contents(public_path('icons/favicon-32.png'));
        $this->sendInChunks($admin, $ex, 'cession.png', $png, 1024)->assertOk()->assertJson(['done' => true]);
        $ex->refresh();
        $this->assertSame('image', $ex->media_type);
        $this->assertSame(1, $ex->version, 'La démonstration ne crée pas de nouvelle version de l\'exercice.');
        Storage::disk('local')->assertExists($ex->media_path);
        $image = $ex->media_path;

        $this->actingAs($user)->get('/exercices/'.$ex->id)->assertOk()->assertSee('Démonstration')->assertSee('data-lightbox="exercise-'.$ex->id.'"', false);
        $this->actingAs($user)->get(route('exercises.media', $ex))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($user)->get('/exercices?q=Cession')->assertSee('Image');

        // Une vidéo remplace l'image (fichier précédent supprimé).
        $mp4 = "\x00\x00\x00\x18ftypmp42".str_repeat("\x00", 5000);
        $this->sendInChunks($admin, $ex, 'cession.mp4', $mp4)->assertOk();
        $ex->refresh();
        $this->assertSame('video', $ex->media_type);
        Storage::disk('local')->assertMissing($image);
        $this->actingAs($user)->get('/exercices/'.$ex->id)->assertSee('<video', false);

        // Mauvais format, faux fichier, envoi d'un non-administrateur.
        $this->sendInChunks($admin, $ex, 'virus.exe', 'MZ')->assertStatus(422);
        $this->sendInChunks($admin, $ex, 'faux.jpg', 'pas une image')->assertStatus(422)->assertJson(['message' => 'Ce fichier n\'est pas une image ou une vidéo lisible.']);
        $this->assertSame('video', $ex->fresh()->media_type);
        $this->actingAs($user)->post(route('admin.exercises.upload', $ex), [])->assertNotFound();

        // Une copie personnelle n'emporte ni la clé de bibliothèque ni le fichier.
        $this->actingAs($user)->post("/exercices/{$ex->id}/dupliquer")->assertRedirect();
        $copy = Exercise::where('duplicated_from_id', $ex->id)->firstOrFail();
        $this->assertSame('personal', $copy->scope);
        $this->assertNull($copy->seed_key);
        $this->assertNull($copy->media_path);

        $this->asAdmin($admin)->delete(route('admin.exercises.remove-media', $ex))->assertSessionHas('success');
        $this->assertNull($ex->fresh()->media_path);
        $this->actingAs($user)->get(route('exercises.media', $ex))->assertNotFound();
    }
}
