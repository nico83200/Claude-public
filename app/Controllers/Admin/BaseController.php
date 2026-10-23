<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;

abstract class BaseController
{
    protected array $admin;

    public function __construct()
    {
        header('X-Robots-Tag: noindex, nofollow');
        $this->admin = Auth::requireAdmin();
    }

    protected function render(string $template, array $data = []): void
    {
        view('admin/' . $template, $data + ['admin' => $this->admin], 'layouts/admin');
    }
}
