<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\HealthObservation;
use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\RidingSession;
use App\Models\SessionComment;
use App\Services\Audit;
use App\Support\Perm;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Exports générés côté serveur, restreints aux permissions de l'utilisateur.
 * Disponibles même en lecture seule (abonnement expiré).
 */
class ExportController extends Controller
{
    public function horsePdf(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_VIEW);
        $perms = $this->horsePerms($horse);
        $horse->load(['breed', 'identifiers', 'ownerships.user', 'pedigree', 'origin', 'locations']);
        Audit::log('export.horse_pdf', $horse, [], $horse->organization_id);

        return Pdf::loadView('exports.horse', [
            'horse' => $horse,
            'perms' => $perms,
            'treatments' => in_array(Perm::TREATMENTS_VIEW, $perms, true) ? $horse->treatments()->current()->get() : collect(),
            'feeding' => in_array(Perm::FEEDING_VIEW, $perms, true) ? $horse->feedingPlans()->with('entries')->first() : null,
        ])->download('fiche-'.str($horse->official_name)->slug().'.pdf');
    }

    public function healthPdf(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_VIEW);
        Audit::log('export.health_pdf', $horse, [], $horse->organization_id);

        return Pdf::loadView('exports.health', [
            'horse' => $horse->load(['breed', 'identifiers']),
            'records' => $horse->careRecords()->with(['category', 'professional'])->get(),
            'treatments' => $horse->treatments()->get(),
        ])->download('sante-'.str($horse->official_name)->slug().'.pdf');
    }

    public function sessions(Request $request, Horse $horse, string $format)
    {
        $this->authorizeHorse($horse, Perm::SESSIONS_VIEW);
        $sessions = $horse->sessions()->with(['rider', 'exercises'])
            ->when($request->query('from'), fn ($q, $f) => $q->where('scheduled_at', '>=', $f))
            ->when($request->query('to'), fn ($q, $t) => $q->where('scheduled_at', '<=', $t.' 23:59:59'))->get();
        Audit::log('export.sessions', $horse, ['format' => $format, 'count' => $sessions->count()], $horse->organization_id);
        $name = 'seances-'.str($horse->official_name)->slug();

        if ($format === 'pdf') {
            return Pdf::loadView('exports.sessions', ['horse' => $horse, 'sessions' => $sessions])->download($name.'.pdf');
        }

        return response()->streamDownload(function () use ($sessions) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Cavalier', 'Type', 'Discipline', 'Objectif', 'Statut', 'Durée prévue', 'Durée réelle', 'Exercices réalisés', 'Ressenti', 'Comportement', 'Progrès', 'À retravailler', 'Anomalies'], ';');
            foreach ($sessions as $s) {
                fputcsv($out, [
                    $s->scheduled_at->format('d/m/Y H:i'), $s->riderLabel(), $s->typeLabel(), $s->discipline, $s->objective, RidingSession::STATUSES[$s->status],
                    $s->planned_minutes, $s->actual_minutes, $s->exercises->where('status', 'done')->pluck('name')->implode(', '),
                    $s->rider_feeling, $s->horse_behavior, $s->progress, $s->to_rework, $s->anomalies,
                ], ';');
            }
            fclose($out);
        }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Export RGPD : données personnelles de l'utilisateur (JSON). Les dossiers
     * des chevaux appartenant à d'autres personnes n'y figurent pas, seulement
     * les contributions de l'utilisateur.
     */
    public function personalData(Request $request)
    {
        $user = $request->user()->load('profile');
        Audit::log('gdpr.export', $user);
        $ownedHorseIds = Horse::whereIn('organization_id', $user->memberships->filter(fn ($m) => $m->role->key === 'owner')->pluck('organization_id'))->pluck('id');

        $data = [
            'generated_at' => now()->toIso8601String(),
            'account' => $user->only(['name', 'email', 'email_verified_at', 'created_at', 'last_login_at', 'terms_accepted_at']),
            'profile' => $user->profile?->only(['first_name', 'last_name', 'phone', 'city', 'riding_level', 'email_notifications']),
            'organizations' => $user->memberships->map(fn ($m) => ['name' => $m->organization->name, 'type' => $m->organization->type, 'role' => $m->role->name, 'joined_at' => $m->joined_at]),
            'horses_owned_space' => Horse::with(['identifiers', 'breed'])->whereIn('id', $ownedHorseIds)->get()->map(fn ($h) => $h->only(['official_name', 'usual_name', 'sex', 'birth_date', 'coat', 'particularities', 'general_notes']) + ['breed' => $h->breed?->name, 'identifiers' => $h->identifiers->pluck('value', 'type')]),
            'sessions_as_rider_or_author' => RidingSession::with('horse:id,official_name')->where(fn ($q) => $q->where('rider_id', $user->id)->orWhere('created_by', $user->id))->get()->map(fn ($s) => ['horse' => $s->horse?->official_name] + $s->only(['scheduled_at', 'session_type', 'objective', 'status', 'actual_minutes', 'progress', 'to_rework', 'notes'])),
            'comments' => SessionComment::where('author_id', $user->id)->get(['body', 'created_at']),
            'observations' => HealthObservation::where('author_id', $user->id)->get(['body', 'severity', 'observed_at']),
            'daily_logs' => DailyLog::where('author_id', $user->id)->get(['logged_at', 'appetite', 'general_state', 'behavior', 'observations']),
            'expenses_entered' => Expense::where('author_id', $user->id)->get(['spent_on', 'amount', 'currency', 'supplier', 'comment']),
            'access_grants_received' => HorseAccessGrant::with('horse:id,official_name')->where('user_id', $user->id)->get()->map(fn ($g) => ['horse' => $g->horse?->official_name, 'permissions' => $g->permissions, 'starts_at' => $g->starts_at, 'expires_at' => $g->expires_at, 'revoked_at' => $g->revoked_at]),
            'security_log' => AuditLog::where('user_id', $user->id)->latest('created_at')->limit(500)->get(['action', 'ip_address', 'created_at']),
            'notifications' => $user->notifications()->limit(500)->get(['data', 'created_at', 'read_at']),
        ];

        return response()->streamDownload(fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'mes-donnees-'.now()->format('Ymd').'.json', ['Content-Type' => 'application/json; charset=UTF-8']);
    }
}
