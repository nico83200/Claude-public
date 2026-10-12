<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\SubscriptionPlan;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Messages ponctuels du super-administrateur : à tous ou selon l'offre. */
class AdminAnnouncementTest extends TestCase
{
    public function test_super_admin_sends_to_everyone_or_by_plan(): void
    {
        Notification::fake();
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $free = $this->user();
        $suspended = $this->user();
        $suspended->forceFill(['suspended_at' => now()])->save();
        $as = fn () => $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

        $as()->get('/admin/messages')->assertOk()->assertSee('data-announcement-form', false)->assertSee('Propriétaire');

        // Selon l'offre : seulement les espaces « Propriétaire ».
        $plan = SubscriptionPlan::where('slug', 'particulier')->value('id');
        $as()->post('/admin/messages', ['title' => 'Offre EquitaLyon', 'body' => '-20 % ce week-end', 'url' => '/abonnement', 'audience' => 'plans', 'plans' => [$plan]])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        Notification::assertSentTo($owner, AppNotification::class, fn ($n) => $n->kind === 'announcement' && $n->title === 'Offre EquitaLyon' && str_ends_with($n->url, '/abonnement') && ! $n->mail);
        Notification::assertNotSentTo($free, AppNotification::class);

        // Offre gratuite : les espaces sans offre payante.
        $freePlan = SubscriptionPlan::where('is_default_free', true)->value('id');
        $as()->post('/admin/messages', ['title' => 'Essayez Propriétaire', 'body' => '30 jours offerts', 'audience' => 'plans', 'plans' => [$freePlan]]);
        Notification::assertSentTo($free, AppNotification::class, fn ($n) => $n->title === 'Essayez Propriétaire');
        Notification::assertNotSentTo($owner, AppNotification::class, fn ($n) => $n->title === 'Essayez Propriétaire');

        // Tout le monde (sauf comptes suspendus), avec e-mail.
        $as()->post('/admin/messages', ['title' => 'Maintenance', 'body' => 'Dimanche 8 h', 'audience' => 'all', 'with_email' => 1]);
        foreach ([$admin, $owner, $free] as $u) {
            Notification::assertSentTo($u, AppNotification::class, fn ($n) => $n->title === 'Maintenance' && $n->mail);
        }
        Notification::assertNotSentTo($suspended, AppNotification::class);
        $this->assertSame(3, Announcement::count());
        $as()->get('/admin/messages')->assertSee('Maintenance')->assertSee('data-announcement', false);

        // Validation et accès.
        $as()->post('/admin/messages', ['title' => 'X', 'body' => 'Y', 'audience' => 'plans'])->assertSessionHasErrors('plans');
        $as()->post('/admin/messages', ['title' => 'X', 'body' => 'Y', 'audience' => 'all', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->actingAs($owner)->get('/admin/messages')->assertNotFound();
        $this->actingAs($owner)->post('/admin/messages', ['title' => 'X', 'body' => 'Y', 'audience' => 'all'])->assertNotFound();
        $this->assertSame(3, Announcement::count());
    }
}
