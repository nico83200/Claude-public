<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Apparence : clair par défaut, sombre ou automatique, enregistrée sur le compte. */
class ThemeTest extends TestCase
{
    public function test_theme_is_light_by_default_and_saved_on_the_account(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/parametres')->assertOk()->assertSee('Apparence')->assertSee('var pref = null', false);

        $this->actingAs($user)->putJson('/parametres/apparence', ['theme' => 'dark'])->assertOk()->assertJsonPath('theme', 'dark');
        $this->assertSame('dark', $user->profile()->first()->theme);
        $this->actingAs($user->fresh())->get('/dashboard')->assertSee('var pref = "dark"', false);

        $this->actingAs($user)->put('/parametres/apparence', ['theme' => 'auto'])->assertSessionHas('success');
        $this->actingAs($user)->putJson('/parametres/apparence', ['theme' => 'violet'])->assertStatus(422);
        $this->assertSame('auto', $user->profile()->first()->theme);
    }
}
