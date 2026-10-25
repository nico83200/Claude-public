<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\Mailer;
use App\Services\Newsletter;

final class PageController
{
    public function show(string $slug): void
    {
        $page = Database::one('SELECT * FROM pages WHERE slug = ?', [$slug]) ?? abort(404);
        view('shop/page', [
            'pageTitle'       => $page['title'],
            'metaDescription' => $page['meta_description'] ?: excerpt($page['content']),
            'canonical'       => url('page/' . $page['slug']),
            'page'            => $page,
            'html'            => self::placeholders(rich_text((string) $page['content'])),
        ]);
    }

    /** Remplace {{shop_name}}, {{company_siret}}… par les réglages de la boutique. */
    public static function placeholders(string $html): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static function (array $m): string {
            return match ($m[1]) {
                'site_url' => e(base_url()),
                'date'     => date('d/m/Y'),
                default    => e((string) setting($m[1], '')),
            };
        }, $html);
    }

    public function contact(): void
    {
        view('shop/contact', ['pageTitle' => 'Contact', 'metaDescription' => 'Une question sur un article ou une commande ? Écris-nous.', 'canonical' => url('contact')]);
    }

    public function sendContact(): void
    {
        $data = [
            'name'         => mb_substr((string) input('name'), 0, 190),
            'email'        => mb_substr((string) input('email'), 0, 190),
            'subject'      => mb_substr((string) input('subject'), 0, 190),
            'order_number' => mb_substr((string) input('order_number'), 0, 40),
            'message'      => mb_substr((string) input('message'), 0, 5000),
        ];
        remember_input($data);

        // Anti-spam : champ piège + délai minimum + limite par session.
        if ((string) input('website') !== '' || (time() - (int) ($_SESSION['contact_last'] ?? 0)) < 60) {
            flash('error', 'Patiente un peu avant d\'envoyer un nouveau message.');
            redirect('contact');
        }
        if ($data['name'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['message']) < 10) {
            flash('error', 'Indique ton nom, un e-mail valide et un message (10 caractères minimum).');
            redirect('contact');
        }
        if (input('consent') !== '1') {
            flash('error', 'Accepte le traitement de tes données pour que nous puissions te répondre.');
            redirect('contact');
        }

        Database::insert('contact_messages', $data + ['created_at' => now(), 'is_read' => 0]);
        Mailer::notifyAdmin('Nouveau message : ' . ($data['subject'] ?: 'contact'), 'admin_contact', ['data' => $data]);
        $_SESSION['contact_last'] = time();
        remember_input([]);
        flash('success', 'Merci ! Ton message a bien été envoyé, on te répond sous 48 h ouvrées.');
        redirect('contact');
    }

    public function newsletter(): void
    {
        $ajax = input('ajax') === '1';
        if ((string) input('website') !== '') {
            $ajax ? json_response(['ok' => true]) : back('/');
        }
        $email = mb_strtolower(trim((string) input('email')));
        $isNew = !Newsletter::exists($email);
        if (input('consent') !== '1' || !Newsletter::subscribe($email, in_array(input('source'), ['popup', 'footer'], true) ? input('source') : 'footer')) {
            $error = 'Merci d\'indiquer une adresse e-mail valide et d\'accepter de recevoir notre newsletter.';
            $ajax ? json_response(['ok' => false, 'message' => $error], 422) : flash('error', $error);
            back('/');
        }

        // Code personnel à usage unique (créé dans Stripe), envoyé par e-mail à la première inscription
        $code = \App\Services\WelcomeOffer::active() ? \App\Services\WelcomeOffer::codeFor($email) : null;
        $label = \App\Services\WelcomeOffer::label();
        if ($isNew && $code) {
            Mailer::sendTemplate($email, 'Ton cadeau de bienvenue 🎁', 'welcome', ['code' => $code, 'discount' => $label]);
        }
        $message = $code
            ? 'Bienvenue au club ! Ton code ' . $label . ' : ' . $code . ' (envoyé aussi par e-mail, valable une fois).'
            : 'Bienvenue au club ! Tu recevras bientôt nos nouveautés.';
        if ($ajax) {
            json_response(['ok' => true, 'message' => $message, 'code' => $code]);
        }
        flash('success', $message);
        back('/');
    }
}
