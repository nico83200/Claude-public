<?php

namespace Tests\Feature;

use App\Models\AppUpdate;
use Tests\TestCase;

/** Mise à jour : un second envoi du bouton « Installer » renvoie vers le suivi, sans erreur 422 brute. */
class AdminUpdateResubmitTest extends TestCase
{
    public function test_resubmitting_install_redirects_to_the_update_page(): void
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $as = fn () => $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

        $running = AppUpdate::create(['user_id' => $admin->id, 'status' => 'applying', 'from_version' => '2.15.2', 'to_version' => '2.15.3']);
        $as()->post("/admin/mises-a-jour/{$running->id}/installer", ['confirm' => '1'])
            ->assertRedirect("/admin/mises-a-jour/{$running->id}")->assertSessionHas('success', 'L\'installation est déjà en cours : suivez son avancement ici.');

        $done = AppUpdate::create(['user_id' => $admin->id, 'status' => 'done', 'from_version' => '2.15.2', 'to_version' => '2.15.3']);
        $as()->post("/admin/mises-a-jour/{$done->id}/installer", ['confirm' => '1'])
            ->assertRedirect("/admin/mises-a-jour/{$done->id}")->assertSessionHas('success', 'Jack&Cie 2.15.3 est déjà installée.');

        $rejected = AppUpdate::create(['user_id' => $admin->id, 'status' => 'rejected', 'to_version' => '2.15.3']);
        $as()->post("/admin/mises-a-jour/{$rejected->id}/installer", ['confirm' => '1'])
            ->assertRedirect("/admin/mises-a-jour/{$rejected->id}")->assertSessionHas('error');

        $this->assertSame('applying', $running->fresh()->status);
        $this->assertSame('done', $done->fresh()->status);
    }

    public function test_unprocessable_actions_show_a_branded_page(): void
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $done = AppUpdate::create(['user_id' => $admin->id, 'status' => 'done', 'to_version' => '2.15.3']);

        // Suppression d'un paquet déjà installé : refus 422 avec une page lisible.
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->delete("/admin/mises-a-jour/{$done->id}")
            ->assertStatus(422)->assertSee('Action impossible')->assertSee('Revenir à la page précédente');
    }
}
