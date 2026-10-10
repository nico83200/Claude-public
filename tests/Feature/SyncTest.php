<?php

namespace Tests\Feature;

use App\Models\HorseAccessGrant;
use App\Models\RidingSession;
use App\Models\SubscriptionPlan;
use App\Models\SyncConflict;
use App\Models\SyncDevice;
use App\Support\Perm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncTest extends TestCase
{
    private string $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->device = (string) Str::uuid();
    }

    private function select($user, $horse): void
    {
        DB::table('offline_horse_selections')->insert(['user_id' => $user->id, 'horse_id' => $horse->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function push($user, array $ops)
    {
        return $this->actingAs($user)->postJson('/sync/push', ['device_uuid' => $this->device, 'operations' => $ops]);
    }

    private function op(string $entity, string $action, array $payload, ?string $opUuid = null): array
    {
        return ['op_uuid' => $opUuid ?? (string) Str::uuid(), 'entity' => $entity, 'action' => $action, 'payload' => $payload, 'created_at' => now()->toIso8601String()];
    }

    private function sessionPayload($horse, ?string $uuid = null): array
    {
        return ['uuid' => $uuid ?? (string) Str::uuid(), 'horse_id' => $horse->id, 'scheduled_at' => now()->utc()->toIso8601String(), 'session_type' => 'free', 'objective' => 'Hors ligne',
            'items' => [['uuid' => (string) Str::uuid(), 'name' => 'Pas rênes longues', 'phase' => 'warmup']]];
    }

    public function test_pull_returns_only_selected_and_authorized_horses(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization());
        $h1 = $this->horseFor($user, null, ['official_name' => 'SELECTIONNE', 'care_instructions' => 'Pommade matin']);
        $this->horseFor($user, null, ['official_name' => 'NON SELECTIONNE']);
        $this->select($user, $h1);

        $res = $this->actingAs($user)->postJson('/sync/pull', ['device_uuid' => $this->device])->assertOk();
        $res->assertJsonPath('horse_ids', [$h1->id])->assertJsonPath('horses.0.care_instructions', 'Pommade matin');
        $this->assertNotEmpty($res->json('exercises'));
        $this->assertNotNull($res->json('valid_until'));
        $this->assertDatabaseHas('sync_devices', ['user_id' => $user->id, 'device_uuid' => $this->device]);
    }

    public function test_offline_session_creation_is_idempotent(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $payload = $this->sessionPayload($horse);
        $op = $this->op('session', 'create', $payload);

        $this->push($user, [$op])->assertOk()->assertJsonPath('results.0.status', 'applied');
        // Même opération renvoyée (réponse perdue) : pas de doublon
        $this->push($user, [$op])->assertOk()->assertJsonPath('results.0.status', 'duplicate');
        // Nouvelle opération pour la même séance (même uuid) : pas de doublon non plus
        $this->push($user, [$this->op('session', 'create', $payload)])->assertJsonPath('results.0.status', 'duplicate');

        $this->assertSame(1, RidingSession::count());
        $session = RidingSession::first();
        $this->assertSame($payload['uuid'], $session->uuid);
        $this->assertSame(1, $session->items()->count());
        $this->assertSame($user->id, $session->created_by);
    }

    public function test_offline_live_session_and_debrief_sync(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $payload = $this->sessionPayload($horse);
        $itemUuid = $payload['items'][0]['uuid'];
        $ops = [
            $this->op('session', 'create', $payload),
            $this->op('session_exercise', 'update', ['uuid' => $itemUuid, 'changes' => ['status' => 'done', 'done_repetitions' => 3], 'base' => ['status' => 'pending', 'done_repetitions' => null]]),
            $this->op('comment', 'create', ['uuid' => (string) Str::uuid(), 'session_uuid' => $payload['uuid'], 'body' => 'Bonne séance']),
            $this->op('session', 'complete', ['uuid' => $payload['uuid'], 'debrief' => ['actual_minutes' => 35, 'rider_feeling' => 4, 'progress' => 'OK']]),
            $this->op('daily_log', 'create', ['uuid' => (string) Str::uuid(), 'horse_id' => $horse->id, 'appetite' => 'good', 'observations' => 'RAS']),
            $this->op('observation', 'create', ['uuid' => (string) Str::uuid(), 'horse_id' => $horse->id, 'body' => 'Petite plaie', 'severity' => 'watch']),
        ];
        $res = $this->push($user, $ops)->assertOk();
        $this->assertSame(['applied', 'applied', 'applied', 'applied', 'applied', 'applied'], array_column($res->json('results'), 'status'));

        $session = RidingSession::first();
        $this->assertTrue($session->isCompleted());
        $this->assertSame('done', $session->items()->first()->status);
        $this->assertSame(1, $session->comments()->count());
        $this->assertDatabaseHas('daily_logs', ['horse_id' => $horse->id, 'observations' => 'RAS']);
        $this->assertDatabaseHas('health_observations', ['horse_id' => $horse->id, 'severity' => 'watch']);
    }

    public function test_concurrent_edit_creates_conflict_instead_of_silent_overwrite(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user, null, ['general_notes' => 'Version initiale', 'particularities' => 'Tique au montoir']);
        // Quelqu'un modifie en ligne pendant que l'appareil est hors ligne.
        $horse->update(['general_notes' => 'Modifié en ligne']);

        $res = $this->push($user, [$this->op('horse', 'update', [
            'horse_id' => $horse->id,
            'changes' => ['general_notes' => 'Modifié hors ligne', 'current_location' => 'Pré du bas'],
            'base' => ['general_notes' => 'Version initiale', 'current_location' => null],
        ])])->assertOk();
        $res->assertJsonPath('results.0.status', 'conflict');
        $this->assertSame(['current_location'], $res->json('results.0.applied_fields'));

        $horse->refresh();
        $this->assertSame('Modifié en ligne', $horse->general_notes); // non écrasé
        $this->assertSame('Pré du bas', $horse->current_location); // champ sans conflit fusionné
        $conflict = SyncConflict::firstOrFail();
        $this->assertSame('Modifié hors ligne', $conflict->local_values['general_notes']);
        $this->assertSame('Modifié en ligne', $conflict->server_values['general_notes']);

        // Décision explicite de l'utilisateur
        $this->actingAs($user)->getJson('/sync/conflits')->assertJsonCount(1, 'conflicts');
        $this->actingAs($user)->postJson("/sync/conflits/{$conflict->id}", ['choice' => 'local'])->assertOk();
        $this->assertSame('Modifié hors ligne', $horse->fresh()->general_notes);
        $this->assertSame('resolved_local', $conflict->fresh()->status);
    }

    public function test_non_editable_horse_fields_are_ignored_offline(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user, null, ['official_name' => 'ORIGINAL']);
        $this->push($user, [$this->op('horse', 'update', ['horse_id' => $horse->id, 'changes' => ['official_name' => 'PIRATE', 'organization_id' => 999], 'base' => []])])->assertOk();
        $this->assertSame('ORIGINAL', $horse->fresh()->official_name);
        $this->assertNotSame(999, $horse->fresh()->organization_id);
    }

    public function test_revoked_access_rejects_operations_and_purges_on_pull(): void
    {
        $owner = $this->user();
        $rider = $this->user();
        $horse = $this->horseFor($owner);
        $grant = HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => Perm::halfLeasePreset()]);
        $this->select($rider, $horse);
        $this->actingAs($rider)->postJson('/sync/pull', ['device_uuid' => $this->device])->assertJsonPath('horse_ids', [$horse->id]);

        $grant->update(['revoked_at' => now()]);
        $this->push($rider, [$this->op('session', 'create', $this->sessionPayload($horse))])
            ->assertJsonPath('results.0.status', 'rejected');
        $this->assertSame(0, RidingSession::count());
        $this->actingAs($rider)->postJson('/sync/pull', ['device_uuid' => $this->device])->assertJsonPath('horse_ids', [])->assertJsonPath('horses', []);
    }

    public function test_expired_subscription_rejects_offline_writes(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        SubscriptionPlan::where('is_default_free', true)->update(['is_default_free' => false]);
        $this->grantLicense($user->personalOrganization(), 'particulier', 'expired');

        $res = $this->push($user, [$this->op('session', 'create', $this->sessionPayload($horse))])->assertOk();
        $res->assertJsonPath('results.0.status', 'rejected');
        $this->assertStringContainsString('expiré', $res->json('results.0.message'));
        $this->select($user, $horse);
        $this->actingAs($user)->postJson('/sync/pull', ['device_uuid' => $this->device])->assertJsonPath('horses.0.writable', false);
    }

    public function test_operation_from_another_user_cannot_be_replayed(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $horse = $this->horseFor($alice);
        $op = $this->op('session', 'create', $this->sessionPayload($horse));
        $this->push($alice, [$op])->assertJsonPath('results.0.status', 'applied');
        $this->push($bob, [$op])->assertJsonPath('results.0.status', 'rejected');
    }

    public function test_unknown_operation_and_invalid_data_are_rejected_not_lost(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $res = $this->push($user, [
            $this->op('horse', 'delete', ['horse_id' => $horse->id]),
            $this->op('session', 'create', ['uuid' => 'pas-un-uuid', 'horse_id' => $horse->id]),
        ])->assertOk();
        $this->assertSame(['rejected', 'rejected'], array_column($res->json('results'), 'status'));
        $this->assertDatabaseCount('sync_operations', 2);
    }

    public function test_remote_wipe_is_signalled_and_acknowledged(): void
    {
        $user = $this->user();
        $this->actingAs($user)->postJson('/sync/pull', ['device_uuid' => $this->device])->assertJsonPath('wipe', false);
        $device = SyncDevice::firstOrFail();
        $this->actingAs($user)->post("/parametres/appareils/{$device->id}/purger")->assertRedirect();
        $this->actingAs($user)->postJson('/sync/pull', ['device_uuid' => $this->device])->assertJsonPath('wipe', true);
        $this->push($user, [])->assertStatus(422); // opérations requises
        $this->actingAs($user)->postJson('/sync/pull', ['device_uuid' => $this->device, 'ack_wipe' => true])->assertJsonPath('wipe', false);
    }

    public function test_csrf_token_refresh_endpoint(): void
    {
        $user = $this->user();
        $this->actingAs($user)->getJson('/sync/session')->assertOk()->assertJsonStructure(['user_id', 'csrf_token']);
    }

    public function test_offline_shell_renders(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/hors-ligne')->assertOk()->assertSee('stableApp', false)->assertSee('data-offline-shell', false);
    }
}
