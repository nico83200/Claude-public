<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;

/** Parrainage : lien personnel, QR code, SMS, filleuls et jours offerts. */
class ReferralController extends Controller
{
    public function __construct(private ReferralService $referrals) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $link = $this->referrals->link($user);
        $plan = ReferralService::plan();

        return view('referral.index', [
            'link' => $link,
            'plan' => $plan,
            'days' => ReferralService::DAYS,
            'referrals' => Referral::with('referee:id,name')->where('referrer_id', $user->id)->latest()->get(),
            'banked' => $this->referrals->bankedDays($user),
            'canUseBanked' => ! $this->referrals->hasActiveOffer($user),
            'message' => 'Je suis '.$user->name.' et j\'utilise Jack&Cie pour suivre mes chevaux : soins, séances, agenda, budget… Inscris-toi avec mon lien : '.$link,
        ]);
    }

    /** QR code du lien de parrainage (à faire scanner avec l'appareil photo du téléphone). */
    public function qr(Request $request)
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(480, 1), new SvgImageBackEnd)))->writeString($this->referrals->link($request->user()));

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=86400']);
    }

    public function useBanked(Request $request)
    {
        $license = $this->referrals->useBanked($request->user());
        if (! $license) {
            return back()->with('error', 'Vos jours en réserve s\'utilisent quand aucune offre n\'est active sur votre espace personnel.');
        }

        return back()->with('success', 'C\'est parti : l\'offre '.$license->plan->name.' vous est offerte jusqu\'au '.$license->ends_at->format('d/m/Y').'.');
    }

    /** Lien partagé par un parrain : mémorisé 30 jours, puis direction l'inscription. */
    public function join(Request $request, string $code)
    {
        $code = ReferralService::normalize($code);
        $referrer = $code ? User::where('referral_code', $code)->whereNull('suspended_at')->first() : null;
        if (! $referrer) {
            return redirect('/register');
        }
        if ($request->user()) {
            return redirect()->route('dashboard');
        }
        $request->session()->put('referral_code', $code);
        $request->session()->put('referral_name', strtok($referrer->name, ' '));

        return redirect('/register?src=parrainage')->withCookie(cookie(ReferralService::COOKIE, $code, 60 * 24 * 30));
    }
}
