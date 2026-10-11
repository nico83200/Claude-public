<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Auth\SocialLogin;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Les super-administrateurs sont prévenus de chaque nouvelle inscription, une seule fois. */
class AdminRegistrationNotificationTest extends TestCase
{
    public function test_super_admins_are_notified_once_of_a_new_registration(): void
    {
        Notification::fake();
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $suspendedAdmin = $this->user();
        $suspendedAdmin->forceFill(['is_super_admin' => true, 'suspended_at' => now()])->save();
        $member = $this->user();

        $this->post('/register', ['name' => 'Nina Petit', 'email' => 'nina@exemple.fr', 'password' => 'Cheval2026secure', 'password_confirmation' => 'Cheval2026secure', 'terms' => '1'])->assertRedirect();
        $nina = User::where('email', 'nina@exemple.fr')->firstOrFail();

        Notification::assertSentToTimes($admin, AppNotification::class, 1);
        Notification::assertSentTo($admin, AppNotification::class, fn (AppNotification $n) => $n->kind === 'new_user'
            && str_contains($n->body, 'Nina Petit (nina@exemple.fr)') && $n->url === route('admin.users.show', $nina));
        Notification::assertNotSentTo($suspendedAdmin, AppNotification::class);
        Notification::assertNotSentTo($member, AppNotification::class);
    }

    public function test_registration_with_google_is_announced_too(): void
    {
        Notification::fake();
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();

        app(SocialLogin::class)->login('google', ['sub' => 'g-1', 'email' => 'leo@gmail.com', 'email_verified' => true, 'name' => 'Léo Garnier']);

        Notification::assertSentTo($admin, AppNotification::class, fn (AppNotification $n) => $n->kind === 'new_user' && str_contains($n->body, 'avec Google'));
    }
}
