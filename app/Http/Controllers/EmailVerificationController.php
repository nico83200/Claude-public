<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

/**
 * Confirmation d'adresse email par lien signé, sans connexion préalable :
 * sur téléphone, le lien s'ouvre dans le navigateur alors que la session
 * est ouverte dans l'application installée.
 */
class EmailVerificationController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash)
    {
        $user = User::find($id);
        abort_unless($user && hash_equals(sha1($user->getEmailForVerification()), $hash), 403, 'Lien de confirmation invalide.');
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }
        if ($request->user()?->is($user)) {
            return redirect()->route('dashboard')->with('success', 'Adresse email confirmée.');
        }

        return view('auth.email-verified');
    }
}
