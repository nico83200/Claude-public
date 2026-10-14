<?php

namespace Tests\Feature;

use App\Mail\SupportMessage;
use App\Models\License;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Entitlements;
use App\Services\ReferralService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Parrainage (15 jours de l'offre Propriétaire), assistance par e-mail et lien Instagram. */
class ReferralSupportTest extends TestCase
{
    private function register(string $email = 'ami@exemple.fr')
    {
        return $this->post('/register', ['name' => 'Camille Ami', 'email' => $email, 'password' => 'Cheval2026secret', 'password_confirmation' => 'Cheval2026secret', 'terms' => '1']);
    }

    private function verify(User $user): void
    {
        $this->actingAs($user)->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]))->assertRedirect();
    }

    public function test_menu_shows_referral_support_and_instagram(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee(route('referral.index'))->assertSee(route('support.create'))
            ->assertSee('href="https://www.instagram.com/jackcieapp/"', false)->assertSee('data-instagram-link', false);
    }

    public function test_referee_signup_gives_referrer_15_days_of_owner_plan_once_email_is_confirmed(): void
    {
        Notification::fake();
        $parrain = $this->user(['name' => 'Léa Martin']);
        $page = $this->actingAs($parrain)->get('/parrainage')->assertOk()->assertSee('data-referral-link', false)->assertSee('data-referral-sms', false)->assertSee(route('referral.qr'));
        $link = app(ReferralService::class)->link($parrain->fresh());
        $page->assertSee($link);
        $this->assertStringContainsString('sms:?&body=', $page->getContent());
        $this->actingAs($parrain)->get('/parrainage/qr.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertSame('free', app(Entitlements::class)->for($parrain->personalOrganization())['source']);

        // Le filleul ouvre le lien puis s'inscrit.
        auth()->logout();
        $this->get($link)->assertRedirect('/register?src=parrainage')->assertCookie(ReferralService::COOKIE);
        $this->get('/register?src=parrainage')->assertOk()->assertSee('data-referral-banner', false)->assertSee('Léa');
        $this->register()->assertRedirect();
        $filleul = User::where('email', 'ami@exemple.fr')->first();
        $this->assertSame($parrain->id, $filleul->referred_by_id);
        $this->assertSame('pending', Referral::first()->status);
        $this->assertSame(0, License::where('source', 'referral')->count(), 'Rien avant la confirmation de l\'adresse e-mail.');

        // Adresse confirmée : 15 jours de l'offre Propriétaire pour le parrain, une seule fois.
        $this->verify($filleul);
        $org = $parrain->personalOrganization();
        $ent = app(Entitlements::class)->for($org);
        $this->assertSame('particulier', $ent['plan']->slug);
        $this->assertSame('referral', $ent['source']);
        $license = License::where('source', 'referral')->first();
        $this->assertTrue($license->ends_at->isSameDay(now()->addDays(15)));
        Notification::assertSentTo($parrain, AppNotification::class, fn ($n) => $n->kind === 'referral' && str_contains($n->title, 'Camille Ami'));
        app(ReferralService::class)->reward($filleul->fresh());
        $this->assertSame(1, License::where('source', 'referral')->count());

        // Deuxième filleul : la même offre est prolongée de 15 jours.
        auth()->logout();
        $this->flushSession();
        $this->get($link);
        $this->register('ami2@exemple.fr');
        $this->verify(User::where('email', 'ami2@exemple.fr')->first());
        $this->assertTrue($license->fresh()->ends_at->isSameDay(now()->addDays(30)));
        $this->actingAs($parrain)->get('/parrainage')->assertSee('Camille Ami')->assertSee('Jours offerts');

        // Fin des jours offerts : retour à l'offre gratuite, sans bloquer l'espace.
        $this->travel(31)->days();
        app(Entitlements::class)->flush($org);
        $this->assertSame('free', app(Entitlements::class)->for($org)['source']);
    }

    public function test_paid_subscriber_gets_banked_days_and_self_or_unknown_codes_are_ignored(): void
    {
        $parrain = $this->user();
        $org = $parrain->personalOrganization();
        $this->grantLicense($org, 'multi-chevaux');
        $code = app(ReferralService::class)->code($parrain);

        // Code inconnu ou propre code : aucun parrainage.
        $this->get('/p/INCONNU1')->assertRedirect('/register');
        $this->assertNull(app(ReferralService::class)->attach($parrain, $code));

        $filleul = $this->user();
        app(ReferralService::class)->attach($filleul, strtolower($code));
        $this->assertSame('banked', Referral::first()->status, 'Abonnement en cours : jours mis en réserve.');
        $this->assertSame('multi-chevaux', app(Entitlements::class)->for($org)['plan']->slug, 'L\'offre en cours n\'est jamais remplacée.');
        $this->actingAs($parrain)->get('/parrainage')->assertSee('data-referral-banked', false)->assertDontSee('Utiliser mes 15 jours');
        $this->actingAs($parrain)->post('/parrainage/utiliser')->assertSessionHas('error');

        // Abonnement terminé : les jours en réserve deviennent utilisables.
        License::where('organization_id', $org->id)->update(['status' => 'expired', 'ends_at' => now()->subDay()]);
        app(Entitlements::class)->flush($org);
        $this->actingAs($parrain)->get('/parrainage')->assertSee('Utiliser mes 15 jours');
        $this->actingAs($parrain)->post('/parrainage/utiliser')->assertSessionHas('success');
        $this->assertSame('particulier', app(Entitlements::class)->for($org)['plan']->slug);
        $this->assertSame('used', Referral::first()->status);
    }

    public function test_support_form_emails_the_team_with_reply_to_and_context(): void
    {
        Mail::fake();
        $user = $this->user(['name' => 'Léa Martin', 'email' => 'lea@exemple.fr']);
        $this->actingAs($user)->get('/assistance')->assertOk()->assertSee('data-support-form', false)->assertSee('contact@nlapps.fr');
        $this->actingAs($user)->post('/assistance', ['topic' => 'bug', 'message' => 'Trop court'.'', 'page' => '/chevaux'])->assertSessionHasNoErrors();
        Mail::assertSent(SupportMessage::class, fn ($m) => $m->hasTo('contact@nlapps.fr') && $m->hasReplyTo('lea@exemple.fr')
            && $m->topic === 'Un problème ou un bug' && $m->body === 'Trop court' && $m->context['Page'] === '/chevaux');
        $mail = Mail::sent(SupportMessage::class)->first();
        $this->assertStringContainsString('Léa Martin', $mail->envelope()->subject);
        $this->assertStringContainsString('Trop court', $mail->render());

        Mail::fake();
        $this->actingAs($user)->post('/assistance', ['topic' => 'question', 'message' => 'court'])->assertSessionHasErrors('message');
        $this->actingAs($user)->post('/assistance', ['topic' => 'piratage', 'message' => 'Un message assez long'])->assertSessionHasErrors('topic');
        $this->actingAs($user)->post('/assistance', ['topic' => 'idea', 'message' => 'Voici une capture du souci', 'attachment' => UploadedFile::fake()->image('capture.png')])->assertRedirect('/assistance');
        Mail::assertSent(SupportMessage::class, fn ($m) => count($m->attachments()) === 1);
    }
}
