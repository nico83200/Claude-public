<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Notifications\AppNotification;
use App\Services\MailDesign;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** E-mails : mise en forme et textes réglés par le super-administrateur. */
class MailDesignTest extends TestCase
{
    private function admin()
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();

        return $admin;
    }

    private function as($admin)
    {
        return $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    }

    private function form(array $over = []): array
    {
        $base = MailDesign::defaults();
        unset($base['kinds']);
        $base['logo_link'] = 1;
        $base['show_fallback_link'] = 1;

        return array_replace($base, $over);
    }

    public function test_default_mail_has_logo_and_french_texts(): void
    {
        $user = $this->user();
        $user->forceFill(['name' => 'Nicolas Lefèvre'])->save();
        $html = (string) (new AppNotification('appointment', 'Rendez-vous demain', 'Séance monte libre – Jack le 14/10/2026 à 17:30.', url('/calendrier/18')))->toMail($user)->render();

        $this->assertStringContainsString('/brand/jackcie-logo.png', $html);
        $this->assertStringContainsString('Bonjour Nicolas,', $html);
        $this->assertStringContainsString('Séance monte libre – Jack le 14/10/2026 à 17:30.', $html);
        $this->assertStringContainsString('Si le bouton ne fonctionne pas', $html);
        $this->assertStringNotContainsString('having trouble', $html);
        $this->assertStringNotContainsString('All rights reserved', $html);
        $this->assertSame('Rendez-vous demain', (new AppNotification('appointment', 'Rendez-vous demain', 'x'))->toMail($user)->subject);
    }

    public function test_super_admin_sets_texts_around_the_reason_globally_and_per_type(): void
    {
        $admin = $this->admin();
        $this->as($admin)->get('/admin/emails')->assertOk()->assertSee('data-mail-design', false)->assertSee('data-mail-kind="appointment"', false)->assertSee('data-mail-kind="reset_password"', false);

        $this->as($admin)->put('/admin/emails', $this->form([
            'subject_prefix' => '[{app}]',
            'intro' => 'Petit rappel pour **{prenom}** :',
            'outro' => 'Bonne journée avec vos chevaux !',
            'button_color' => '#aa0000',
            'logo' => 'wordmark',
            'kinds' => [
                'appointment' => ['enabled' => 1, 'subject' => 'Rendez-vous : {titre}', 'outro' => 'Pensez à prévenir le vétérinaire.', 'button_label' => 'Voir le rendez-vous'],
                'care_due' => ['enabled' => 1, 'intro' => '-'],
                'daily_reminder' => [],
            ],
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $user = $this->user();
        $user->forceFill(['name' => 'Léa Martin'])->save();
        $appointment = (new AppNotification('appointment', 'Demain 17 h', 'Séance avec Jack.', url('/calendrier/1')))->toMail($user);
        $this->assertSame('['.config('app.name').'] Rendez-vous : Demain 17 h', $appointment->subject);
        $html = (string) $appointment->render();
        $this->assertStringContainsString('Petit rappel pour <strong>Léa</strong> :', $html);
        $this->assertStringContainsString('Pensez à prévenir le vétérinaire.', $html);
        $this->assertStringNotContainsString('Bonne journée avec vos chevaux', $html);
        $this->assertStringContainsString('Voir le rendez-vous', $html);
        $this->assertStringContainsString('#aa0000', $html);
        $this->assertStringContainsString('jackcie-wordmark.png', $html);
        // Ordre : avant le motif, motif, après le motif.
        $this->assertMatchesRegularExpression('#Petit rappel.*Séance avec Jack\..*Pensez à prévenir#s', $html);

        // « - » efface le texte général pour ce type ; les autres gardent le texte général.
        $care = (string) (new AppNotification('care_due', 'Vermifuge', 'Vermifuge de Jack le 20/10.', null))->toMail($user)->render();
        $this->assertStringNotContainsString('Petit rappel', $care);
        $this->assertStringContainsString('Bonne journée avec vos chevaux', $care);

        // Le type « Rappel de saisie » n'a pas été coché : son e-mail est coupé, pas la notification.
        FeatureFlag::where('key', 'email_notifications')->update(['enabled_globally' => true]);
        cache()->flush();
        $via = (new AppNotification('daily_reminder', 'x', 'y'))->via($user);
        $this->assertNotContains('mail', $via);
        $this->assertContains('database', $via);
        $this->assertContains('mail', (new AppNotification('appointment', 'x', 'y'))->via($user));
    }

    public function test_live_preview_and_test_send_are_reserved_to_super_admin(): void
    {
        $admin = $this->admin();
        $json = $this->as($admin)->postJson('/admin/emails/apercu', $this->form(['outro' => 'Texte non enregistré', 'preview_kind' => 'weight_due']))->assertOk()->json();
        $this->assertStringContainsString('Texte non enregistré', $json['html']);
        $this->assertStringContainsString('Pensez à mesurer le poids', $json['html']);
        $this->assertSame('Pesée à faire', $json['subject']);
        $this->assertNotSame('Texte non enregistré', app(MailDesign::class)->settings()['outro']);
        $this->as($admin)->postJson('/admin/emails/apercu', $this->form(['button_color' => 'rouge']))->assertStatus(422);

        $this->as($admin)->post('/admin/emails/essai', ['preview_kind' => 'reset_password'])->assertSessionHas('success');

        $user = $this->user();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/emails')->assertNotFound();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->postJson('/admin/emails/apercu', $this->form())->assertNotFound();
    }

    public function test_custom_logo_is_uploaded_and_served_publicly(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->as($admin)->post('/admin/emails/logo', ['logo_file' => UploadedFile::fake()->image('logo.png', 400, 120)])->assertSessionHasNoErrors();
        $this->assertSame('custom', app(MailDesign::class)->settings()['logo']);
        $logo = app(MailDesign::class)->logoUrl();
        $this->assertStringContainsString('/email/logo', $logo);
        auth()->logout();
        $this->get($logo)->assertOk();

        $this->as($admin)->post('/admin/emails/logo', ['logo_file' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('logo_file');
        $this->as($admin)->delete('/admin/emails/logo')->assertSessionHas('success');
        $this->assertSame('logo', app(MailDesign::class)->settings()['logo']);
        $this->get('/email/logo')->assertNotFound();
    }

    public function test_account_emails_use_the_same_design(): void
    {
        $admin = $this->admin();
        $this->as($admin)->put('/admin/emails', $this->form([
            'footer' => 'NLapps – Jack&Cie',
            'kinds' => ['reset_password' => ['outro' => 'Besoin d\'aide ? Répondez à cet e-mail.']],
        ]))->assertSessionHasNoErrors();
        $user = $this->user();
        $html = (string) (new ResetPassword('tok123'))->toMail($user)->render();
        $this->assertStringContainsString('reset-password/tok123', $html);
        $this->assertStringContainsString('Besoin d&#039;aide ? Répondez à cet e-mail.', $html);
        $this->assertStringContainsString('NLapps – Jack&amp;Cie', $html);
        $this->assertStringContainsString('Choisir un nouveau mot de passe', $html);
        $this->assertTrue(app(MailDesign::class)->enabled('reset_password'));
    }
}
