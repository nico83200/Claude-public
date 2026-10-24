<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\Account;
use App\Services\OrderService;

final class AccountController
{
    public function index(): void
    {
        $customer = $this->requireCustomer();
        header('X-Robots-Tag: noindex, nofollow');
        view('shop/account/index', [
            'pageTitle' => 'Mon compte',
            'noindex'   => true,
            'customer'  => $customer,
            'orders'    => Account::orders($customer),
        ]);
    }

    public function loginForm(): void
    {
        if (Account::current()) {
            redirect($this->returnPath());
        }
        view('shop/account/login', [
            'pageTitle' => 'Mon compte',
            'noindex'   => true,
            'return'    => $this->returnPath(''),
            'tab'       => input('inscription') !== null ? 'register' : 'login',
        ]);
    }

    public function login(): void
    {
        $ip = client_ip();
        $email = Account::normalize((string) input('email'));
        $back = $this->loginUrl();
        if (Account::isLocked($ip)) {
            flash('error', 'Trop de tentatives. Réessayez dans 15 minutes, ou utilisez « Mot de passe oublié ».');
            redirect($back);
        }
        $customer = Account::attempt($email, (string) input('password'), $ip);
        if (!$customer) {
            usleep(400000);
            remember_input(['email' => $email]);
            flash('error', 'Adresse e-mail ou mot de passe incorrect.');
            redirect($back);
        }
        Account::login($customer);
        flash('success', 'Ravie de vous revoir, ' . Account::firstName($customer) . ' ♡');
        redirect($this->returnPath());
    }

    public function register(): void
    {
        $email = Account::normalize((string) input('email'));
        $name = trim((string) input('name'));
        if (input('password') !== input('password_confirm')) {
            remember_input(['email' => $email, 'name' => $name]);
            flash('error', 'Les deux mots de passe ne correspondent pas.');
            redirect($this->loginUrl(true));
        }
        try {
            $customer = Account::register($email, (string) input('password'), $name);
        } catch (\RuntimeException $e) {
            remember_input(['email' => $email, 'name' => $name]);
            flash('error', $e->getMessage());
            redirect($this->loginUrl(true));
        }
        Account::login($customer);
        flash('success', 'Bienvenue au club, ' . Account::firstName($customer) . ' ! Un e-mail de confirmation vient de vous être envoyé.');
        redirect($this->returnPath());
    }

    /** Création du compte juste après le paiement : l'adresse est celle utilisée chez Stripe. */
    public function fromOrder(): void
    {
        $order = OrderService::find((int) input('order_id'));
        if (!$order || (int) ($_SESSION['last_order_id'] ?? 0) !== (int) $order['id'] || $order['status'] === 'pending' || !$order['email']) {
            flash('error', 'Cette commande ne permet pas de créer un compte. Inscrivez-vous depuis « Mon compte ».');
            redirect('compte/connexion?inscription=1');
        }
        if (input('password') !== input('password_confirm')) {
            flash('error', 'Les deux mots de passe ne correspondent pas.');
            redirect('commande/merci?session_id=' . rawurlencode((string) $order['stripe_session_id']) . '#compte');
        }
        try {
            $customer = Account::register($order['email'], (string) input('password'), (string) $order['customer_name'], true);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('commande/merci?session_id=' . rawurlencode((string) $order['stripe_session_id']) . '#compte');
        }
        Account::login($customer);
        unset($_SESSION['want_account']);
        flash('success', 'Votre compte est créé : retrouvez ici le suivi de votre commande ♡');
        redirect('compte');
    }

    public function logout(): void
    {
        Account::logout();
        flash('success', 'Vous êtes déconnectée. À très vite !');
        redirect('/');
    }

    public function verify(string $token): void
    {
        $customer = Account::verify($token);
        if (!$customer) {
            flash('error', 'Ce lien de confirmation a expiré. Connectez-vous pour en recevoir un nouveau.');
            redirect('compte/connexion');
        }
        Account::login($customer);
        flash('success', 'Adresse confirmée : vos commandes passées avec cette adresse sont maintenant dans votre compte.');
        redirect('compte');
    }

    public function resendVerification(): void
    {
        $customer = $this->requireCustomer();
        if (!$customer['email_verified_at']) {
            Account::sendVerification($customer);
        }
        flash('success', 'Un nouveau lien de confirmation a été envoyé à ' . $customer['email'] . '.');
        redirect('compte');
    }

    public function forgotForm(): void
    {
        view('shop/account/forgot', ['pageTitle' => 'Mot de passe oublié', 'noindex' => true]);
    }

    public function forgot(): void
    {
        $email = Account::normalize((string) input('email'));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) && !Account::isLocked(client_ip())) {
            // Compte comme une tentative : limite les envois en rafale
            Database::insert('login_attempts', ['ip' => 'c:' . client_ip(), 'created_at' => now()]);
            Account::sendReset($email);
        }
        flash('success', 'Si un compte existe pour cette adresse, vous allez recevoir un lien pour choisir un nouveau mot de passe (valable 1 heure).');
        redirect('compte/mot-de-passe-oublie');
    }

    public function resetForm(string $token): void
    {
        if (!Account::resetCustomer($token)) {
            flash('error', 'Ce lien a expiré. Demandez-en un nouveau.');
            redirect('compte/mot-de-passe-oublie');
        }
        view('shop/account/reset', ['pageTitle' => 'Nouveau mot de passe', 'noindex' => true, 'token' => $token]);
    }

    public function reset(string $token): void
    {
        if (input('password') !== input('password_confirm')) {
            flash('error', 'Les deux mots de passe ne correspondent pas.');
            redirect('compte/nouveau-mot-de-passe/' . $token);
        }
        try {
            $customer = Account::resetPassword($token, (string) input('password'));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect(str_contains($e->getMessage(), 'expiré') ? 'compte/mot-de-passe-oublie' : 'compte/nouveau-mot-de-passe/' . $token);
        }
        Account::login($customer);
        flash('success', 'Mot de passe modifié. Vous êtes connectée.');
        redirect('compte');
    }

    public function updateProfile(): void
    {
        $customer = $this->requireCustomer();
        Database::update('customers', ['name' => mb_substr(trim((string) input('name')), 0, 120)], 'id = :id', ['id' => $customer['id']]);
        flash('success', 'Informations enregistrées.');
        redirect('compte#infos');
    }

    public function changePassword(): void
    {
        $customer = $this->requireCustomer();
        if (!password_verify((string) input('current_password'), $customer['password_hash'])) {
            flash('error', 'Mot de passe actuel incorrect.');
            redirect('compte#infos');
        }
        $password = (string) input('password');
        if ($error = Account::validatePassword($password)) {
            flash('error', $error);
            redirect('compte#infos');
        }
        if ($password !== input('password_confirm')) {
            flash('error', 'Les deux mots de passe ne correspondent pas.');
            redirect('compte#infos');
        }
        Database::update('customers', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $customer['id']]);
        session_regenerate_id(true);
        flash('success', 'Mot de passe modifié.');
        redirect('compte#infos');
    }

    public function destroy(): void
    {
        $customer = $this->requireCustomer();
        if (!password_verify((string) input('current_password'), $customer['password_hash'])) {
            flash('error', 'Mot de passe incorrect : le compte n\'a pas été supprimé.');
            redirect('compte#infos');
        }
        Account::delete($customer);
        Account::logout();
        flash('success', 'Votre compte a été supprimé. Vos commandes restent consultables avec le lien reçu par e-mail.');
        redirect('/');
    }

    private function requireCustomer(): array
    {
        $customer = Account::current();
        if (!$customer) {
            redirect('compte/connexion?retour=' . rawurlencode(ltrim(request_path(), '/')));
        }
        return $customer;
    }

    /** Page où revenir après connexion : chemin interne uniquement. */
    private function returnPath(string $default = 'compte'): string
    {
        $return = (string) input('retour', '');
        return preg_match('#^[a-z0-9][a-z0-9/_-]{0,80}$#', $return) ? $return : $default;
    }

    private function loginUrl(bool $register = false): string
    {
        $query = array_filter(['inscription' => $register ? 1 : null, 'retour' => $this->returnPath('') ?: null]);
        return 'compte/connexion' . ($query ? '?' . http_build_query($query) : '');
    }
}
