<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;

final class AuthController
{
    public function loginForm(): void
    {
        if (Auth::admin()) {
            redirect('admin');
        }
        header('X-Robots-Tag: noindex, nofollow');
        view('admin/login', ['pageTitle' => 'Connexion'], null);
    }

    public function login(): void
    {
        $ip = client_ip();
        if (Auth::isLocked($ip)) {
            flash('error', 'Trop de tentatives. Réessayez dans 15 minutes.');
            redirect('admin/connexion');
        }
        if (!Auth::attempt((string) input('email'), (string) input('password'), $ip)) {
            usleep(400000);
            flash('error', 'Identifiants incorrects.');
            redirect('admin/connexion');
        }
        redirect('admin');
    }

    public function forgotForm(): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        view('admin/login', ['mode' => 'forgot'], null);
    }

    public function forgot(): void
    {
        $ip = client_ip();
        $email = (string) input('email');
        if (!Auth::isLocked($ip) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Auth::sendReset($email, $ip);
        }
        flash('success', 'Si cette adresse correspond à un compte administrateur, un lien de réinitialisation vient d\'être envoyé (valable 1 heure). Pensez à vérifier les indésirables.');
        redirect('admin/mot-de-passe-oublie');
    }

    public function resetForm(string $token): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        if (!Auth::resetAdmin($token)) {
            flash('error', 'Ce lien a expiré ou a déjà servi. Demandez-en un nouveau.');
            redirect('admin/mot-de-passe-oublie');
        }
        view('admin/login', ['mode' => 'reset', 'token' => $token], null);
    }

    public function reset(string $token): void
    {
        if (input('password') !== input('password_confirm')) {
            flash('error', 'Les deux mots de passe ne correspondent pas.');
            redirect('admin/nouveau-mot-de-passe/' . $token);
        }
        try {
            Auth::resetPassword($token, (string) input('password'));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect(str_contains($e->getMessage(), 'expiré') ? 'admin/mot-de-passe-oublie' : 'admin/nouveau-mot-de-passe/' . $token);
        }
        flash('success', 'Mot de passe modifié : vous êtes connectée.');
        redirect('admin');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('admin/connexion');
    }
}
