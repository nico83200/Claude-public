<?php

namespace App\Http\Controllers;

use App\Models\HalfLease;
use App\Models\Horse;
use App\Models\RidingDay;
use App\Services\HalfLeaseFees;
use App\Services\HalfLeases;
use App\Services\InvitationService;
use App\Services\RidingDays;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Fiche demi-pension : proposée par le propriétaire depuis la page Partage, consultée par les deux parties. */
class HalfLeaseController extends Controller
{
    public function __construct(private HalfLeases $leases, private HalfLeaseFees $fees) {}

    public function create(Horse $horse, RidingDays $days)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);

        return view('half-leases.create', $this->formData($horse, new HalfLease([
            'starts_on' => today()->addMonthNoOverflow()->startOfMonth(), 'notice_days' => 30, 'permissions' => Perm::halfLeasePreset(),
        ]), $days));
    }

    public function store(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $data = $this->validated($request, true);
        [$lease, $token] = $this->leases->propose($horse, $request->user(), $data);

        // Lien montré une seule fois, à transmettre aussi par WhatsApp ou SMS (il ne fonctionne que pour l'adresse invitée).
        return redirect()->route('half-leases.show', $lease)
            ->with('success', 'Proposition envoyée à '.$lease->email.' : elle la reçoit dans l\'appli si elle a déjà un compte, sinon par e-mail avec le lien pour s\'inscrire.')
            ->with('invite_link', ['url' => route('invitations.show', $token), 'email' => $lease->email]);
    }

    public function show(Request $request, HalfLease $halfLease)
    {
        $user = $request->user();
        $halfLease->load(['horse.organization', 'user', 'creator', 'invitation']);
        $manager = $this->horseCan($halfLease->horse, Perm::HORSE_MANAGE);
        abort_unless($manager || $halfLease->isRider($user), 404);

        return view('half-leases.show', [
            'lease' => $halfLease,
            'horse' => $halfLease->horse,
            'manager' => $manager,
            'rider' => $halfLease->isRider($user),
            'summary' => $this->leases->summary($halfLease),
            'labels' => Perm::horseLabels(),
            'pendingInvitation' => $halfLease->status === 'proposed' && $halfLease->invitation?->isPending() ? $halfLease->invitation : null,
            'fees' => $this->fees->schedule($halfLease),
            'next' => $this->fees->next($halfLease),
            'viaStatement' => $this->fees->viaStatement($halfLease),
            'canReceive' => $manager && $this->horseCan($halfLease->horse, Perm::EXPENSES_CREATE),
            'documents' => $halfLease->documents()->with('uploader')->get(),
            'canUpload' => $halfLease->isOpen() && ($manager || $halfLease->user_id === $user->id),
            'docsIncluded' => $this->entitlements()->hasFeature($halfLease->horse->organization, 'documents'),
            'month' => $halfLease->status === 'active' && $halfLease->user_id ? (app(RidingDays::class)->monthCount($halfLease->horse, today())[$halfLease->user_id] ?? null) : null,
        ]);
    }

    public function edit(HalfLease $halfLease, RidingDays $days)
    {
        $this->authorizeHorse($halfLease->horse, Perm::HORSE_MANAGE);
        abort_unless($halfLease->isOpen(), 404);

        return view('half-leases.edit', $this->formData($halfLease->horse, $halfLease, $days));
    }

    public function update(Request $request, HalfLease $halfLease)
    {
        $this->authorizeHorse($halfLease->horse, Perm::HORSE_MANAGE);
        abort_unless($halfLease->isOpen(), 404);
        $this->leases->update($halfLease, $this->validated($request, false), $request->user());

        return redirect()->route('half-leases.show', $halfLease)->with('success', 'Demi-pension mise à jour.');
    }

    /** Fin par le propriétaire ou la demi-pension : dernier jour au choix (préavis proposé). */
    public function end(Request $request, HalfLease $halfLease)
    {
        $user = $request->user();
        abort_unless($halfLease->status === 'active' && ($this->horseCan($halfLease->horse, Perm::HORSE_MANAGE) || $halfLease->user_id === $user->id), 404);
        $data = $request->validate(['last_day' => ['required', 'date', 'after_or_equal:today']]);
        $this->leases->end($halfLease, Carbon::parse($data['last_day']), $user);

        return back()->with('success', 'Fin de la demi-pension le '.Carbon::parse($data['last_day'])->format('d/m/Y').' : l\'accès s\'arrête le soir même.');
    }

    /** Proposition retirée avant sa réponse. */
    public function cancel(HalfLease $halfLease)
    {
        $this->authorizeHorse($halfLease->horse, Perm::HORSE_MANAGE);
        abort_unless($halfLease->status === 'proposed', 404);
        $this->leases->cancel($halfLease);

        return redirect()->route('horses.sharing', $halfLease->horse)->with('success', 'Proposition de demi-pension annulée.');
    }

    /** Mois sans mensualité (vacances…), ou rétabli. */
    public function skipMonth(Request $request, HalfLease $halfLease)
    {
        $this->authorizeHorse($halfLease->horse, Perm::HORSE_MANAGE);
        abort_unless($halfLease->isOpen(), 404);
        $data = $request->validate(['month' => ['required', 'date_format:Y-m'], 'restore' => ['nullable', 'boolean']]);
        $request->boolean('restore') ? $this->fees->unskipMonth($halfLease, $data['month']) : $this->fees->skipMonth($halfLease, $data['month']);
        $label = Carbon::createFromFormat('Y-m-d', $data['month'].'-01')->translatedFormat('F Y');

        return back()->with('success', $request->boolean('restore') ? 'Mensualité de '.$label.' rétablie.' : 'Pas de mensualité en '.$label.'.');
    }

    /** Relance de la proposition : nouveau lien valable 14 jours. */
    public function resend(Request $request, HalfLease $halfLease, InvitationService $invitations)
    {
        $this->authorizeHorse($halfLease->horse, Perm::HORSE_MANAGE);
        abort_unless($halfLease->status === 'proposed' && $halfLease->invitation && ! $halfLease->invitation->accepted_at && ! $halfLease->invitation->revoked_at, 404);
        $token = $invitations->resend($halfLease->invitation, $request->user());

        return back()->with('success', 'Proposition renvoyée à '.$halfLease->email.'.')
            ->with('invite_link', ['url' => route('invitations.show', $token), 'email' => $halfLease->email]);
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => $creating ? ['required', 'email', 'max:255'] : ['prohibited'],
            'phone' => ['nullable', 'string', 'max:40'],
            'starts_on' => ['required', 'date'],
            'trial_ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on', 'after_or_equal:today'],
            'notice_days' => ['nullable', 'integer', Rule::in(array_keys(HalfLease::NOTICES))],
            'weekdays' => ['array'],
            'weekdays.*' => ['integer', Rule::in(array_keys(RidingDay::WEEKDAYS))],
            'monthly_amount' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'payment_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'first_month' => ['nullable', Rule::in(array_keys(HalfLeaseFees::FIRST_MONTH))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Perm::horseKeys())],
        ]);
    }

    private function formData(Horse $horse, HalfLease $lease, RidingDays $days): array
    {
        return [
            'horse' => $horse,
            'lease' => $lease,
            'taken' => $this->leases->takenDays($horse, $lease->exists ? $lease : null),
            'week' => $days->weekly($horse),
            'restWeek' => $days->restWeekdays($horse),
            'labels' => Perm::shareableLabels(),
            'presets' => ['half_lease' => Perm::halfLeasePreset(), 'boarding' => Perm::boardingPreset()],
        ];
    }
}
