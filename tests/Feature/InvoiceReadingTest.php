<?php

namespace Tests\Feature;

use App\Models\AssistantRequest;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\Assistant\ClaudeGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeClaude;
use Tests\TestCase;

/** Facture photographiée lue par l'assistant : vérification puis dépense (+ soin) avec justificatif. */
class InvoiceReadingTest extends TestCase
{
    private function setUpUser(): array
    {
        Storage::fake('local');
        config(['services.anthropic.key' => 'sk-ant-test']);
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $horse = $this->horseFor($user, null, ['official_name' => 'UNI DU BOIS', 'usual_name' => 'Uni']);

        return [$user, $horse];
    }

    private function fake(?array $invoice): FakeClaude
    {
        $fake = new FakeClaude([]);
        $fake->invoice = $invoice;
        $this->app->instance(ClaudeGateway::class, $fake);

        return $fake;
    }

    private function vetInvoice(array $extra = []): array
    {
        return array_merge(['is_invoice' => true, 'supplier' => 'Clinique vétérinaire du Var', 'invoice_date' => now()->subDays(2)->toDateString(), 'total_amount' => 85.5,
            'currency' => 'EUR', 'expense_category' => 'veterinary', 'care_category' => 'vaccination', 'horse_name' => 'Uni', 'summary' => 'Rappel vaccin grippe-tétanos',
            'lines' => [['label' => 'Vaccin Equip FT', 'amount' => 45.5], ['label' => 'Visite', 'amount' => 40]], 'confidence' => 'high'], $extra);
    }

    public function test_photo_is_read_reviewed_and_saved_as_expense_with_receipt_and_care(): void
    {
        [$user, $horse] = $this->setUpUser();
        $fake = $this->fake($this->vetInvoice());

        $this->actingAs($user)->get('/budget')->assertOk()->assertSee('Une facture à noter ?')->assertSee('data-invoice-card', false);
        $res = $this->actingAs($user)->post('/budget/facture', ['invoice' => UploadedFile::fake()->image('facture.jpg', 2400, 3200)]);
        $res->assertRedirect();
        $review = $res->headers->get('Location');
        $this->assertSame('image/jpeg', $fake->calls[0]['invoice']['mediaType']);
        $this->assertSame(['Uni'], $fake->calls[0]['invoice']['horseNames']);
        $this->assertDatabaseHas('assistant_requests', ['user_id' => $user->id, 'input_mode' => 'document', 'status' => 'done']);

        // Formulaire pré-rempli : cheval reconnu, montant, date, soin proposé ; rien n'est encore enregistré.
        $this->actingAs($user)->get($review)->assertOk()->assertSee('Clinique vétérinaire du Var')->assertSee('Lecture sûre')
            ->assertSee('value="85.50"', false)->assertSee('Vaccin Equip FT')->assertSee('Enregistrer aussi le soin');
        $this->assertSame(0, Expense::count());

        $token = basename(parse_url($review, PHP_URL_PATH));
        $vet = ExpenseCategory::where('key', 'veterinary')->value('id');
        $vacc = CareCategory::where('key', 'vaccination')->value('id');
        $this->actingAs($user)->post("/budget/facture/{$token}", ['horse_id' => $horse->id, 'expense_category_id' => $vet, 'amount' => '85.50', 'spent_on' => now()->subDays(2)->toDateString(),
            'supplier' => 'Clinique vétérinaire du Var', 'comment' => 'Rappel vaccin', 'paid' => '1', 'with_care' => '1', 'care_category_id' => $vacc])
            ->assertRedirect()->assertSessionHas('success');

        $expense = Expense::with('documents')->firstOrFail();
        $this->assertSame('85.50', $expense->amount);
        $this->assertCount(1, $expense->documents);
        Storage::disk('local')->assertExists($expense->documents[0]->path);
        $care = CareRecord::findOrFail($expense->care_record_id);
        $this->assertSame($horse->id, $care->horse_id);
        $this->assertSame($vacc, $care->care_category_id);
        // Lecture à usage unique.
        $this->actingAs($user)->get($review)->assertNotFound();
    }

    public function test_not_an_invoice_or_unreadable_file_saves_nothing(): void
    {
        [$user] = $this->setUpUser();
        $this->fake($this->vetInvoice(['is_invoice' => false]));
        $this->actingAs($user)->from('/budget')->post('/budget/facture', ['invoice' => UploadedFile::fake()->image('cheval.jpg')])
            ->assertRedirect('/budget')->assertSessionHas('warning');

        $this->fake(null);
        $this->actingAs($user)->from('/budget')->post('/budget/facture', ['invoice' => UploadedFile::fake()->create('facture.pdf', 50, 'application/pdf')])
            ->assertRedirect('/budget')->assertSessionHas('error');
        $this->actingAs($user)->from('/budget')->post('/budget/facture', ['invoice' => UploadedFile::fake()->create('facture.docx', 10)])->assertSessionHasErrors('invoice');
        $this->assertSame(0, Expense::count());
    }

    public function test_review_is_private_and_requires_the_assistant_in_the_plan(): void
    {
        [$user] = $this->setUpUser();
        $this->fake($this->vetInvoice());
        $review = $this->actingAs($user)->post('/budget/facture', ['invoice' => UploadedFile::fake()->image('f.jpg')])->headers->get('Location');
        $this->actingAs($this->user())->get($review)->assertNotFound();

        // Offre Découverte : pas de lecture, proposition d'offre à la place.
        $free = $this->user();
        $this->horseFor($free);
        $this->actingAs($free)->from('/budget')->post('/budget/facture', ['invoice' => UploadedFile::fake()->image('f.jpg')])->assertSessionHas('upsell');
        $this->assertSame(1, AssistantRequest::count());
    }
}
