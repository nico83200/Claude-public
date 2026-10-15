<?php

namespace App\Http\Controllers;

use App\Models\CareRecord;
use App\Services\Audit;
use App\Services\CalendarService;
use App\Services\CareReminders;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Boutons d'une notification de rappel de soin (« C'est fait », « Reporter d'une semaine »),
 * appelés par le service worker sans ouvrir l'application.
 *
 * Adresse signée (tient lieu de jeton CSRF), réservée à la personne destinataire, connectée,
 * et qui a toujours le droit de modifier la santé du cheval.
 */
class CareReminderActionController extends Controller
{
    public function __invoke(Request $request, CareRecord $care, string $action, CareReminders $reminders, CalendarService $calendar)
    {
        abort_unless((int) $request->query('user') === $request->user()->id, 403, 'Ce rappel était destiné à une autre personne.');
        $horse = $care->horse;
        abort_unless($horse && ! $horse->trashed(), 404);
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $what = $care->category->name.' de '.$horse->shortName();

        // Déjà traité (autre appareil, second appui, soin saisi entre-temps) : rien n'est fait deux fois.
        $handled = $care->next_check_done_at || ! $care->next_check_on || Carbon::parse($care->next_check_on)->toDateString() !== $request->query('due');
        if ($handled) {
            $message = $care->next_check_done_at ? $what.' : déjà noté comme fait.'
                : ($care->next_check_on ? $what.' : échéance déjà déplacée au '.Carbon::parse($care->next_check_on)->format('d/m/Y').'.' : $what.' : plus d\'échéance en attente.');

            return $this->reply($request, $message, route('horses.care.index', $horse));
        }

        if ($action === 'fait') {
            $record = $reminders->markDone($care, $request->user(), $calendar);
            Audit::log('care.created', $record, ['from' => 'reminder'], $horse->organization_id);
            $message = $what.' noté comme fait aujourd\'hui.'.($record->next_check_on ? ' Prochaine échéance : '.$record->next_check_on->format('d/m/Y').'.' : '');

            return $this->reply($request, $message, route('horses.care.show', [$horse, $record]));
        }

        $date = $reminders->postpone($care);
        Audit::log('care.reminder_postponed', $care, ['to' => $date->toDateString()], $horse->organization_id);

        return $this->reply($request, $what.' : échéance reportée au '.$date->format('d/m/Y').'.', route('horses.care.index', $horse));
    }

    private function reply(Request $request, string $message, string $url)
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'url' => $url])
            : redirect($url)->with('success', $message);
    }
}
