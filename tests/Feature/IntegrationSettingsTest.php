<?php

namespace Tests\Feature;

use App\Mail\TestMail;
use App\Models\ApplicationSetting;
use App\Models\AuditLog;
use App\Services\Billing\StripeGateway;
use App\Services\IntegrationSettings;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    private function asAdmin()
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();

        return $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_only_super_admin_reaches_integrations(): void
    {
        $this->actingAs($this->user())->withSession(['auth.password_confirmed_at' => time()])->get('/admin/integrations')->assertNotFound();
        $this->asAdmin()->get('/admin/integrations')->assertOk()->assertSee('Envoi des emails')->assertSee(route('stripe.webhook'));
    }

    public function test_mail_settings_are_saved_encrypted_and_applied(): void
    {
        $this->asAdmin()->put('/admin/integrations/email', [
            'mailer' => 'smtp', 'host' => 'smtp.exemple.fr', 'port' => 465, 'encryption' => 'ssl', 'username' => 'jack',
            'password' => 'S3cret!pass', 'from_address' => 'bonjour@exemple.fr', 'from_name' => 'Jack&Cie',
        ])->assertSessionHasNoErrors();

        $raw = ApplicationSetting::where('key', 'integration.mail.password')->first()->value;
        $this->assertNotSame('S3cret!pass', $raw);
        $this->assertStringNotContainsString('S3cret', json_encode(ApplicationSetting::pluck('value')));
        $this->assertSame('smtp.exemple.fr', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('S3cret!pass', config('mail.mailers.smtp.password'));
        $this->assertSame('bonjour@exemple.fr', config('mail.from.address'));

        // Le secret n'est jamais réaffiché ; un champ vide le conserve
        $this->asAdmin()->get('/admin/integrations')->assertOk()->assertDontSee('S3cret!pass')->assertSee('••••pass');
        $this->asAdmin()->put('/admin/integrations/email', ['mailer' => 'smtp', 'host' => 'smtp2.exemple.fr', 'port' => 587, 'encryption' => 'tls', 'password' => '', 'from_address' => 'a@exemple.fr', 'from_name' => 'J'])->assertSessionHasNoErrors();
        app()->forgetScopedInstances();
        $this->assertSame('S3cret!pass', app(IntegrationSettings::class)->get('mail.password'));

        // Réglages réappliqués au démarrage d'une nouvelle requête
        config(['mail.mailers.smtp.host' => 'env-host']);
        app()->forgetScopedInstances();
        app(IntegrationSettings::class)->apply();
        $this->assertSame('smtp2.exemple.fr', config('mail.mailers.smtp.host'));
    }

    public function test_send_test_email(): void
    {
        Mail::fake();
        $this->asAdmin()->post('/admin/integrations/email/test', ['to' => 'test@exemple.fr'])->assertSessionHas('success');
        Mail::assertSent(TestMail::class, fn ($m) => $m->hasTo('test@exemple.fr'));
    }

    public function test_stripe_keys_are_validated_saved_and_tested(): void
    {
        $this->asAdmin()->put('/admin/integrations/stripe', ['secret' => 'pas-une-cle'])->assertSessionHasErrors('secret');
        $this->asAdmin()->put('/admin/integrations/stripe', ['key' => 'pk_live_abc', 'secret' => 'sk_test_abc'])->assertSessionHasErrors('key');

        $this->asAdmin()->put('/admin/integrations/stripe', ['key' => 'pk_test_abc', 'secret' => 'sk_test_supersecret1234', 'webhook_secret' => 'whsec_xyz9876'])->assertSessionHasNoErrors();
        $this->assertSame('sk_test_supersecret1234', config('services.stripe.secret'));
        $this->assertSame('pk_test_abc', config('services.stripe.key'));
        $this->asAdmin()->get('/admin/integrations')->assertDontSee('sk_test_supersecret1234')->assertDontSee('whsec_xyz9876')->assertSee('••••1234');

        $gateway = Mockery::mock(StripeGateway::class);
        $gateway->shouldReceive('configured')->andReturn(true);
        $gateway->shouldReceive('testConnection')->once()->andReturn(['livemode' => false, 'currencies' => ['eur']]);
        $this->app->instance(StripeGateway::class, $gateway);
        $this->asAdmin()->post('/admin/integrations/stripe/test')->assertSessionHas('success');

        // Effacer la clé enregistrée : retour à la valeur du .env
        $this->asAdmin()->put('/admin/integrations/stripe', ['clear' => ['secret', 'webhook_secret']])->assertSessionHasNoErrors();
        app()->forgetScopedInstances();
        $this->assertNull(app(IntegrationSettings::class)->get('stripe.secret'));
    }

    public function test_settings_changes_are_audited(): void
    {
        $this->asAdmin()->put('/admin/integrations/stripe', ['secret' => 'sk_test_abcd1234'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.stripe_settings_updated']);
        $this->assertStringNotContainsString('sk_test_abcd1234', json_encode(AuditLog::all()));
    }
}
