<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_registration_creates_account_and_personal_space(): void
    {
        Notification::fake();
        $this->post('/register', ['name' => 'Alice Martin', 'email' => 'Alice@Example.com', 'password' => 'Cheval2026x', 'password_confirmation' => 'Cheval2026x', 'terms' => '1'])
            ->assertRedirect('/dashboard');

        $user = User::where('email', 'alice@example.com')->firstOrFail();
        $this->assertNotNull($user->terms_accepted_at);
        $org = $user->personalOrganization();
        $this->assertNotNull($org);
        $this->assertSame('owner', $user->memberships()->first()->role->key);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_requires_terms_and_strong_password(): void
    {
        $this->post('/register', ['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors(['password', 'terms']);
        $this->assertDatabaseMissing('users', ['email' => 'bob@example.com']);
    }

    public function test_unverified_user_is_redirected_to_verification(): void
    {
        $user = $this->user(['email_verified_at' => null]);
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/email/verify');
    }

    public function test_email_verification_link(): void
    {
        $user = $this->user(['email_verified_at' => null]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($user)->get($url)->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_login_and_logout(): void
    {
        $user = $this->user(['password' => 'Cheval2026x']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Cheval2026x'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $user = $this->user(['password' => 'Cheval2026x']);
        $this->post('/login', ['email' => $user->email, 'password' => 'mauvais'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->user(['password' => 'Cheval2026x']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'faux'.$i]);
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'Cheval2026x'])->assertStatus(429);
    }

    public function test_suspended_user_cannot_login_and_is_logged_out(): void
    {
        $user = $this->user(['password' => 'Cheval2026x']);
        $user->forceFill(['suspended_at' => now()])->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'Cheval2026x'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = $this->user();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/reset-password', ['token' => $notification->token, 'email' => $user->email, 'password' => 'Nouveau2026x', 'password_confirmation' => 'Nouveau2026x'])
                ->assertSessionHasNoErrors();

            return true;
        });
        $this->post('/login', ['email' => $user->email, 'password' => 'Nouveau2026x'])->assertRedirect('/dashboard');
    }

    public function test_guest_is_redirected_from_private_pages(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/chevaux')->assertRedirect('/login');
        $this->postJson('/sync/pull', [])->assertStatus(401);
    }

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('Créer un compte');
        $this->get('/tarifs')->assertOk()->assertSee('Particulier');
        $this->get('/confidentialite')->assertOk();
        $this->get('/conditions')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }
}
