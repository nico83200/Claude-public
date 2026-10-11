<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssistantRequest;
use App\Models\AuditLog;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\HorseDocument;
use App\Models\HorseMeasurement;
use App\Models\License;
use App\Models\RidingSession;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Upsell;
use Illuminate\Http\Request;

/**
 * Statistiques d'usage (super-administrateur) : inscriptions, activité, conversions Découverte → payant,
 * usage des fonctions, propositions d'offre cliquées, résiliations et abandons.
 * Uniquement des nombres agrégés : aucune donnée personnelle n'est affichée.
 */
class StatsController extends Controller
{
    public const PERIODS = [30 => '30 jours', 90 => '3 mois', 365 => '12 mois'];

    public function __invoke(Request $request)
    {
        $days = array_key_exists($request->integer('jours'), self::PERIODS) ? $request->integer('jours') : 30;
        $from = now()->subDays($days)->startOfDay();
        $users = User::query()->whereNull('deleted_at');

        // Inscriptions par semaine (≤ 90 jours) ou par mois.
        $byMonth = $days > 90;
        $signups = (clone $users)->where('created_at', '>=', $from)->get(['created_at'])
            ->groupBy(fn ($u) => $byMonth ? $u->created_at->format('Y-m') : $u->created_at->startOfWeek()->format('Y-m-d'))->map->count();
        $buckets = [];
        $cursor = $byMonth ? $from->copy()->startOfMonth() : $from->copy()->startOfWeek();
        while ($cursor->lte(now())) {
            $key = $byMonth ? $cursor->format('Y-m') : $cursor->format('Y-m-d');
            $buckets[] = ['label' => $byMonth ? ucfirst($cursor->translatedFormat('M')) : $cursor->format('d/m'), 'full' => $byMonth ? ucfirst($cursor->translatedFormat('F Y')) : 'Semaine du '.$cursor->translatedFormat('j F'), 'value' => $signups[$key] ?? 0];
            $byMonth ? $cursor->addMonth() : $cursor->addWeek();
        }

        $newUsers = (clone $users)->where('created_at', '>=', $from)->pluck('id');
        $withHorse = Horse::whereIn('created_by', $newUsers)->distinct()->count('created_by');
        $activeNew = (clone $users)->whereIn('id', $newUsers)->where('last_seen_at', '>=', now()->subDays(7))->count();
        $paidLicenses = License::where('source', 'stripe')->where('created_at', '>=', $from);
        $trials = Subscription::where('created_at', '>=', $from)->whereNotNull('trial_ends_at')->get();
        $trialsEnded = $trials->filter(fn ($s) => $s->trial_ends_at && $s->trial_ends_at->isPast());
        $converted = $trialsEnded->filter(fn ($s) => in_array($s->stripe_status, ['active', 'past_due'], true))->count();

        $usage = [
            'Séances et cours' => RidingSession::where('created_at', '>=', $from)->count(),
            'Soins' => CareRecord::where('created_at', '>=', $from)->count(),
            'Dépenses' => Expense::where('created_at', '>=', $from)->count(),
            'Consignes à l\'assistant' => AssistantRequest::where('created_at', '>=', $from)->where('input_mode', '!=', 'document')->count(),
            'Factures lues' => AssistantRequest::where('created_at', '>=', $from)->where('input_mode', 'document')->count(),
            'Mesures de poids' => HorseMeasurement::where('created_at', '>=', $from)->count(),
            'Documents' => HorseDocument::where('created_at', '>=', $from)->count(),
            'Partages' => HorseAccessGrant::where('created_at', '>=', $from)->count(),
        ];
        arsort($usage);

        $upsellClicks = AuditLog::where('action', 'upsell.clicked')->where('created_at', '>=', $from)->get(['properties'])
            ->countBy(fn ($l) => $l->properties['key'] ?? '?')->sortDesc()
            ->mapWithKeys(fn ($n, $k) => [Upsell::PITCHES[$k][0] ?? $k => $n]);
        $checkouts = AuditLog::where('action', 'billing.checkout_started')->where('created_at', '>=', $from)->count();
        $subscribed = Subscription::where('created_at', '>=', $from)->count();

        return view('admin.stats', [
            'days' => $days,
            'periods' => self::PERIODS,
            'kpis' => [
                'users' => (clone $users)->count(),
                'new' => $newUsers->count(),
                'active7' => (clone $users)->where('last_seen_at', '>=', now()->subDays(7))->count(),
                'active30' => (clone $users)->where('last_seen_at', '>=', now()->subDays(30))->count(),
                'paying' => Subscription::whereIn('stripe_status', ['active', 'past_due'])->count(),
                'trialing' => Subscription::where('stripe_status', 'trialing')->count(),
            ],
            'buckets' => $buckets,
            'byMonth' => $byMonth,
            'funnel' => [
                ['Comptes créés', $newUsers->count()],
                ['Ont ajouté un cheval', $withHorse],
                ['Actifs cette semaine', $activeNew],
                ['Ont démarré un abonnement', $paidLicenses->distinct()->count('organization_id')],
            ],
            'trials' => ['started' => $trials->count(), 'ended' => $trialsEnded->count(), 'converted' => $converted],
            'usage' => $usage,
            'upsellClicks' => $upsellClicks,
            'cancellations' => Subscription::whereNotNull('canceled_at')->where('canceled_at', '>=', $from)->count(),
            'scheduled' => Subscription::where('cancel_at_period_end', true)->whereIn('stripe_status', ['active', 'trialing'])->count(),
            'checkouts' => ['started' => $checkouts, 'completed' => $subscribed],
            'dormant' => (clone $users)->where('created_at', '<=', now()->subDays(30))->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays(30)))->count(),
            'since' => $from,
        ]);
    }

    public static function pct(int $part, int $total): string
    {
        return $total > 0 ? number_format($part / $total * 100, 0, ',', ' ').' %' : '—';
    }
}
