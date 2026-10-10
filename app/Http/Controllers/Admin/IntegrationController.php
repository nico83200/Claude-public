<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Services\Audit;
use App\Services\Billing\StripeGateway;
use App\Services\IntegrationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Réglages email (SMTP) et Stripe saisis par le super-administrateur.
 * Les secrets sont chiffrés en base et jamais réaffichés (seulement « ••••abcd »).
 */
class IntegrationController extends Controller
{
    public function __construct(private IntegrationSettings $settings) {}

    public function edit()
    {
        $s = $this->settings;

        return view('admin.integrations', [
            'mail' => [
                'mailer' => $s->get('mail.mailer') ?? config('mail.default'),
                'host' => $s->get('mail.host') ?? config('mail.mailers.smtp.host'),
                'port' => $s->get('mail.port') ?? config('mail.mailers.smtp.port'),
                'encryption' => $s->get('mail.encryption') ?? (config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl' : 'tls'),
                'username' => $s->get('mail.username') ?? config('mail.mailers.smtp.username'),
                'from_address' => $s->get('mail.from_address') ?? config('mail.from.address'),
                'from_name' => $s->get('mail.from_name') ?? config('mail.from.name'),
            ],
            'stripe' => [
                'key' => $s->get('stripe.key') ?? config('services.stripe.key'),
                'mode' => str_starts_with((string) config('services.stripe.secret'), 'sk_live_') || str_starts_with((string) config('services.stripe.secret'), 'rk_live_') ? 'live' : 'test',
            ],
            'masked' => collect(['mail.password', 'stripe.secret', 'stripe.webhook_secret'])->mapWithKeys(fn ($k) => [$k => $s->masked($k)]),
            'sources' => collect(array_keys(IntegrationSettings::FIELDS))->mapWithKeys(fn ($k) => [$k => $s->source($k)]),
            'configured' => [
                'mail.password' => filled(config('mail.mailers.smtp.password')),
                'stripe.secret' => filled(config('services.stripe.secret')),
                'stripe.webhook_secret' => filled(config('services.stripe.webhook_secret')),
            ],
            'webhookUrl' => route('stripe.webhook'),
            'events' => ['checkout.session.completed', 'customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted',
                'customer.subscription.paused', 'customer.subscription.resumed', 'invoice.paid', 'invoice.payment_failed', 'invoice.voided',
                'invoice.marked_uncollectible', 'charge.refunded'],
        ]);
    }

    public function updateMail(Request $request)
    {
        $data = $request->validate([
            'mailer' => ['required', Rule::in(['smtp', 'sendmail', 'log'])],
            'host' => ['nullable', 'required_if:mailer,smtp', 'string', 'max:255'],
            'port' => ['nullable', 'required_if:mailer,smtp', 'integer', 'between:1,65535'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:500'],
            'clear_password' => ['boolean'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:100'],
        ], ['host.required_if' => 'Indiquez le serveur SMTP.', 'port.required_if' => 'Indiquez le port SMTP.']);

        $changed = $this->settings->save(collect($data)->except('clear_password')->mapWithKeys(fn ($v, $k) => ['mail.'.$k => $v === null ? null : (string) $v])->all(),
            $request->boolean('clear_password') ? ['mail.password'] : []);
        Audit::log('admin.mail_settings_updated', null, ['fields' => $changed]);

        return back()->with('success', 'Réglages email enregistrés. Envoyez un email de test pour les vérifier.');
    }

    public function testMail(Request $request)
    {
        $data = $request->validate(['to' => ['required', 'email']]);
        try {
            Mail::to($data['to'])->send(new TestMail(config('app.name')));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Échec de l\'envoi : '.mb_substr($e->getMessage(), 0, 300));
        }
        Audit::log('admin.mail_test_sent', null, ['to' => $data['to'], 'mailer' => config('mail.default')]);

        return back()->with('success', config('mail.default') === 'log'
            ? 'Email généré, mais le mode « journal » ne l\'envoie pas : il est écrit dans storage/logs.'
            : 'Email de test envoyé à '.$data['to'].'. Vérifiez la boîte de réception (et les indésirables).');
    }

    public function updateStripe(Request $request)
    {
        $data = $request->validate([
            'key' => ['nullable', 'string', 'max:255', 'regex:/^pk_(test|live)_/'],
            'secret' => ['nullable', 'string', 'max:255', 'regex:/^(sk|rk)_(test|live)_/'],
            'webhook_secret' => ['nullable', 'string', 'max:255', 'regex:/^whsec_/'],
            'clear' => ['array'], 'clear.*' => [Rule::in(['secret', 'webhook_secret'])],
        ], [
            'key.regex' => 'La clé publique commence par pk_test_ ou pk_live_.',
            'secret.regex' => 'La clé secrète commence par sk_test_, sk_live_ (ou rk_ pour une clé restreinte).',
            'webhook_secret.regex' => 'Le secret de signature du webhook commence par whsec_.',
        ]);
        $key = $data['key'] ?? null;
        $secret = $data['secret'] ?? $this->settings->get('stripe.secret') ?? config('services.stripe.secret');
        if ($key && $secret && str_contains($key, '_live_') !== str_contains($secret, '_live_')) {
            return back()->withErrors(['key' => 'Les clés publique et secrète doivent être du même mode (test ou live).'])->withInput();
        }

        $changed = $this->settings->save(['stripe.key' => $key, 'stripe.secret' => $data['secret'] ?? null, 'stripe.webhook_secret' => $data['webhook_secret'] ?? null],
            array_map(fn ($k) => 'stripe.'.$k, $data['clear'] ?? []));
        Audit::log('admin.stripe_settings_updated', null, ['fields' => $changed]);

        return back()->with('success', 'Réglages Stripe enregistrés. Testez la connexion.');
    }

    public function testStripe(StripeGateway $stripe)
    {
        if (! $stripe->configured()) {
            return back()->with('error', 'Aucune clé secrète Stripe n\'est configurée.');
        }
        try {
            $r = $stripe->testConnection();
        } catch (Throwable $e) {
            return back()->with('error', 'Connexion Stripe refusée : '.mb_substr($e->getMessage(), 0, 300));
        }

        return back()->with('success', 'Connexion Stripe réussie (mode '.($r['livemode'] ? 'production' : 'test').').'
            .(config('services.stripe.webhook_secret') ? '' : ' Pensez à renseigner le secret du webhook.'));
    }
}
