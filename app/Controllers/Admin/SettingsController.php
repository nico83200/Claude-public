<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Settings;
use App\Services\Cron;
use App\Services\Mailer;
use App\Services\Printful;
use App\Services\Stripe;

final class SettingsController extends BaseController
{
    private const MONEY_KEYS = ['shipping_standard_price', 'shipping_free_threshold', 'shipping_express_price'];

    public function index(): void
    {
        $this->render('settings', ['pageTitle' => 'Réglages', 'settings' => Settings::all()]);
    }

    public function update(): void
    {
        foreach (array_keys(Settings::DEFAULTS) as $key) {
            if (!array_key_exists($key, $_POST)) {
                continue;
            }
            $value = trim((string) $_POST[$key]);
            if (in_array($key, self::MONEY_KEYS, true)) {
                $value = (string) to_cents($value);
            }
            Settings::set($key, mb_substr($value, 0, 2000));
        }
        flash('success', 'Réglages enregistrés.');
        redirect('admin/reglages');
    }

    public function password(): void
    {
        $row = Database::one('SELECT password_hash FROM admins WHERE id = ?', [$this->admin['id']]);
        if (!password_verify((string) input('current'), $row['password_hash'])) {
            flash('error', 'Mot de passe actuel incorrect.');
        } elseif (mb_strlen((string) input('new')) < 10) {
            flash('error', 'Le nouveau mot de passe doit contenir au moins 10 caractères.');
        } else {
            Database::update('admins', ['password_hash' => password_hash((string) input('new'), PASSWORD_DEFAULT)], 'id = :id', ['id' => $this->admin['id']]);
            flash('success', 'Mot de passe modifié.');
        }
        redirect('admin/reglages');
    }

    public function integrations(): void
    {
        $this->render('integrations', [
            'pageTitle'   => 'Intégrations',
            'checklist'   => DashboardController::checklist(),
            'cronLast'    => @file_get_contents(STORAGE_PATH . '/cache/cron-last-run') ?: null,
            'stripeHook'  => url('webhooks/stripe'),
            'printfulHook' => config('printful.webhook_token') ? url('webhooks/printful/' . config('printful.webhook_token')) : null,
            'cronUrl'     => config('cron.token') ? url('tache-planifiee/' . config('cron.token')) : null,
        ]);
    }

    public function integrationAction(string $action): void
    {
        try {
            switch ($action) {
                case 'test-stripe':
                    $balance = Stripe::fromConfig()->request('GET', 'balance');
                    $mode = str_starts_with((string) config('stripe.secret_key'), 'sk_live') ? 'production' : 'test';
                    flash('success', "Connexion Stripe OK (mode $mode, livemode=" . json_encode($balance['livemode'] ?? null) . ').');
                    break;
                case 'test-printful':
                    $stores = Printful::fromConfig()->stores();
                    flash('success', 'Connexion Printful OK : ' . implode(', ', array_map(static fn ($s) => $s['name'] . ' (#' . $s['id'] . ')', $stores)));
                    break;
                case 'printful-webhook':
                    if (!config('printful.webhook_token')) {
                        throw new \RuntimeException('Définissez printful.webhook_token dans config/config.php.');
                    }
                    Printful::fromConfig()->setWebhooks(url('webhooks/printful/' . config('printful.webhook_token')));
                    flash('success', 'Webhook Printful enregistré : expéditions, échecs et annulations seront reçus automatiquement.');
                    break;
                case 'test-mail':
                    $ok = Mailer::sendTemplate($this->admin['email'], 'Test d\'envoi — ' . setting('shop_name'), 'test');
                    $ok ? flash('success', 'E-mail de test envoyé à ' . $this->admin['email'] . '.') : flash('error', 'Échec de l\'envoi : consultez storage/logs/mail-*.log.');
                    break;
                case 'run-cron':
                    flash('success', implode(' · ', Cron::run()));
                    break;
                default:
                    abort(404);
            }
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/integrations');
    }
}
