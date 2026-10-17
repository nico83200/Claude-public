<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Models\CalendarEvent;
use App\Models\DigestIntro;
use App\Models\Expense;
use App\Models\RidingSession;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\ClaudeGateway;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Récapitulatif de la semaine à venir : agenda (rendez-vous, soins prévus, séances),
 * traitements en cours, paiements à prévoir, bilans de séance en attente et budget du mois.
 * Ne reprend que ce que l'utilisateur a le droit de voir.
 */
class WeeklyDigest
{
    public function __construct(private HorseAccess $access, private CalendarService $calendar) {}

    /**
     * @return array{from: Carbon, to: Carbon, events: Collection, treatments: Collection,
     *               payments: Collection, debriefs: Collection, month_total: float, month_count: int}
     */
    public function for(User $user, ?Carbon $now = null): array
    {
        $now ??= now();
        // Le dimanche soir, « la semaine » est celle qui commence le lendemain.
        $from = $now->copy()->addDay()->startOfDay();
        $to = $from->copy()->addDays(6)->endOfDay();

        $calendarIds = $this->access->horseIdsWith($user, Perm::CALENDAR_VIEW);
        $orgIds = $user->memberships->pluck('organization_id');
        $events = CalendarEvent::with('horse')
            ->where(fn ($q) => $q->whereIn('horse_id', $calendarIds ?: [0])->orWhere(fn ($w) => $w->whereNull('horse_id')->whereIn('organization_id', $orgIds)))
            ->visibleSessions($this->access->horseIdsWith($user, Perm::SESSIONS_VIEW))
            ->whereNotIn('status', ['cancelled', 'done'])
            ->where(fn ($q) => $q->whereBetween('starts_at', [$from, $to])
                ->orWhere(fn ($r) => $r->whereNotNull('recurrence')->where('starts_at', '<=', $to)->where(fn ($u) => $u->whereNull('recurrence_until')->orWhere('recurrence_until', '>=', $from->toDateString()))))
            ->get();
        $events = $this->calendar->expand($events, $from, $to)->filter(fn ($o) => ! $o['event']->horse?->archived_at)->values();

        $healthIds = $this->access->horseIdsWith($user, Perm::HEALTH_VIEW);
        $treatments = Treatment::with('horse')->whereIn('horse_id', $healthIds ?: [0])->where('status', 'active')
            ->where('starts_on', '<=', $to->toDateString())->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $from->toDateString()))
            ->orderBy('ends_on')->get()->filter(fn ($t) => $t->horse && ! $t->horse->archived_at)->values();

        $expenseHorses = $this->access->horseIdsWith($user, Perm::EXPENSES_VIEW);
        $orgView = $user->memberships->filter(fn ($m) => in_array(Perm::EXPENSES_ORG_VIEW, $m->role?->permissionKeys() ?? [], true))->pluck('organization_id');
        $expenses = Expense::query()->where(fn ($q) => $q->whereIn('horse_id', $expenseHorses ?: [0])->orWhereIn('organization_id', $orgView));
        $payments = (clone $expenses)->with(['horse', 'category'])->whereNull('paid_on')->where('spent_on', '<=', $to->toDateString())->orderBy('spent_on')->limit(20)->get();
        $month = (clone $expenses)->whereBetween('spent_on', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()]);

        $debriefs = RidingSession::with('horse')->whereIn('status', ['planned', 'in_progress'])
            ->where(fn ($q) => $q->where('rider_id', $user->id)->orWhere(fn ($w) => $w->whereNull('rider_id')->where('created_by', $user->id)))
            ->whereBetween('scheduled_at', [$now->copy()->subDays(14), $now])->orderBy('scheduled_at')->get()
            ->filter(fn ($s) => $s->horse && ! $s->horse->archived_at)->values();

        return [
            'from' => $from, 'to' => $to,
            'events' => $events, 'treatments' => $treatments, 'payments' => $payments, 'debriefs' => $debriefs,
            'month_total' => (float) $month->sum('amount'), 'month_count' => (clone $month)->count(),
        ];
    }

    public function isEmpty(array $digest): bool
    {
        return $digest['events']->isEmpty() && $digest['treatments']->isEmpty() && $digest['payments']->isEmpty() && $digest['debriefs']->isEmpty();
    }

    /** Résumé court (notification sur le téléphone). */
    public function summary(array $d): string
    {
        $parts = array_filter([
            $d['events']->count() ? $d['events']->count().' rendez-vous ou activité'.($d['events']->count() > 1 ? 's' : '') : null,
            $d['treatments']->count() ? $d['treatments']->count().' traitement'.($d['treatments']->count() > 1 ? 's' : '').' en cours' : null,
            $d['payments']->count() ? $d['payments']->count().' paiement'.($d['payments']->count() > 1 ? 's' : '').' à prévoir' : null,
            $d['debriefs']->count() ? $d['debriefs']->count().' bilan'.($d['debriefs']->count() > 1 ? 's' : '').' de séance à faire' : null,
        ]);

        return $parts ? 'Cette semaine : '.implode(', ', $parts).'.' : 'Rien de prévu cette semaine.';
    }

    /** Texte complet (e-mail) : une ligne par élément, regroupé par rubrique. */
    public function text(array $d, bool $budget = true): string
    {
        $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' €';
        $blocks = [];
        if ($d['events']->isNotEmpty()) {
            $blocks[] = "**Au programme**\n".$d['events']->take(15)->map(fn ($o) => '• '.ucfirst($o['at']->translatedFormat('l j')).($o['event']->all_day ? '' : ' à '.$o['at']->format('H:i')).' – '.$o['event']->title.($o['event']->horse && ! str_contains($o['event']->title, $o['event']->horse->shortName()) ? ' ('.$o['event']->horse->shortName().')' : ''))->implode("\n");
        }
        if ($d['treatments']->isNotEmpty()) {
            $blocks[] = "**Traitements en cours**\n".$d['treatments']->map(fn ($t) => '• '.$t->horse->shortName().' : '.$t->product.($t->ends_on ? ' jusqu\'au '.$t->ends_on->format('d/m') : ''))->implode("\n");
        }
        if ($d['payments']->isNotEmpty()) {
            $blocks[] = "**Paiements à prévoir**\n".$d['payments']->map(fn ($e) => '• '.($e->spent_on->isPast() && ! $e->spent_on->isToday() ? 'En retard – ' : $e->spent_on->format('d/m').' – ').($e->comment ?: $e->category?->name).($e->horse ? ' ('.$e->horse->shortName().')' : '').' : '.$eur($e->amount))->implode("\n");
        }
        if ($d['debriefs']->isNotEmpty()) {
            $blocks[] = "**Bilans de séance à faire**\n".$d['debriefs']->map(fn ($s) => '• '.$s->scheduled_at->format('d/m').' – '.$s->title().' ('.$s->horse->shortName().')')->implode("\n");
        }
        if ($budget) {
            $blocks[] = '**Budget du mois** : '.$eur($d['month_total']).' ('.$d['month_count'].' dépense'.($d['month_count'] > 1 ? 's' : '').').';
        }

        return implode("\n\n", $blocks);
    }

    /**
     * Introduction rédigée par l'assistant (2 à 3 phrases chaleureuses et précises), pour les espaces
     * dont l'offre inclut l'assistant ; null sinon ou en cas d'échec (le récapitulatif part sans).
     */
    public function aiIntro(User $user, array $d, bool $budget = true, bool $afterGreeting = true): ?string
    {
        if (! $this->introEligible($user, $d)) {
            return null;
        }
        try {
            $result = app(ClaudeGateway::class)->write(
                'Tu écris l\'introduction du récapitulatif hebdomadaire de Jack&Cie, application de suivi de chevaux. '
                .'Français, 2 à 3 phrases, ton chaleureux et sobre, tutoiement interdit (vouvoiement). Mets en avant 2 ou 3 points concrets de la semaine (jours, noms des chevaux), '
                .'sans rien inventer, sans liste, sans mise en forme, sans formule de politesse finale ni avis médical. '
                .($afterGreeting ? 'Ne commence pas par « Bonjour » (la salutation est déjà écrite). ' : 'Commence directement par le contenu, sans salutation. ')
                .'Réponds uniquement avec le texte final, entièrement en français : aucun commentaire sur ces consignes, aucun brouillon, aucune autre langue.',
                'Prénom : '.(strtok(trim((string) $user->name), ' ') ?: '—').'\nSemaine du '.$d['from']->translatedFormat('l j F').' au '.$d['to']->translatedFormat('l j F')."\n\n".$this->text($d, $budget),
                1000);

            if (! empty($result['truncated'])) {
                report(new \RuntimeException('Introduction de la semaine coupée par la limite de longueur.'));
            }

            return self::cleanIntro($result['text']);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Garde-fou : retire les phrases où le modèle commente sa consigne ou change de langue
     * (« … wait, I must not start with "Bonjour". Let me write in French. »). Null s'il ne reste rien d'utile.
     */
    public static function cleanIntro(string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text), " \t\n\r\0\x0B\"«»“”");
        $sentences = preg_split('/(?<=[.!?…])\s+(?=\S)/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $english = '/\b(wait|let me|let\'s|I must|I should|I will|I\'ll|I need|I am|I\'m|actually|okay|sorry|instructions?|the user|in French|write|should not|must not|start with|allowed|not allowed|draft|here is|here\'s)\b/iu';
        $kept = array_filter($sentences, function ($sentence) use ($english) {
            // Phrase inachevée (« Bonjour Nicolas, ... ») ou commentaire de la consigne / en anglais.
            // Note entre parenthèses seule (« (no tutoiement allowed). »).
            if (preg_match('/(\.\.\.|…)$/u', $sentence) || preg_match('/^\(.*\)[.!?]?$/u', $sentence) || preg_match($english, $sentence)) {
                return false;
            }
            $words = preg_split('/[^\p{L}\']+/u', mb_strtolower($sentence), -1, PREG_SPLIT_NO_EMPTY);
            $en = count(array_intersect($words, ['the', 'and', 'is', 'are', 'you', 'your', 'this', 'that', 'with', 'for', 'will', 'be', 'it', 'of', 'to', 'week', 'have']));

            return ! $words || $en / count($words) < 0.2;
        });
        // Dernière phrase sans ponctuation finale : réponse coupée, elle est retirée plutôt qu'affichée à moitié.
        if ($kept && ! preg_match('/[.!?…»)]$/u', end($kept))) {
            array_pop($kept);
        }
        $clean = trim(implode(' ', $kept));

        return mb_strlen($clean) >= 20 ? mb_substr($clean, 0, 700) : null;
    }

    /** Introduction IA possible : réglage actif, semaine non vide, service prêt et offre avec l'assistant. */
    public function introEligible(User $user, array $d): bool
    {
        // Comptes de simulation de charge : jamais d'appel (payant) à l'IA.
        if (! (ApplicationSetting::get('digest_ai') ?? true) || $this->isEmpty($d) || LoadSimulation::isSimulated($user)) {
            return false;
        }
        $entitlements = app(Entitlements::class);

        return app(AssistantService::class)->serviceReady()
            && $user->memberships->contains(fn ($m) => $m->organization && $entitlements->hasFeature($m->organization, 'assistant'));
    }

    /**
     * Empreinte du contenu de la semaine : change avec la semaine, l'ajout, la suppression ou la modification
     * (date, heure, titre, montant…) d'un élément. Le budget du mois n'en fait pas partie.
     */
    public function fingerprint(array $d): string
    {
        return hash('sha256', json_encode([
            'v4', // à changer pour faire réécrire toutes les introductions (changement de consigne)
            $d['from']->toDateString(),
            $d['events']->map(fn ($o) => [$o['event']->id, $o['at']->toDateTimeString(), $o['event']->title, (bool) $o['event']->all_day, $o['event']->horse_id])->values(),
            $d['treatments']->map(fn ($t) => [$t->id, $t->product, $t->ends_on?->toDateString(), $t->horse_id])->values(),
            $d['payments']->map(fn ($e) => [$e->id, (string) $e->amount, $e->spent_on?->toDateString(), $e->comment, $e->expense_category_id, $e->horse_id])->values(),
            $d['debriefs']->map(fn ($r) => [$r->id, $r->scheduled_at?->toDateTimeString(), $r->horse_id])->values(),
        ]));
    }

    /** Introduction déjà rédigée pour ce contenu exact de la semaine (sans appel à l'IA). */
    public function cachedPageIntro(User $user, array $d): ?string
    {
        return DigestIntro::where('user_id', $user->id)->where('fingerprint', $this->fingerprint($d))->value('text');
    }

    /**
     * Introduction de la page « Ma semaine » : réutilisée tant que la semaine n'a pas changé, sinon rédigée
     * à nouveau (un seul appel à la fois par utilisateur). Pas décomptée des consignes de l'assistant.
     */
    public function pageIntro(User $user, array $d): ?string
    {
        if (! $this->introEligible($user, $d)) {
            return null;
        }
        if (($cached = $this->cachedPageIntro($user, $d)) !== null) {
            return $cached;
        }

        return Cache::lock('digest-intro:'.$user->id, 60)->block(45, function () use ($user, $d) {
            if (($cached = $this->cachedPageIntro($user, $d)) !== null) {
                return $cached; // rédigée entre-temps par un autre chargement de la page
            }
            $text = $this->aiIntro($user, $d, false, false);
            if ($text !== null) {
                DigestIntro::updateOrCreate(['user_id' => $user->id], ['fingerprint' => $this->fingerprint($d), 'text' => $text]);
            }

            return $text;
        });
    }
}
