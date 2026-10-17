<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Agenda au format iCalendar (RFC 5545) pour l'abonnement depuis Google Agenda ou le Calendrier Apple.
 * Adresse secrète propre à chaque utilisateur (révocable) ; uniquement les événements qu'il a le droit de voir.
 * Abonnement en lecture : les agendas externes relisent l'adresse régulièrement (Apple : réglable, Google : quelques heures).
 */
class CalendarFeed
{
    public function __construct(private HorseAccess $access, private Entitlements $entitlements) {}

    public function enabledFor(User $user): bool
    {
        return $user->loadMissing('memberships.organization')->memberships
            ->contains(fn ($m) => $m->organization && $this->entitlements->hasFeature($m->organization, 'calendar_sync'));
    }

    public function tokenFor(User $user): string
    {
        if (! $user->calendar_token) {
            $user->forceFill(['calendar_token' => Str::random(48)])->save();
        }

        return $user->calendar_token;
    }

    public function regenerate(User $user): string
    {
        $user->forceFill(['calendar_token' => Str::random(48)])->save();

        return $user->calendar_token;
    }

    /** Adresse https de l'abonnement, et sa variante webcal:// (ouverture directe dans le Calendrier Apple). */
    public function urls(User $user): array
    {
        $https = route('calendar.feed', $this->tokenFor($user));

        return [
            'https' => $https,
            'webcal' => preg_replace('#^https?://#', 'webcal://', $https),
            'google' => 'https://calendar.google.com/calendar/r?cid='.rawurlencode(preg_replace('#^https?://#', 'webcal://', $https)),
        ];
    }

    public function render(User $user): string
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Jack&Cie//Agenda//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:Jack&Cie', 'X-WR-TIMEZONE:'.config('app.timezone'), 'REFRESH-INTERVAL;VALUE=DURATION:PT1H', 'X-PUBLISHED-TTL:PT1H'];
        if (! $this->enabledFor($user)) {
            // Offre gratuite ou abonnement terminé : l'agenda externe se vide, avec une explication.
            $lines = array_merge($lines, $this->vevent('jackcie-sync-off', today(), true, 30, 'Synchronisation Jack&Cie en pause',
                'La synchronisation de l\'agenda est réservée aux offres payantes. Retrouvez vos rendez-vous dans Jack&Cie.', route('billing.index'), 'CONFIRMED', null, now()));
        } else {
            foreach ($this->events($user) as $e) {
                $title = $e->title.($e->horse && ! str_contains($e->title, $e->horse->shortName()) ? ' – '.$e->horse->shortName() : '');
                $desc = collect([CalendarEvent::TYPES[$e->type] ?? null, $e->horse ? 'Cheval : '.$e->horse->shortName() : null,
                    $e->professional ? 'Intervenant : '.$e->professional->fullName() : null, $e->notes])->filter()->implode("\n");
                $lines = array_merge($lines, $this->vevent('event-'.$e->id, $e->starts_at, $e->all_day, (int) $e->duration_minutes ?: 60, $title, $desc,
                    route('calendar.show', $e), $e->status === 'cancelled' ? 'CANCELLED' : ($e->status === 'planned' ? 'TENTATIVE' : 'CONFIRMED'), $this->rrule($e), $e->updated_at ?? now(),
                    $e->recurrence ? $this->exdates($e) : []));
            }
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([$this, 'fold'], $lines))."\r\n";
    }

    /** Événements visibles par l'utilisateur, de 3 mois en arrière à 18 mois en avant (récurrences comprises). */
    private function events(User $user)
    {
        $horseIds = $this->access->horseIdsWith($user, Perm::CALENDAR_VIEW);
        $orgIds = $user->memberships->pluck('organization_id');

        return CalendarEvent::with(['horse', 'professional'])
            ->where(fn ($q) => $q->whereIn('horse_id', $horseIds)->orWhere(fn ($w) => $w->whereNull('horse_id')->whereIn('organization_id', $orgIds)))
            ->visibleSessions($this->access->horseIdsWith($user, Perm::SESSIONS_VIEW))
            ->where(fn ($q) => $q->whereBetween('starts_at', [now()->subMonths(3), now()->addMonths(18)])
                ->orWhere(fn ($r) => $r->whereNotNull('recurrence')->where(fn ($u) => $u->whereNull('recurrence_until')->orWhere('recurrence_until', '>=', now()->subMonths(3)->toDateString()))))
            ->orderBy('starts_at')->limit(3000)->get();
    }

    private function rrule(CalendarEvent $e): ?string
    {
        if (! in_array($e->recurrence, ['daily', 'weekly', 'monthly'], true)) {
            return null;
        }

        return 'FREQ='.strtoupper($e->recurrence).';INTERVAL='.max(1, (int) $e->recurrence_interval)
            .($e->recurrence_until ? ';UNTIL='.Carbon::parse($e->recurrence_until)->endOfDay()->utc()->format('Ymd\THis\Z') : '');
    }

    /** Occurrences retirées de la série (supprimées ou validées à part). */
    private function exdates(CalendarEvent $e): array
    {
        return collect($e->recurrence_exceptions ?? [])->map(fn ($d) => $e->all_day
            ? 'EXDATE;VALUE=DATE:'.Carbon::parse($d)->format('Ymd')
            : 'EXDATE:'.Carbon::parse($d.' '.$e->starts_at->format('H:i:s'))->utc()->format('Ymd\THis\Z'))->all();
    }

    private function vevent(string $uid, Carbon $start, bool $allDay, int $minutes, string $title, string $desc, string $url, string $status, ?string $rrule, Carbon $stamp, array $exdates = []): array
    {
        $lines = ['BEGIN:VEVENT', 'UID:'.$uid.'@'.parse_url(config('app.url'), PHP_URL_HOST), 'DTSTAMP:'.$stamp->copy()->utc()->format('Ymd\THis\Z')];
        if ($allDay) {
            $lines[] = 'DTSTART;VALUE=DATE:'.$start->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$start->copy()->addDay()->format('Ymd');
        } else {
            $lines[] = 'DTSTART:'.$start->copy()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:'.$start->copy()->addMinutes(max(5, $minutes))->utc()->format('Ymd\THis\Z');
        }
        if ($rrule) {
            $lines[] = 'RRULE:'.$rrule;
            array_push($lines, ...$exdates);
        }
        $lines[] = 'SUMMARY:'.$this->text($title);
        if ($desc !== '') {
            $lines[] = 'DESCRIPTION:'.$this->text($desc."\n".$url);
        }
        $lines[] = 'URL:'.$url;
        $lines[] = 'STATUS:'.$status;
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function text(string $s): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\\,', '\\n', '\\n'], $s);
    }

    /** Lignes de 75 octets au plus (continuation par un espace), sans couper un caractère UTF-8. */
    private function fold(string $line): string
    {
        $out = '';
        $max = 75; // 75 octets, puis 74 + l'espace de continuation
        while (strlen($line) > $max) {
            $cut = $max;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
            $max = 74;
        }

        return $out.$line;
    }
}
