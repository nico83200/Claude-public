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

    public function logout(): void
    {
        Auth::logout();
        redirect('admin/connexion');
    }
}
