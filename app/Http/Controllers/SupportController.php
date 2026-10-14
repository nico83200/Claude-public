<?php

namespace App\Http\Controllers;

use App\Mail\SupportMessage;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

/** Assistance : formulaire de contact envoyé par e-mail à l'équipe Jack&Cie. */
class SupportController extends Controller
{
    public const TOPICS = [
        'question' => 'Une question sur l\'application',
        'bug' => 'Un problème ou un bug',
        'idea' => 'Une suggestion d\'amélioration',
        'billing' => 'Abonnement et facturation',
        'account' => 'Mon compte et mes données',
        'other' => 'Autre',
    ];

    public function create(Request $request)
    {
        return view('support.create', ['topics' => self::TOPICS, 'email' => config('equine.support_email')]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'topic' => ['required', Rule::in(array_keys(self::TOPICS))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'page' => ['nullable', 'string', 'max:300'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf'],
        ], ['message.min' => 'Décrivez votre demande en quelques mots (10 caractères au moins).', 'attachment.mimes' => 'Joignez une capture d\'écran (image) ou un PDF.']);

        $user = $request->user();
        $org = $this->currentOrganization();
        $ent = $this->entitlements()->for($org);
        $context = array_filter([
            'Compte' => '#'.$user->id.' — '.$user->email,
            'Espace' => $org->name.' (#'.$org->id.')',
            'Offre' => ($ent['plan']?->name ?? 'aucune').' — '.($ent['source'] ?? '—'),
            'Page' => $data['page'] ?? null,
            'Version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'Appareil' => mb_substr((string) $request->userAgent(), 0, 250),
            'Date' => now()->format('d/m/Y H:i'),
        ]);
        $file = null;
        if ($upload = $request->file('attachment')) {
            $file = ['data' => file_get_contents($upload->getRealPath()), 'name' => 'capture.'.($upload->guessExtension() ?: 'jpg'), 'mime' => (string) $upload->getMimeType()];
        }

        try {
            Mail::to(config('equine.support_email'))->send(new SupportMessage($user, self::TOPICS[$data['topic']], $data['message'], $context, $file));
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Le message n\'a pas pu partir. Réessayez dans un instant, ou écrivez directement à '.config('equine.support_email').'.');
        }
        Audit::log('support.message_sent', null, ['topic' => $data['topic']], $org->id);

        return redirect()->route('support.create')->with('success', 'Merci, votre message est bien parti ! Nous vous répondons par e-mail à '.$user->email.', en général sous 48 h ouvrées.');
    }
}
