<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Justificatifs : image et PDF affichés dans la visionneuse de l'application, téléchargement sur demande. */
class ReceiptViewerTest extends TestCase
{
    public function test_receipts_open_in_app_viewer_and_download_only_on_request(): void
    {
        Storage::fake('local');
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $horse = $this->horseFor($user);
        $cat = ExpenseCategory::value('id');
        $pdf = UploadedFile::fake()->createWithContent('facture-marechal.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $cat, 'spent_on' => now()->toDateString(), 'amount' => '95', 'receipt' => $pdf])->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $cat, 'spent_on' => now()->toDateString(), 'amount' => '20', 'receipt' => UploadedFile::fake()->image('ticket.jpg')])->assertSessionHasNoErrors();
        [$withPdf, $withImage] = Expense::with('documents')->orderBy('id')->get()->all();
        $pdfDoc = $withPdf->documents->first();
        $imgDoc = $withImage->documents->first();

        // Liens : ouverts dans la visionneuse (image ou PDF), avec un lien de téléchargement.
        $page = $this->actingAs($user)->get('/budget/'.$withPdf->id)->assertOk()->getContent();
        $this->assertStringContainsString('data-lightbox-type="pdf"', $page);
        $this->assertStringContainsString(route('budget.receipt', [$withPdf, $pdfDoc]).'?telecharger=1', $page);
        $this->actingAs($user)->get('/budget/'.$withImage->id)->assertSee('data-lightbox-type="image"', false);
        $this->actingAs($user)->get('/budget')->assertSee('data-receipt-link', false);

        // Affichage dans la page (inline), protégé ; téléchargement seulement avec ?telecharger=1.
        $inline = $this->actingAs($user)->get(route('budget.receipt', [$withPdf, $pdfDoc]))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Security-Policy', 'sandbox');
        $this->assertStringStartsWith('inline', $inline->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('image/', $this->actingAs($user)->get(route('budget.receipt', [$withImage, $imgDoc]))->assertOk()->headers->get('Content-Type'));
        $download = $this->actingAs($user)->get(route('budget.receipt', [$withPdf, $pdfDoc]).'?telecharger=1')->assertOk();
        $this->assertStringStartsWith('attachment', $download->headers->get('Content-Disposition'));

        // Toujours réservé aux personnes qui voient la dépense.
        $this->actingAs($this->user())->get(route('budget.receipt', [$withPdf, $pdfDoc]))->assertNotFound();
    }
}
