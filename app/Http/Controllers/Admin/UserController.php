<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::withTrashed()->with('memberships.organization')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->when($request->query('filter') === 'suspended', fn ($q) => $q->whereNotNull('suspended_at'))
            ->when($request->query('filter') === 'admins', fn ($q) => $q->where('is_super_admin', true))
            ->when($request->query('filter') === 'unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->when($request->query('filter') === 'deletion', fn ($q) => $q->whereNotNull('deletion_requested_at'))
            ->latest()->paginate(40)->withQueryString();

        return view('admin.users.index', ['users' => $users]);
    }

    public function show(User $user)
    {
        Audit::log('admin.user_viewed', $user);

        return view('admin.users.show', [
            'user' => $user->load(['memberships.organization', 'memberships.role', 'profile']),
            'logs' => AuditLog::where('user_id', $user->id)->latest('created_at')->limit(30)->get(),
        ]);
    }

    public function suspend(Request $request, User $user)
    {
        abort_if($user->is_super_admin, 422, 'Un super-administrateur ne peut pas être suspendu depuis l\'interface.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $user->forceFill(['suspended_at' => now(), 'suspension_reason' => $data['reason']])->save();
        // Les sessions actives sont invalidées immédiatement.
        DB::table('sessions')->where('user_id', $user->id)->delete();
        Audit::log('admin.user_suspended', $user, ['reason' => $data['reason']]);

        return back()->with('success', 'Compte suspendu et sessions fermées.');
    }

    public function reactivate(User $user)
    {
        $user->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();
        Audit::log('admin.user_reactivated', $user);

        return back()->with('success', 'Compte réactivé.');
    }
}
