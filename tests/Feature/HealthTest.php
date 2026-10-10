<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\HorseAccessGrant;
use App\Models\Professional;
use App\Models\Treatment;
use App\Notifications\AppNotification;
use App\Support\Perm;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_care_record_creates_reminder_event_and_expense(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $horse = $this->horseFor($user);
        $vet = new Professional(['last_name' => 'Durand', 'kind' => 'veterinarian']);
        $vet->organization_id = $horse->organization_id;
        $vet->save();
        $cat = CareCategory::where('key', 'vaccination')->first();

        $this->actingAs($user)->post("/chevaux/{$horse->id}/soins", [
            'care_category_id' => $cat->id, 'professional_id' => $vet->id, 'performed_at' => now()->format('Y-m-d H:i'),
            'reason' => 'Rappel grippe', 'cost' => '85.50', 'next_check_on' => now()->addMonths(6)->toDateString(), 'add_expense' => '1',
        ])->assertRedirect();

        $care = CareRecord::firstOrFail();
        $this->assertSame($user->id, $care->author_id);
        $event = CalendarEvent::where('source_id', $care->id)->firstOrFail();
        $this->assertSame('veterinary', $event->type);
        $this->assertSame(now()->addMonths(6)->toDateString(), $event->starts_at->toDateString());
        $expense = Expense::where('care_record_id', $care->id)->firstOrFail();
        $this->assertSame('85.50', (string) $expense->amount);

        // Mise à jour : l'événement est déplacé, pas dupliqué.
        $this->actingAs($user)->put("/chevaux/{$horse->id}/soins/{$care->id}", ['version' => $care->version, 'care_category_id' => $cat->id, 'performed_at' => now()->format('Y-m-d H:i'), 'next_check_on' => now()->addYear()->toDateString(), 'add_expense' => '1', 'cost' => '85.50'])->assertRedirect();
        $this->assertSame(1, CalendarEvent::where('source_id', $care->id)->count());
        $this->assertSame(1, Expense::where('care_record_id', $care->id)->count());
    }

    public function test_concurrent_care_edit_is_detected(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $cat = CareCategory::first();
        $this->actingAs($user)->post("/chevaux/{$horse->id}/soins", ['care_category_id' => $cat->id, 'performed_at' => now()->format('Y-m-d H:i'), 'reason' => 'A']);
        $care = CareRecord::firstOrFail();
        $care->update(['reason' => 'modifié ailleurs']);

        $this->actingAs($user)->put("/chevaux/{$horse->id}/soins/{$care->id}", ['version' => 1, 'care_category_id' => $cat->id, 'performed_at' => now()->format('Y-m-d H:i'), 'reason' => 'B'])
            ->assertSessionHas('error');
        $this->assertSame('modifié ailleurs', $care->fresh()->reason);
    }

    public function test_professional_from_other_organization_is_rejected(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $other = $this->user();
        $foreign = new Professional(['last_name' => 'Autre', 'kind' => 'farrier']);
        $foreign->organization_id = $other->personalOrganization()->id;
        $foreign->save();
        $this->actingAs($user)->post("/chevaux/{$horse->id}/soins", ['care_category_id' => CareCategory::first()->id, 'performed_at' => now()->format('Y-m-d H:i'), 'professional_id' => $foreign->id])
            ->assertSessionHasErrors('professional_id');
    }

    public function test_treatment_tracking_and_administration(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->actingAs($user)->post("/chevaux/{$horse->id}/traitements", ['product' => 'Anti-inflammatoire', 'dosage' => 'selon ordonnance', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addDays(5)->toDateString(), 'track_administrations' => '1'])->assertRedirect();
        $t = Treatment::firstOrFail();
        $this->assertDatabaseHas('calendar_events', ['source_id' => $t->id, 'type' => 'treatment']);
        $this->actingAs($user)->post("/chevaux/{$horse->id}/traitements/{$t->id}/administrer")->assertRedirect();
        $this->assertSame(1, $t->administrations()->count());
        $this->actingAs($user)->get("/chevaux/{$horse->id}/sante")->assertOk()->assertSee('Anti-inflammatoire');
        $this->actingAs($user)->get('/sante')->assertOk()->assertSee('Anti-inflammatoire');
    }

    public function test_health_export_requires_health_permission(): void
    {
        $owner = $this->user();
        $rider = $this->user();
        $horse = $this->horseFor($owner);
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => ['horse.view', 'sessions.view']]);
        $this->actingAs($owner)->get("/chevaux/{$horse->id}/export/sante.pdf")->assertOk();
        $this->actingAs($rider)->get("/chevaux/{$horse->id}/export/sante.pdf")->assertForbidden();
    }

    public function test_observation_notifies_only_people_with_health_access(): void
    {
        Notification::fake();
        $owner = $this->user();
        $rider = $this->user();
        $viewer = $this->user();
        $horse = $this->horseFor($owner);
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => Perm::halfLeasePreset()]);
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => ['horse.view']]);
        $this->actingAs($rider)->post("/chevaux/{$horse->id}/observations", ['body' => 'Boiterie antérieur gauche', 'severity' => 'alert'])->assertRedirect();
        Notification::assertSentTo($owner, AppNotification::class);
        Notification::assertNotSentTo($viewer, AppNotification::class);
    }
}
