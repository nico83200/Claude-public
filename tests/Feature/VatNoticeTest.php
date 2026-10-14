<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Micro-entreprise en franchise de TVA : prix affichés sans « TTC », avec la mention de l'article 293 B. */
class VatNoticeTest extends TestCase
{
    public function test_prices_show_vat_exemption_notice_instead_of_ttc(): void
    {
        $this->get('/tarifs')->assertOk()->assertSee('TVA non applicable, article 293 B du CGI.')->assertDontSee('TTC');
        $this->actingAs($this->user())->get('/abonnement')->assertOk()->assertSee('data-vat-notice', false)->assertSee('293 B du CGI')->assertDontSee('TTC');
    }
}
