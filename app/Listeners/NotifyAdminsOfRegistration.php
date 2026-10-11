<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Auth\Events\Registered;

/**
 * Nouvelle inscription : les super-administrateurs sont prévenus (application, téléphone, email).
 * Seuls le nom, l'adresse email et le mode d'inscription sont transmis.
 */
class NotifyAdminsOfRegistration
{
    public function handle(Registered $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }
        $via = $user->socialAccounts()->value('provider');
        $total = User::count();
        $admins = User::where('is_super_admin', true)->whereNull('suspended_at')->where('id', '!=', $user->id)->get();
        foreach ($admins as $admin) {
            $admin->notify(new AppNotification('new_user', 'Nouvelle inscription',
                $user->name.' ('.$user->email.') vient de créer un compte'.($via ? ' avec '.ucfirst($via) : '').'. '.$total.' compte'.($total > 1 ? 's' : '').' au total.',
                route('admin.users.show', $user)));
        }
    }
}
