<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Statistiques d'usage du super-administrateur : agrégats seulement. */
class AdminStatsTest extends TestCase
{
    public function test_stats_page_shows_aggregates_and_upsell_clicks(): void
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $user = $this->user(['name' => 'Zoé Secret', 'email' => 'zoe@exemple.fr']);
        $this->horseFor($user);
        $this->actingAs($user)->get('/abonnement?fonction=assistant')->assertOk();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/statistiques?jours=90')->assertOk()
            ->assertSee('Statistiques d&#039;usage', false)->assertSee('Parcours des nouveaux comptes')->assertSee('Ont ajouté un cheval')
            ->assertSee('Dictez au lieu de taper')->assertSee('Fonctions utilisées')
            ->assertDontSee('zoe@exemple.fr')->assertDontSee('Zoé Secret');

        $this->actingAs($user)->get('/admin/statistiques')->assertNotFound();
    }
}
