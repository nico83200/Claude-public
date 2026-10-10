<?php

namespace App\Http\Controllers;

use App\Models\RidingSession;
use App\Models\SessionTemplate;
use App\Support\Perm;
use Illuminate\Http\Request;

class SessionTemplateController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('sessions.templates', [
            'templates' => SessionTemplate::where('user_id', $user->id)->orWhereIn('organization_id', $user->memberships->pluck('organization_id'))->with('user')->orderBy('name')->get(),
        ]);
    }

    public function storeFromSession(Request $request, RidingSession $session)
    {
        $this->authorizeHorse($session->horse, Perm::SESSIONS_VIEW);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'share' => ['nullable', 'boolean']]);
        $orgId = null;
        if ($request->boolean('share')) {
            $org = $this->currentOrganization();
            $this->authorizeOrg($org, Perm::EXERCISES_MANAGE);
            $orgId = $org->id;
        }
        $template = new SessionTemplate([
            'organization_id' => $orgId, 'name' => $data['name'], 'discipline' => $session->discipline,
            'session_type' => $session->session_type, 'objective' => $session->objective, 'planned_minutes' => $session->planned_minutes,
            'items' => $session->exercises()->get()->map(fn ($i) => [
                'exercise_id' => $i->exercise_id, 'name' => $i->name, 'phase' => $i->phase, 'is_break' => $i->is_break,
                'planned_minutes' => $i->planned_minutes, 'planned_repetitions' => $i->planned_repetitions, 'instructions' => $i->instructions,
            ])->all(),
        ]);
        $template->user_id = $request->user()->id;
        $template->save();

        return back()->with('success', 'Modèle « '.$template->name.' » enregistré.');
    }

    public function destroy(Request $request, SessionTemplate $template)
    {
        abort_unless($template->user_id === $request->user()->id || ($template->organization_id && $request->user()->canInOrg($template->organization_id, Perm::EXERCISES_MANAGE)), 403);
        $template->delete();

        return back()->with('success', 'Modèle supprimé (les séances déjà créées sont conservées).');
    }
}
