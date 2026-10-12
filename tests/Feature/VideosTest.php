<?php

namespace Tests\Feature;

use App\Models\Video;
use App\Models\VideoView;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VideosTest extends TestCase
{
    private function asAdmin()
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();

        return [$admin, $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])];
    }

    private function sendChunks($as, Video $video, string $kind, string $name, string $content, int $chunk = 300000)
    {
        $id = (string) Str::uuid();
        $parts = str_split($content, $chunk);
        $res = null;
        foreach ($parts as $i => $part) {
            $res = $as->post(route('admin.videos.upload', [$video, $kind]), [
                'upload_id' => $id, 'index' => $i, 'total' => count($parts), 'name' => $name,
                'chunk' => UploadedFile::fake()->createWithContent('part', $part),
            ], ['Accept' => 'application/json']);
        }

        return $res;
    }

    /** Titres des vidéos proposées automatiquement sur la page. */
    private function pending(string $html): array
    {
        if (! preg_match('#<script type="application/json" id="pending-videos">(.*?)</script>#s', $html, $m)) {
            return [];
        }

        return array_column(json_decode($m[1], true), 'title');
    }

    public function test_tutorial_is_shown_once_to_new_accounts_and_not_again_after_being_seen(): void
    {
        $tutorial = Video::where('kind', 'onboarding')->where('device', 'desktop')->firstOrFail(); // requêtes de test = ordinateur
        $this->assertSame('builtin', $tutorial->source);
        $user = $this->user();

        $this->assertSame(['Bienvenue dans Jack&Cie'], $this->pending($this->actingAs($user)->get('/dashboard')->assertOk()->getContent()));
        // Uniquement sur la page d'accueil ; reproposée à chaque chargement de l'accueil tant qu'elle n'est ni vue ni passée.
        $this->actingAs($user)->get('/chevaux')->assertOk()->assertDontSee('id="pending-videos"', false);
        $this->assertSame(['Bienvenue dans Jack&Cie'], $this->pending($this->actingAs($user)->get('/dashboard')->assertOk()->getContent()));

        $this->actingAs($user)->postJson(route('videos.seen', $tutorial), ['completed' => true])->assertOk();
        $this->assertTrue(VideoView::where(['user_id' => $user->id, 'video_id' => $tutorial->id])->value('completed'));
        $this->flushSession();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('id="pending-videos"', false);
        $this->actingAs($user)->get('/tutoriels')->assertOk()->assertSee('Bienvenue dans Jack&amp;Cie', false)->assertSee('Déjà vue');
    }

    public function test_new_accounts_get_the_tutorial_matching_their_device(): void
    {
        $this->assertSame(['mobile', 'desktop'], Video::where('kind', 'onboarding')->orderBy('id')->pluck('device')->all());
        $user = $this->user();
        $desktop = $this->actingAs($user)->get('/dashboard', ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/140.0'])->getContent();
        preg_match('#id="pending-videos">(.*?)</script>#s', $desktop, $m);
        $this->assertSame(['desktop'], array_column(json_decode($m[1], true), 'device'));
        $this->flushSession();
        $phone = $this->actingAs($user)->get('/dashboard', ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 7) Mobile Chrome/140.0'])->getContent();
        preg_match('#id="pending-videos">(.*?)</script>#s', $phone, $m);
        $this->assertSame(['mobile'], array_column(json_decode($m[1], true), 'device'));
        // La bibliothèque « Tutoriels » propose les deux
        $this->actingAs($user)->get('/tutoriels')->assertOk()->assertSee('Téléphone')->assertSee('Ordinateur');
    }

    public function test_announcement_reaches_existing_accounts_at_next_login_only(): void
    {
        Storage::fake('local');
        $existing = $this->user();
        $existing->forceFill(['created_at' => now()->subMonth()])->save();
        // Comptes antérieurs au tutoriel : ils ne le reçoivent pas automatiquement (il reste dans « Tutoriels »).
        Video::where('kind', 'onboarding')->update(['published_at' => now()->subDay()]);
        [, $as] = $this->asAdmin();

        $as->post(route('admin.videos.store'), ['title' => 'Nouveau : budget récurrent', 'kind' => 'announcement'])->assertRedirect();
        $video = Video::where('title', 'Nouveau : budget récurrent')->firstOrFail();
        $as->post(route('admin.videos.publish', $video))->assertStatus(422); // pas de fichier
        $this->sendChunks($as, $video, 'video', 'nouveau.mp4', str_repeat('v', 700000))->assertOk()->assertJson(['done' => true]);
        $this->sendChunks($as, $video, 'audio', 'voix.mp3', str_repeat('a', 1000))->assertOk();
        $this->sendChunks($as, $video, 'captions', 'st.srt', "1\n00:00:01,000 --> 00:00:03,500\nBonjour\n")->assertOk();
        $video->refresh();
        $this->assertSame(700000, Storage::disk('local')->size($video->video_path));
        $this->assertStringStartsWith("WEBVTT\n\n1\n00:00:01.000 --> 00:00:03.500", Storage::disk('local')->get($video->captions_path));
        Carbon::setTestNow(now()->addMinute());
        $as->post(route('admin.videos.publish', $video))->assertSessionHas('success');

        // Une nouveauté publiée s'affiche dès le prochain chargement de l'accueil, sans nouvelle connexion.
        $this->actingAs($existing)->get('/chevaux')->assertOk();
        $this->assertSame(['Nouveau : budget récurrent'], $this->pending($this->actingAs($existing)->get('/dashboard')->assertOk()->getContent()));
        $html = $this->actingAs($existing)->get('/tutoriels')->assertOk()->getContent();
        $this->assertStringContainsString(route('videos.file', [$video, 'audio']), str_replace('\\/', '/', $html));
        $newcomer = $this->user();
        $this->flushSession();
        $this->assertSame(['Bienvenue dans Jack&Cie'], $this->pending($this->actingAs($newcomer)->get('/dashboard')->assertOk()->getContent()));
        Carbon::setTestNow();
    }

    public function test_files_are_streamed_with_range_support_and_hidden_until_published(): void
    {
        Storage::fake('local');
        [, $as] = $this->asAdmin();
        $as->post(route('admin.videos.store'), ['title' => 'Brouillon', 'kind' => 'announcement']);
        $video = Video::where('title', 'Brouillon')->firstOrFail();
        $this->sendChunks($as, $video, 'video', 'clip.mp4', str_repeat('0123456789', 100));
        $user = $this->user();

        $this->actingAs($user)->get(route('videos.file', [$video, 'video']))->assertNotFound();
        [, $as] = $this->asAdmin();
        $as->post(route('admin.videos.publish', $video->fresh()))->assertSessionHas('success');
        $res = $this->actingAs($user)->get(route('videos.file', [$video, 'video']), ['Range' => 'bytes=10-19']);
        $res->assertStatus(206);
        $this->assertSame('video/mp4', $res->headers->get('Content-Type'));
        $this->assertSame('bytes 10-19/1000', $res->headers->get('Content-Range'));
    }

    public function test_upload_rejects_wrong_formats_and_only_super_admin_manages_videos(): void
    {
        Storage::fake('local');
        [, $as] = $this->asAdmin();
        $as->post(route('admin.videos.store'), ['title' => 'Test', 'kind' => 'announcement']);
        $video = Video::where('title', 'Test')->firstOrFail();
        $this->sendChunks($as, $video, 'video', 'virus.exe', 'MZ')->assertStatus(422);
        $this->sendChunks($as, $video, 'captions', 'faux.vtt', 'pas des sous-titres')->assertStatus(422);
        $this->assertNull($video->fresh()->captions_path);
        $builtin = Video::where('source', 'builtin')->firstOrFail();
        $this->sendChunks($as, $builtin, 'video', 'autre.mp4', 'x')->assertStatus(422);

        $user = $this->user();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp])->get(route('admin.videos.index'))->assertNotFound();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp])->post(route('admin.videos.store'), ['title' => 'x', 'kind' => 'announcement'])->assertNotFound();
        [, $as] = $this->asAdmin();
        $as->get(route('admin.videos.index'))->assertOk()->assertSee('Bienvenue dans Jack&amp;Cie', false);
        $as->get(route('admin.videos.edit', $video))->assertOk()->assertSee('Voix off');
    }
}
