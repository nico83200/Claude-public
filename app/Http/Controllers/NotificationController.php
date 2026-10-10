<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', ['notifications' => $request->user()->notifications()->paginate(30)]);
    }

    public function open(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? null;

        // Uniquement des liens internes.
        return $url && str_starts_with($url, url('/')) ? redirect($url) : redirect()->route('notifications.index');
    }

    public function markAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Notifications marquées comme lues.');
    }
}
