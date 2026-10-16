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

        // Uniquement des pages de l'application. Le lien enregistré peut porter un autre nom de domaine
        // (ancienne adresse de l'application) : seul le chemin compte, s'il correspond à une page existante.
        $target = $url ? $this->internalPath($url) : null;

        return $target ? redirect($target) : redirect()->route('notifications.index')->with('warning', 'Ce rappel n\'est plus disponible.');
    }

    private function internalPath(string $url): ?string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        if ($parts === false || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }
        $target = $path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
        try {
            app('router')->getRoutes()->match(Request::create($path, 'GET'));
        } catch (\Throwable) {
            return null;
        }

        return $target;
    }

    public function markAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Notifications marquées comme lues.');
    }
}
