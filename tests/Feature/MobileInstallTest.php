<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Services\InvitationService;
use App\Support\Perm;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MobileInstallTest extends TestCase
{
    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    private const ANDROID_TABLET = 'Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';

    private const DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';

    public function test_phones_and_tablets_only_see_install_on_login_and_register(): void
    {
        foreach ([self::IPHONE, self::ANDROID_TABLET] as $ua) {
            foreach (['/login', '/register'] as $page) {
                $html = $this->withHeader('User-Agent', $ua)->get($page)->assertOk()->getContent();
                $this->assertMatchesRegularExpression('/x-show="\$store\.install\.gate"\s+class="text-center"/', $html, $page);
                $this->assertMatchesRegularExpression('/x-show="! \$store\.install\.gate"\s+style="display: none"/', $html, 'formulaire masqué sur '.$page);
                $this->assertStringContainsString('Installez l', $html);
            }
        }
        $html = $this->withHeader('User-Agent', self::DESKTOP)->get('/login')->getContent();
        $this->assertMatchesRegularExpression('/x-show="\$store\.install\.gate"\s+style="display: none"/', $html);
        $this->assertMatchesRegularExpression('/x-show="! \$store\.install\.gate"\s*>/', $html);
    }

    public function test_home_page_offers_install_instead_of_login_on_mobile(): void
    {
        $html = $this->withHeader('User-Agent', self::IPHONE)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString("Installer l'application", $html);
        $this->assertMatchesRegularExpression('/x-show="! \$store\.install\.gate"\s+style="display: none"/', $html);
    }

    public function test_email_can_be_confirmed_from_a_link_without_being_logged_in(): void
    {
        Notification::fake();
        $user = $this->user(['email_verified_at' => null]);
        $user->sendEmailVerificationNotification();
        $url = null;
        Notification::assertSentTo($user, VerifyEmail::class, function ($n) use ($user, &$url) {
            $url = $n->toMail($user)->actionUrl;

            return true;
        });
        $this->assertStringContainsString('/email/confirmer/', $url);
        $this->withHeader('User-Agent', self::IPHONE)->get($url)->assertOk()->assertSee('Adresse email confirmée');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get(str_replace('/confirmer/'.$user->id.'/', '/confirmer/'.($user->id + 999).'/', $url))->assertForbidden();
    }

    public function test_invitation_can_be_accepted_from_the_dashboard(): void
    {
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $guest = $this->user(['email' => 'cavaliere@exemple.fr']);
        app(InvitationService::class)->inviteToHorse($horse, $owner, 'cavaliere@exemple.fr', Perm::halfLeasePreset(), null, null, null);
        $invitation = Invitation::latest('id')->first();

        $this->actingAs($this->user())->post(route('invitations.respond', $invitation), ['decision' => 'accept'])->assertNotFound();
        $this->actingAs($guest)->get('/dashboard')->assertOk()->assertSee('Accepter');
        $this->actingAs($guest)->post(route('invitations.respond', $invitation), ['decision' => 'accept'])->assertRedirect(route('horses.show', $horse));
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->actingAs($guest)->post(route('invitations.respond', $invitation), ['decision' => 'accept'])->assertNotFound();
    }
}
