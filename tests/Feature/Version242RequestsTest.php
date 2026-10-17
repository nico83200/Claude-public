<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\User;
use App\Services\CompetitionVaccines;
use App\Services\HorseAccess;
use App\Support\Perm;
use App\Support\VaccineRules;
use App\Support\Vaccines;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Vaccins vérifiés avant un concours : règles FFE et FEI (réglables par le super-administrateur), fiche, agenda, accueil, rappels. */
class Version242RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function owner(): array
    {
        Carbon::setTestNow('2026-10-17 09:00');
        $owner = $this->user(['name' => 'Paul MARTIN']);
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);

        return [$owner, $horse];
    }

    /** Vaccination du carnet ($vaccines null : reconnus dans le motif). */
    private function vaccine(Horse $horse, string $date, ?array $vaccines = ['flu', 'rhino'], ?string $reason = null, string $category = 'vaccination'): CareRecord
    {
        $care = new CareRecord(['care_category_id' => CareCategory::where('key', $category)->value('id'), 'performed_at' => $date.' 10:00', 'reason' => $reason, 'vaccines' => $vaccines]);
        $care->horse_id = $horse->id;
        $care->save();

        return $care;
    }

    private function check(Horse $horse, string $day, string $rule): array
    {
        $cv = app(CompetitionVaccines::class);
        $cv->flush();

        return $cv->check($horse, Carbon::parse($day), $rule);
    }

    private function competition(User $owner, Horse $horse, string $date, ?string $rules = null): CalendarEvent
    {
        $event = new CalendarEvent(['horse_id' => $horse->id, 'type' => 'competition', 'title' => 'CSO de Lyon', 'starts_at' => $date.' 08:00', 'vaccine_rules' => $rules]);
        $event->organization_id = $horse->organization_id;
        $event->created_by = $owner->id;
        $event->save();

        return $event;
    }

    private function fresh(): void
    {
        app()->forgetInstance(HorseAccess::class);
        app()->forgetInstance(CompetitionVaccines::class);
    }

    public function test_ffe_rules_one_year_booster_and_eighth_day(): void
    {
        [, $horse] = $this->owner();
        $this->vaccine($horse, '2026-03-03');
        $cv = app(CompetitionVaccines::class);

        // Rappel valable un an, jour anniversaire compris (tolérance FFE).
        $ok = $this->check($horse, '2027-03-03', 'ffe_amateur');
        $this->assertSame('ok', $ok['state']);
        $this->assertTrue($ok['items']['flu']['single'], 'Une seule injection, pas notée comme un rappel : remarque sur la primo-vaccination.');
        $late = $this->check($horse, '2027-03-04', 'ffe_amateur');
        $this->assertSame('ko', $late['state']);
        $this->assertSame('expired', $late['items']['flu']['status']);
        $this->assertSame('2027-02-24', $late['deadline']->toDateString(), 'Au moins 8 jours avant le concours.');
        $this->assertSame('Rappel grippe à faire ; rappel rhinopneumonie à faire. À faire au plus tard le 24 février.', $cv->headline($late));
        $this->assertSame('Grippe : le rappel du 3 mars 2026 n\'est valable que jusqu\'au 3 mars 2027.', $cv->line($late['items']['flu'], $late));

        // Concours à partir du 8e jour suivant l'injection.
        $this->vaccine($horse, '2026-10-15');
        $recent = $this->check($horse, '2026-10-22', 'ffe_amateur');
        $this->assertSame('too_recent', $recent['items']['flu']['status']);
        $this->assertSame('Grippe : vaccin du 15 octobre 2026 trop proche du concours ; Uni peut concourir à partir du 23 octobre 2026.', $cv->line($recent['items']['flu'], $recent));
        $this->assertSame('ok', $this->check($horse, '2026-10-23', 'ffe_amateur')['state']);
    }

    public function test_fei_six_months_and_21_days_and_seven_days_before_arrival(): void
    {
        [$owner, $horse] = $this->owner();
        $this->vaccine($horse, '2026-03-18', ['flu']);
        // 18 mars + 6 mois + 21 jours = 9 octobre ; la rhinopneumonie n'est pas exigée.
        $ok = $this->check($horse, '2026-10-09', 'fei');
        $this->assertSame('ok', $ok['state']);
        $this->assertSame(['flu'], array_keys($ok['items']));
        $this->assertSame('expired', $this->check($horse, '2026-10-10', 'fei')['items']['flu']['status']);

        // Pas de vaccin dans les 7 jours avant l'arrivée.
        $this->vaccine($horse, '2026-10-15', ['flu']);
        $this->assertSame('ok', $this->check($horse, '2026-10-22', 'fei')['state']);
        $close = $this->check($horse, '2026-10-21', 'fei');
        $this->assertSame('too_recent', $close['items']['flu']['status']);
        $this->assertStringContainsString('Uni peut arriver sur le concours à partir du 22 octobre 2026.', app(CompetitionVaccines::class)->line($close['items']['flu'], $close));

        // Fiche d'un concours FEI : règles, précision sur les amendes et source.
        $event = $this->competition($owner, $horse, '2026-11-20', 'fei');
        $this->fresh();
        $this->actingAs($owner)->get(route('calendar.show', $event))->assertOk()->assertSee('data-competition-vaccines="ok"', false)
            ->assertSee('FEI · concours international')->assertSee('annexe VI, sanctions 27 à 30')->assertSee('art. 1002 et 1003')
            ->assertSee('au moins 7 jours entre l&#039;injection et l&#039;arrivée sur le concours', false);
    }

    public function test_primary_course_lasts_until_the_first_booster(): void
    {
        [, $horse] = $this->owner();
        $this->vaccine($horse, '2026-05-01', ['flu']);
        $this->vaccine($horse, '2026-06-01', ['flu']);
        // FFE : 1er rappel 120 à 180 jours après la 2e injection.
        $this->assertSame('ok', $this->check($horse, '2026-11-28', 'ffe_club')['state']);
        $late = $this->check($horse, '2026-11-29', 'ffe_club');
        $this->assertTrue($late['items']['flu']['primo']);
        $this->assertStringStartsWith('1er rappel grippe à faire.', app(CompetitionVaccines::class)->headline($late));
        // FEI : V3 dans les 6 mois + 21 jours après V2.
        $this->assertSame('ok', $this->check($horse, '2026-12-22', 'fei')['state']);
        $this->assertSame('expired', $this->check($horse, '2026-12-23', 'fei')['items']['flu']['status']);
    }

    public function test_divisions_and_vaccines_read_from_the_record(): void
    {
        [, $horse] = $this->owner();
        // Motif seul : « Rappel grippe-tétanos » = grippe et tétanos.
        $this->vaccine($horse, '2026-09-01', null, 'Rappel grippe-tétanos');
        $this->assertSame(['flu', 'tetanus'], Vaccines::infer('Rappel grippe-tétanos'));
        $club = $this->check($horse, '2026-11-07', 'ffe_club');
        $this->assertSame('ok', $club['state'], 'Club, Poney : grippe seulement.');
        $this->assertFalse($club['items']['flu']['single'], 'Noté comme un rappel : pas de remarque sur la primo-vaccination.');
        $amateur = $this->check($horse, '2026-11-07', 'ffe_amateur');
        $this->assertSame('ko', $amateur['state'], 'Amateur, Pro : grippe et rhinopneumonie.');
        $this->assertSame('Rhinopneumonie : aucun vaccin dans le carnet.', app(CompetitionVaccines::class)->line($amateur['items']['rhino'], $amateur));

        // Vaccination sans précision : peut-être la rhinopneumonie, à préciser.
        $unknown = $this->vaccine($horse, '2026-09-20', null, 'Vaccins annuels');
        $unsure = $this->check($horse, '2026-11-07', 'ffe_amateur');
        $this->assertSame('unsure', $unsure['state']);
        $this->assertSame($unknown->id, $unsure['unknown']->first()['care']->id);
        $this->assertStringContainsString('vaccination du 20 septembre à préciser', app(CompetitionVaccines::class)->headline($unsure));

        // Soin vétérinaire : une grippe soignée ne compte pas, un « rappel grippe » oui.
        $someone = $this->user();
        $this->grantLicense($someone->personalOrganization(), 'particulier');
        $other = $this->horseFor($someone, null, ['official_name' => 'JACKPOT', 'usual_name' => 'Jack']);
        $this->vaccine($other, '2026-09-01', null, 'Grippe : toux et fièvre', 'veterinary');
        $this->assertSame('missing', $this->check($other, '2026-11-07', 'ffe_club')['items']['flu']['status']);
        $this->vaccine($other, '2026-09-02', null, 'Rappel grippe', 'veterinary');
        $this->assertSame('ok', $this->check($other, '2026-11-07', 'ffe_club')['state']);
    }

    public function test_event_page_agenda_badge_warning_and_rights(): void
    {
        [$owner, $horse] = $this->owner();
        // Concours saisi sans vaccin dans le carnet : avertissement.
        $this->actingAs($owner)->post(route('calendar.store'), ['type' => 'competition', 'horse_id' => $horse->id, 'title' => 'CSO de Lyon', 'date' => '2026-11-07', 'time' => '08:00', 'vaccine_rules' => 'ffe_amateur'])
            ->assertSessionHas('warning', 'Vaccins de Uni pour ce concours : aucun vaccin contre la grippe dans le carnet ; aucun vaccin contre la rhinopneumonie dans le carnet. À faire au plus tard le 30 octobre.');
        $event = CalendarEvent::where('title', 'CSO de Lyon')->sole();
        $this->assertSame('ffe_amateur', $event->vaccine_rules);

        // Fiche : vérification, échéance et prise de rendez-vous préremplie.
        $this->fresh();
        $html = $this->actingAs($owner)->get(route('calendar.show', $event))->assertOk()->assertSee('data-competition-vaccines="ko"', false)
            ->assertSee('Vaccins à vérifier')->assertSee('À faire au plus tard le vendredi 30 octobre')->assertSee('Prendre rendez-vous avec le vétérinaire')->getContent();
        $this->assertStringContainsString(e(route('calendar.index', ['nouveau' => 1, 'type' => 'veterinary', 'horse' => $horse->id, 'title' => 'Vaccin grippe et rhinopneumonie – Uni', 'date' => '2026-10-20'])), $html);
        // Agenda : pastille sur le concours.
        $this->fresh();
        $this->actingAs($owner)->get(route('calendar.index', ['view' => 'week', 'date' => '2026-11-07']))->assertSee('data-competition-badge="ko"', false);

        // Quelqu'un qui voit l'agenda mais pas la santé du cheval : ni vérification ni pastille.
        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => [Perm::HORSE_VIEW, Perm::CALENDAR_VIEW]]);
        $this->fresh();
        $this->actingAs($viewer)->get(route('calendar.show', $event))->assertOk()->assertDontSee('data-competition-vaccines', false);
        $this->fresh();
        $this->actingAs($viewer)->get(route('calendar.index', ['view' => 'week', 'date' => '2026-11-07']))->assertOk()->assertDontSee('data-competition-badge', false);

        // Vaccins en règle : pastille verte ; « Pas de vérification » : plus de bloc.
        $this->vaccine($horse, '2026-09-01');
        $this->fresh();
        $this->actingAs($owner)->get(route('calendar.show', $event))->assertSee('data-competition-vaccines="ok"', false)->assertSee('Vaccins en règle')->assertDontSee('Prendre rendez-vous avec le vétérinaire');
        $this->actingAs($owner)->put(route('calendar.update', $event), ['type' => 'competition', 'horse_id' => $horse->id, 'title' => 'CSO de Lyon', 'date' => '2026-11-07', 'time' => '08:00', 'status' => 'planned', 'vaccine_rules' => 'none'])
            ->assertSessionMissing('warning');
        $this->fresh();
        $this->actingAs($owner)->get(route('calendar.show', $event))->assertDontSee('data-competition-vaccines', false);
        // Un autre type d'événement ne garde pas de règlement.
        $this->actingAs($owner)->put(route('calendar.update', $event), ['type' => 'lesson', 'horse_id' => $horse->id, 'title' => 'Cours', 'date' => '2026-11-07', 'status' => 'planned', 'vaccine_rules' => 'fei']);
        $this->assertNull($event->fresh()->vaccine_rules);
    }

    public function test_planned_appointment_and_vaccine_too_close(): void
    {
        [$owner, $horse] = $this->owner();
        $competition = $this->competition($owner, $horse, '2026-11-07', 'ffe_club');
        // Rendez-vous vétérinaire prévu assez tôt : « Vaccin prévu ».
        $vet = new CalendarEvent(['horse_id' => $horse->id, 'type' => 'veterinary', 'title' => 'Vaccin grippe – Uni', 'starts_at' => '2026-10-25 10:00']);
        $vet->organization_id = $horse->organization_id;
        $vet->save();
        $cv = app(CompetitionVaccines::class);
        $check = $cv->forEvent($competition);
        $this->assertSame('planned', $check['state']);
        $this->assertSame(['blue', 'Vaccin prévu'], CompetitionVaccines::badge($check));
        $this->assertStringContainsString('Rendez-vous prévu le 25 octobre 2026 : Uni sera en règle s\'il a lieu.', $cv->line($check['items']['flu'], $check));

        // Rendez-vous déplacé à 4 jours du concours : le cheval ne pourrait pas concourir.
        $vet->update(['starts_at' => '2026-11-03 10:00']);
        $this->vaccine($horse, '2026-09-01', ['flu']);
        $cv->flush();
        $close = $cv->forEvent($competition);
        $this->assertSame('conflict', $close['state']);
        $this->assertStringContainsString('Le vaccin prévu le 3 novembre 2026 tombe moins de 8 jours avant le concours', $cv->line($close['items']['flu'], $close));

        // Rendez-vous vétérinaire « Vaccin grippe » déjà passé à sa saisie : enregistré comme vaccination (grippe).
        $this->actingAs($owner)->post(route('calendar.store'), ['type' => 'veterinary', 'horse_id' => $horse->id, 'title' => 'Vaccin grippe – Uni', 'date' => '2026-10-10', 'time' => '10:00']);
        $care = CareRecord::with('category')->where('horse_id', $horse->id)->whereDate('performed_at', '2026-10-10')->sole();
        $this->assertSame('vaccination', $care->category->key);
        $this->assertSame(['flu'], Vaccines::of($care));
    }

    public function test_reminders_before_the_competition(): void
    {
        [$owner, $horse] = $this->owner();
        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => [Perm::HORSE_VIEW, Perm::CALENDAR_VIEW]]);
        $this->vaccine($horse, '2025-11-01', ['flu']);
        $soon = $this->competition($owner, $horse, '2026-11-07', 'ffe_club'); // dans 21 jours
        $this->competition($owner, $horse, '2026-11-06', 'ffe_club'); // dans 20 jours : pas de rappel aujourd'hui

        $this->artisan('reminders:send')->assertSuccessful();
        $notes = $owner->notifications()->get()->filter(fn ($n) => $n->data['kind'] === 'competition_vaccines')->values();
        $this->assertCount(1, $notes);
        $this->assertSame('Concours dans 21 jours : vaccins de Uni', $notes[0]->data['title']);
        $this->assertSame('Rappel grippe à faire. À faire au plus tard le 30 octobre. CSO de Lyon, samedi 7 novembre (FFE Club/Poney).', $notes[0]->data['body']);
        $this->assertSame(route('calendar.show', $soon), $notes[0]->data['url']);
        $this->assertSame(0, $viewer->notifications()->count(), 'Sans accès à la santé du cheval, pas de rappel.');

        // Une seule fois ; plus rien quand les vaccins sont en règle.
        $this->artisan('reminders:send');
        $this->assertSame(1, $owner->notifications()->get()->filter(fn ($n) => $n->data['kind'] === 'competition_vaccines')->count());
        Carbon::setTestNow('2026-10-28 09:00'); // J-10
        $this->vaccine($horse, '2026-10-20', ['flu']);
        $this->artisan('reminders:send');
        $this->assertSame(1, $owner->notifications()->get()->filter(fn ($n) => $n->data['kind'] === 'competition_vaccines')->count());
    }

    public function test_home_page_alert_and_care_form_vaccines(): void
    {
        [$owner, $horse] = $this->owner();
        $event = $this->competition($owner, $horse, '2026-11-07', 'ffe_club');
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('data-competition-alert="'.$event->id.'"', false)->assertSee('Aucun vaccin contre la grippe dans le carnet');

        // Formulaire de soin : vaccins reçus, gardés seulement pour une vaccination.
        $category = CareCategory::where('key', 'vaccination')->value('id');
        $this->actingAs($owner)->get(route('horses.care.create', $horse))->assertOk()->assertSee('data-care-vaccines', false);
        $this->actingAs($owner)->post(route('horses.care.store', $horse), ['care_category_id' => $category, 'performed_at' => '2026-10-01 10:00', 'reason' => 'Rappel annuel', 'vaccines' => ['flu', 'rhino'], 'next_mode' => 'none'])
            ->assertSessionHasNoErrors();
        $care = CareRecord::where('reason', 'Rappel annuel')->sole();
        $this->assertSame(['flu', 'rhino'], $care->vaccines);
        $this->actingAs($owner)->get(route('horses.care.show', [$horse, $care]))->assertSee('Grippe et rhinopneumonie');
        $this->actingAs($owner)->post(route('horses.care.store', $horse), ['care_category_id' => CareCategory::where('key', 'farrier')->value('id'), 'performed_at' => '2026-10-02 10:00', 'reason' => 'Parage', 'vaccines' => ['flu'], 'next_mode' => 'none']);
        $this->assertNull(CareRecord::where('reason', 'Parage')->sole()->vaccines);
        $this->actingAs($owner)->post(route('horses.care.store', $horse), ['care_category_id' => $category, 'performed_at' => '2026-10-03 10:00', 'vaccines' => ['covid'], 'next_mode' => 'none'])->assertSessionHasErrors('vaccines.0');

        // Vaccins en règle : plus d'alerte sur l'accueil.
        $this->fresh();
        $this->actingAs($owner)->get(route('dashboard'))->assertDontSee('data-competition-alert', false);
    }

    public function test_super_admin_adjusts_the_rules(): void
    {
        [$owner, $horse] = $this->owner();
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $as = fn () => $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $as()->get(route('admin.vaccine-rules.edit'))->assertOk()->assertSee('data-vaccine-rules-form', false)->assertSee('FFE · Amateur, Pro, Enseignants, Prépa Compet')
            ->assertSee('art. 1.6')->assertSee('Veterinary Regulations 2026');
        $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.vaccine-rules.edit'))->assertNotFound(); // administration masquée

        $payload = fn (array $changes = []) => array_replace_recursive(['default' => 'ffe_amateur', 'reminder_days' => '21, 10', 'sets' => collect(VaccineRules::DEFAULTS['sets'])->map(fn ($s) => [
            'label' => $s['label'], 'short' => $s['short'], 'active' => $s['active'] ? '1' : '0', 'arrival' => $s['arrival'] ? '1' : '0', 'source' => $s['source'], 'checked_on' => $s['checked_on'],
            'vaccines' => collect($s['vaccines'])->map(fn ($v) => ['required' => $v['required'] ? '1' : '0'] + Arr::except($v, 'required'))->all(),
        ])->all()], $changes);

        // Nouveau règlement : la FEI passe à 6 mois sans tolérance, et la FFE Club devient le choix par défaut ; rappel à J-14 seulement.
        $as()->put(route('admin.vaccine-rules.update'), $payload(['default' => 'ffe_club', 'reminder_days' => '14', 'sets' => ['fei' => ['vaccines' => ['flu' => ['days' => '0']], 'checked_on' => '2027-01-05']]]))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('ffe_club', VaccineRules::defaultKey());
        $this->assertSame([14], VaccineRules::reminderDays());
        $this->vaccine($horse, '2026-03-18', ['flu']);
        $this->assertSame('expired', $this->check($horse, '2026-10-09', 'fei')['items']['flu']['status'], '18 mars + 6 mois = 18 septembre.');
        $this->assertSame(['flu'], array_keys($this->check($horse, '2026-10-09', '')['items']), 'Règlement par défaut : FFE Club.');

        // Garde-fous : durée nulle, aucun règlement actif.
        $as()->put(route('admin.vaccine-rules.update'), $payload(['sets' => ['ffe_club' => ['vaccines' => ['flu' => ['months' => '0', 'days' => '0']]]]]))->assertSessionHasErrors('sets.ffe_club.vaccines.flu.months');
        $as()->put(route('admin.vaccine-rules.update'), $payload(['sets' => ['ffe_club' => ['active' => '0'], 'ffe_amateur' => ['active' => '0'], 'fei' => ['active' => '0']]]))->assertSessionHasErrors('default');
        // Règlement désactivé : plus proposé ; valeurs vérifiées rétablies.
        $as()->put(route('admin.vaccine-rules.update'), $payload(['sets' => ['fei' => ['active' => '0']]]))->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('fei', VaccineRules::options());
        $as()->post(route('admin.vaccine-rules.reset'))->assertSessionHas('success');
        $this->assertSame(VaccineRules::DEFAULTS['sets']['fei']['vaccines']['flu']['days'], VaccineRules::sets()['fei']['vaccines']['flu']['days']);
        $this->assertSame([21, 10], VaccineRules::reminderDays());
    }
}
