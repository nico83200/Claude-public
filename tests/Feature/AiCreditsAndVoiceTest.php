<?php

namespace Tests\Feature;

use App\Models\AssistantCreditPurchase;
use App\Models\AssistantRequest;
use App\Models\SubscriptionPlan;
use App\Notifications\AppNotification;
use App\Services\Assistant\ClaudeGateway;
use App\Services\Assistant\Speaker;
use App\Services\Billing\StripeGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\Support\FakeClaude;
use Tests\TestCase;

/** Crédits IA achetés quand le quota est épuisé, alerte de crédits ElevenLabs, lecture vocale plus rapide. */
class AiCreditsAndVoiceTest extends TestCase
{
    private function webhook(array $event)
    {
        $payload = json_encode($event);
        $t = time();
        $sig = hash_hmac('sha256', "$t.$payload", 'whsec_test_secret');

        return $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t=$t,v1=$sig", 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    private function gateway()
    {
        config(['services.stripe.secret' => 'sk_test_x']);
        $gateway = Mockery::mock(StripeGateway::class)->makePartial();
        $gateway->shouldReceive('configured')->andReturn(true);
        $this->app->instance(StripeGateway::class, $gateway);

        return $gateway;
    }

    public function test_exhausted_ai_balance_is_explained_and_credits_can_be_bought_then_used(): void
    {
        config(['services.anthropic.key' => 'sk-ant-test']);
        SubscriptionPlan::where('slug', 'particulier')->update(['assistant_monthly' => 1]);
        $user = $this->user(['name' => 'Léa Martin']);
        $org = $user->personalOrganization();
        $this->grantLicense($org, 'particulier');
        $this->horseFor($user);
        $this->app->instance(ClaudeGateway::class, new FakeClaude([['text' => 'Première réponse.'], ['text' => 'Réponse payée par un crédit.']]));
        $gateway = $this->gateway();

        $this->actingAs($user)->postJson('/assistant/consigne', ['text' => 'Bonjour'])->assertOk();
        // Quota du mois atteint : explication et proposition d'achat.
        $status = $this->actingAs($user)->getJson('/assistant/etat')->assertJson(['available' => false])->json();
        $this->assertSame('Solde IA épuisé', $status['exhausted']['title']);
        $this->assertTrue($status['exhausted']['can_buy']);
        $this->assertStringContainsString('#credits-ia', $status['exhausted']['buy_url']);
        $this->assertStringContainsString('4,90 €', $status['exhausted']['from']);
        $this->actingAs($user)->postJson('/assistant/consigne', ['text' => 'Encore'])->assertStatus(403)->assertJsonFragment(['message' => 'Solde IA épuisé pour l\'espace '.$org->name.' : le quota de consignes de votre offre est atteint pour ce mois-ci. Il se renouvelle le 1er du mois ; d\'ici là, vous pouvez acheter des crédits IA supplémentaires (Abonnement › Crédits IA).']);
        $this->actingAs($user)->get('/dashboard')->assertSee('data-ai-exhausted', false);
        $this->actingAs($user)->get('/abonnement')->assertOk()->assertSee('data-ai-credits', false)->assertSee('50 crédits');

        // Achat : paiement unique sur Stripe Checkout.
        $gateway->shouldReceive('createCustomer')->once()->andReturn('cus_credits');
        $gateway->shouldReceive('createCheckoutSession')->once()->withArgs(fn ($p) => $p['mode'] === 'payment' && $p['line_items'][0]['price_data']['unit_amount'] === 490
            && $p['metadata']['kind'] === 'assistant_credits' && $p['client_reference_id'] === (string) $org->id && str_contains($p['success_url'], '{CHECKOUT_SESSION_ID}'))
            ->andReturn(['id' => 'cs_credits_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_credits_1']);
        $this->actingAs($user)->post('/abonnement/credits-ia', ['pack' => 0])->assertRedirect('https://checkout.stripe.com/c/pay/cs_credits_1');
        $this->assertDatabaseHas('assistant_credit_purchases', ['stripe_session_id' => 'cs_credits_1', 'status' => 'pending', 'credits' => 50]);
        $this->assertSame(0, $org->fresh()->assistant_credits, 'Rien n\'est crédité avant le paiement.');

        // Retour de Stripe sans paiement confirmé : toujours rien.
        $gateway->shouldReceive('retrieveCheckoutSession')->with('cs_credits_1')->andReturn(['id' => 'cs_credits_1', 'mode' => 'payment', 'payment_status' => 'unpaid', 'client_reference_id' => (string) $org->id, 'amount_total' => 490, 'metadata' => ['kind' => 'assistant_credits']]);
        $this->actingAs($user)->get('/abonnement/credits-ia/retour?session_id=cs_credits_1')->assertRedirect();
        $this->assertSame(0, $org->fresh()->assistant_credits);

        // Paiement confirmé par le webhook signé, une seule fois même si l'événement est rejoué.
        $event = ['id' => 'evt_credits', 'object' => 'event', 'type' => 'checkout.session.completed', 'created' => time(), 'livemode' => false, 'data' => ['object' => [
            'id' => 'cs_credits_1', 'object' => 'checkout.session', 'mode' => 'payment', 'payment_status' => 'paid', 'client_reference_id' => (string) $org->id,
            'customer' => 'cus_credits', 'amount_total' => 490, 'payment_intent' => 'pi_1', 'metadata' => ['kind' => 'assistant_credits', 'organization_id' => (string) $org->id, 'credits' => '9999'],
        ]]];
        $this->webhook($event)->assertOk();
        $this->webhook(['id' => 'evt_credits_2'] + $event)->assertOk();
        $this->assertSame(50, $org->fresh()->assistant_credits, 'Quantité tirée de l\'achat enregistré, jamais des métadonnées.');
        $this->assertSame('paid', AssistantCreditPurchase::first()->status);

        // Les crédits débloquent l'assistant ; chaque demande au-delà du quota en consomme un.
        $this->actingAs($user)->getJson('/assistant/etat')->assertJson(['available' => true, 'remaining' => 50]);
        $this->actingAs($user)->postJson('/assistant/consigne', ['text' => 'Encore'])->assertOk()->assertJson(['remaining' => 49]);
        $this->assertTrue(AssistantRequest::latest('id')->first()->paid_with_credit);
        // Demande en erreur : crédit rendu.
        $failed = AssistantRequest::create(['user_id' => $user->id, 'organization_id' => $org->id, 'conversation_id' => (string) \Illuminate\Support\Str::uuid(), 'input_text' => 'x', 'input_mode' => 'text']);
        $this->assertSame(48, $org->fresh()->assistant_credits);
        $failed->update(['status' => 'error']);
        $this->assertSame(49, $org->fresh()->assistant_credits);
        $this->actingAs($user)->get('/abonnement')->assertSee('Achats de crédits');
    }

    public function test_credit_purchase_requires_billing_permission_and_admin_can_set_packs(): void
    {
        config(['services.anthropic.key' => 'sk-ant-test']);
        $this->gateway();
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->put(route('admin.assistant.update'), [
            'enabled' => '1', 'model' => array_key_first(\App\Services\Assistant\AssistantConfig::MODELS), 'effort' => array_key_first(\App\Services\Assistant\AssistantConfig::EFFORTS),
            'session_min' => 30, 'session_max' => 60, 'credits_enabled' => '1',
            'packs' => [['credits' => 100, 'price' => '8.50'], ['credits' => '', 'price' => ''], ['credits' => 20, 'price' => '2']],
        ])->assertSessionHasNoErrors();
        $this->assertSame([['credits' => 20, 'price_cents' => 200], ['credits' => 100, 'price_cents' => 850]], \App\Services\Assistant\AssistantCredits::packs());

        // Un membre sans droit sur l'abonnement ne peut pas acheter.
        $manager = $this->user();
        $stable = $this->stable($manager);
        $rider = $this->user();
        app(\App\Services\OrganizationService::class)->addMember($stable, $rider, 'rider');
        $rider->forceFill(['current_organization_id' => $stable->id])->save();
        $this->actingAs($rider)->post('/abonnement/credits-ia', ['pack' => 0])->assertForbidden();
        $this->assertDatabaseCount('assistant_credit_purchases', 0);
    }

    public function test_elevenlabs_out_of_credits_shows_technical_message_and_alerts_super_admins(): void
    {
        Notification::fake();
        config(['services.anthropic.key' => 'sk-ant-test', 'services.elevenlabs.key' => 'xi-test']);
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $this->horseFor($user);
        Http::fake(['api.elevenlabs.io/*' => Http::response(['detail' => ['status' => 'quota_exceeded', 'message' => 'This request exceeds your quota of 10000. You have 3 credits remaining.']], 401)]);
        $this->app->instance(ClaudeGateway::class, new FakeClaude([['text' => 'C\'est noté pour mardi.']]));
        $id = $this->actingAs($user)->postJson('/assistant/consigne', ['text' => 'Maréchal mardi', 'mode' => 'voice'])->json('request_id');

        $res = $this->actingAs($user)->get('/assistant/voix/'.$id.'?partie=0')->assertStatus(503)->assertJson(['code' => 'voice_credits']);
        $this->assertStringContainsString('n\'a plus assez de crédits', $res->json('message'));
        $this->assertStringNotContainsString('quota of 10000', $res->json('message'), 'Le détail technique reste réservé au super-administrateur.');
        Notification::assertSentTo($admin, AppNotification::class, fn ($n) => $n->kind === 'system_alert' && str_contains($n->title, 'Crédits ElevenLabs épuisés')
            && str_contains($n->body, 'quota_exceeded') && in_array('mail', $n->via($admin), true));
        Notification::assertNotSentTo($user, AppNotification::class);
        // Réponse immédiate ensuite, sans nouvel appel à ElevenLabs.
        $this->actingAs($user)->get('/assistant/voix/'.$id.'?partie=0')->assertStatus(503);
        Http::assertSentCount(1);

        // Dictée : même explication, une seule alerte par période de 6 heures.
        Notification::fake();
        $this->actingAs($user)->post('/assistant/transcription', ['audio' => UploadedFile::fake()->create('consigne.webm', 20, 'audio/webm')])
            ->assertStatus(503)->assertJson(['code' => 'voice_credits'])->assertJsonFragment(['message' => 'Dictée vocale indisponible : le service vocal (ElevenLabs) n\'a plus assez de crédits pour le moment. L\'administrateur de Jack&Cie a été prévenu. Écrivez votre consigne en attendant.']);
        Notification::assertNothingSent();
    }

    public function test_reply_is_read_in_parts_so_the_first_sentence_starts_quickly(): void
    {
        config(['services.anthropic.key' => 'sk-ant-test', 'services.elevenlabs.key' => 'xi-test']);
        $speaker = app(Speaker::class);
        $this->assertSame(['C\'est noté. Le maréchal passe mardi à 9 h pour Uni.'], $speaker->parts('C\'est noté. Le maréchal passe mardi à 9 h pour Uni.'));
        $long = 'J\'ai ajouté la séance de dressage de demain. '.str_repeat('Elle commence par dix minutes de pas rênes longues puis des transitions au trot. ', 5).'Bonne séance !';
        $parts = $speaker->parts($long);
        $this->assertGreaterThan(1, count($parts));
        $this->assertSame('J\'ai ajouté la séance de dressage de demain.', $parts[0], 'Première phrase seule : générée et jouée en premier.');
        $this->assertSame(mb_substr(preg_replace('/\s+/', ' ', $long), 0, Speaker::MAX_CHARS), implode(' ', $parts));

        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $this->horseFor($user);
        Http::fake(['api.elevenlabs.io/v1/text-to-speech/*' => Http::sequence()->push('mp3-part-0', 200, ['Content-Type' => 'audio/mpeg'])->push('mp3-part-1', 200, ['Content-Type' => 'audio/mpeg'])->push('mp3-part-2', 200)->push('mp3-part-3', 200)]);
        $this->app->instance(ClaudeGateway::class, new FakeClaude([['text' => $long]]));
        $id = $this->actingAs($user)->postJson('/assistant/consigne', ['text' => 'Séance demain', 'mode' => 'voice'])->json('request_id');

        $first = $this->actingAs($user)->get('/assistant/voix/'.$id)->assertOk()->assertHeader('Content-Type', 'audio/mpeg')->assertHeader('X-Speech-Parts', (string) count($parts));
        $this->assertSame('mp3-part-0', $first->getContent());
        Http::assertSent(fn ($r) => $r['text'] === $parts[0] && ! isset($r['previous_text']) && $r['next_text'] === mb_substr($parts[1], 0, 300));
        $this->assertSame('mp3-part-1', $this->actingAs($user)->get('/assistant/voix/'.$id.'?partie=1')->assertOk()->getContent());
        Http::assertSent(fn ($r) => $r['text'] === $parts[1] && $r['previous_text'] === $parts[0]);
        $this->actingAs($user)->get('/assistant/voix/'.$id.'?partie='.count($parts))->assertNotFound();
    }
}
