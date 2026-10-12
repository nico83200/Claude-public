<?php

namespace App\Services;

use App\Models\AcquisitionVisit;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provenance des inscriptions : un lien ou un QR code porte ?src=affiche (ou instagram…) et
 * éventuellement ?code=. Chaque arrivée est comptée (une ligne par visiteur, source et code,
 * avec le nombre d'ouvertures), puis rattachée au compte créé et au code activé.
 * Le visiteur est un condensat d'un jeton aléatoire de session : rien ne permet d'identifier une personne.
 */
class AcquisitionTracker
{
    public const SOURCES = ['affiche' => 'Affiches (QR code)', 'instagram' => 'Instagram (lien en bio)', 'instagram-story' => 'Instagram (story)', 'lien' => 'Lien avec code'];

    public static function label(string $source): string
    {
        return self::SOURCES[$source] ?? ucfirst(str_replace('-', ' ', $source));
    }

    public static function normalizeSource(?string $source): string
    {
        return mb_substr(trim((string) preg_replace('/[^a-z0-9-]+/', '-', mb_strtolower((string) $source)), '-'), 0, 40);
    }

    public function track(Request $request): ?AcquisitionVisit
    {
        $source = self::normalizeSource($request->query('src') ?? $request->query('utm_source'));
        $code = mb_substr(PromoCode::normalize((string) $request->query('code')), 0, 40) ?: null;
        if ($source === '' && ! $code) {
            return null;
        }
        $source = $source ?: 'lien';
        // Jeton aléatoire propre à la session (survit à la connexion), condensé avant enregistrement.
        $token = $request->session()->get('acquisition_token') ?? tap(Str::random(40), fn ($t) => $request->session()->put('acquisition_token', $t));
        $visitor = hash_hmac('sha256', $token, (string) config('app.key'));
        $visit = AcquisitionVisit::firstOrCreate(['visitor' => $visitor, 'source' => $source, 'code' => $code]);
        if (! $visit->wasRecentlyCreated) {
            $visit->increment('hits');
        }
        $request->session()->put('acquisition_visit', $visit->id);

        return $visit;
    }

    /** Compte créé depuis un lien suivi (inscription par email, Google ou Apple). */
    public function registered(User $user, bool $codeRedeemed): void
    {
        $id = session('acquisition_visit');
        if (! $id) {
            return;
        }
        AcquisitionVisit::whereKey($id)->whereNull('user_id')->update([
            'user_id' => $user->id, 'registered_at' => now(), 'code_redeemed_at' => $codeRedeemed ? now() : null,
        ]);
        session()->forget('acquisition_visit');
    }

    /**
     * Tableau par source (et code) : ouvertures, visiteurs, comptes créés, codes activés.
     *
     * @return Collection<int, object>
     */
    public function report(?Carbon $from = null, ?string $code = null): Collection
    {
        return AcquisitionVisit::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($code, fn ($q) => $q->where('code', $code))
            ->groupBy('source', 'code')
            ->orderByDesc(DB::raw('sum(hits)'))
            ->get(['source', 'code', DB::raw('sum(hits) as hits'), DB::raw('count(*) as visitors'),
                DB::raw('count(registered_at) as accounts'), DB::raw('count(code_redeemed_at) as redeemed')])
            ->map(fn ($r) => (object) ['source' => $r->source, 'label' => self::label($r->source), 'code' => $r->code,
                'hits' => (int) $r->hits, 'visitors' => (int) $r->visitors, 'accounts' => (int) $r->accounts, 'redeemed' => (int) $r->redeemed]);
    }
}
