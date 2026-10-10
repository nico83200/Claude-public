<?php

use App\Http\Controllers;
use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

// --- Installeur du premier lancement (fermé définitivement après installation)
Route::get('/install', [Controllers\InstallController::class, 'welcome'])->middleware('throttle:60,1')->name('install');
Route::prefix('install')->name('install.')->middleware('throttle:60,1')->group(function () {
    Route::post('/deverrouiller', [Controllers\InstallController::class, 'unlock'])->name('unlock');
    Route::get('/base-de-donnees', [Controllers\InstallController::class, 'database'])->name('database');
    Route::post('/base-de-donnees', [Controllers\InstallController::class, 'saveDatabase'])->name('database.save');
    Route::get('/application', [Controllers\InstallController::class, 'application'])->name('application');
    Route::post('/application', [Controllers\InstallController::class, 'saveApplication'])->name('application.save');
    Route::get('/administrateur', [Controllers\InstallController::class, 'admin'])->name('admin');
    Route::post('/administrateur', [Controllers\InstallController::class, 'saveAdmin'])->name('admin.save');
    Route::get('/termine', [Controllers\InstallController::class, 'done'])->middleware('signed')->name('done');
});

// --- Pages publiques -------------------------------------------------------
Route::get('/', [Controllers\PublicController::class, 'home'])->name('home');
Route::get('/tarifs', [Controllers\PublicController::class, 'pricing'])->name('pricing');
Route::get('/confidentialite', [Controllers\PublicController::class, 'privacy'])->name('legal.privacy');
Route::get('/conditions', [Controllers\PublicController::class, 'terms'])->name('legal.terms');
Route::view('/application-mobile', 'public.install')->name('app.install');

// Webhook Stripe (signature vérifiée, CSRF exclu dans bootstrap/app.php)
Route::post('/stripe/webhook', Controllers\StripeWebhookController::class)->name('stripe.webhook');

// Invitations (consultables avant connexion)
Route::get('/invitations/{token}', [Controllers\InvitationController::class, 'show'])->name('invitations.show');

// Coquille hors ligne : servie depuis le cache du Service Worker
Route::get('/hors-ligne', [Controllers\OfflineController::class, 'app'])->middleware(['auth', 'verified', 'org'])->name('offline.app');

Route::middleware(['auth', 'verified', 'org'])->group(function () {
    Route::post('/invitations/{token}/accepter', [Controllers\InvitationController::class, 'accept'])->name('invitations.accept');
    Route::post('/invitations/{token}/refuser', [Controllers\InvitationController::class, 'decline'])->name('invitations.decline');

    Route::get('/dashboard', Controllers\DashboardController::class)->name('dashboard');
    Route::get('/recherche', Controllers\SearchController::class)->name('search');

    // Organisations / écuries
    Route::post('/espaces/changer', [Controllers\OrganizationController::class, 'switch'])->name('organizations.switch');
    Route::get('/espaces/nouveau', [Controllers\OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('/espaces', [Controllers\OrganizationController::class, 'store'])->name('organizations.store');
    Route::get('/organisation', [Controllers\OrganizationController::class, 'show'])->name('organization.show');
    Route::put('/organisation', [Controllers\OrganizationController::class, 'update'])->name('organization.update');
    Route::post('/organisation/membres', [Controllers\OrganizationMemberController::class, 'invite'])->middleware('throttle:invitations')->name('organization.members.invite');
    Route::put('/organisation/membres/{member}', [Controllers\OrganizationMemberController::class, 'update'])->name('organization.members.update');
    Route::delete('/organisation/membres/{member}', [Controllers\OrganizationMemberController::class, 'destroy'])->name('organization.members.destroy');
    Route::delete('/organisation/invitations/{invitation}', [Controllers\OrganizationMemberController::class, 'revokeInvitation'])->name('organization.invitations.revoke');
    Route::post('/organisation/roles', [Controllers\OrganizationRoleController::class, 'store'])->name('organization.roles.store');
    Route::put('/organisation/roles/{role}', [Controllers\OrganizationRoleController::class, 'update'])->name('organization.roles.update');
    Route::delete('/organisation/roles/{role}', [Controllers\OrganizationRoleController::class, 'destroy'])->name('organization.roles.destroy');
    Route::post('/organisation/rattachements/{assignment}/accepter', [Controllers\AssignmentController::class, 'accept'])->name('assignments.accept');
    Route::post('/organisation/rattachements/{assignment}/refuser', [Controllers\AssignmentController::class, 'decline'])->name('assignments.decline');
    Route::post('/rattachements/{assignment}/terminer', [Controllers\AssignmentController::class, 'end'])->name('assignments.end');

    // Recherche d'identité sur Internet
    Route::get('/chevaux/rechercher', [Controllers\HorseSearchController::class, 'form'])->name('horse-search.form');
    Route::post('/chevaux/rechercher', [Controllers\HorseSearchController::class, 'search'])->middleware('throttle:horse-search')->name('horse-search.search');
    Route::post('/chevaux/rechercher/apercu', [Controllers\HorseSearchController::class, 'preview'])->name('horse-search.preview');
    Route::post('/chevaux/rechercher/importer', [Controllers\HorseSearchController::class, 'import'])->name('horse-search.import');

    // Chevaux
    Route::resource('chevaux', Controllers\HorseController::class)->parameters(['chevaux' => 'horse'])->names('horses');
    Route::post('/chevaux/{horse}/archiver', [Controllers\HorseController::class, 'archive'])->name('horses.archive');
    Route::post('/chevaux/{horse}/restaurer', [Controllers\HorseController::class, 'unarchive'])->name('horses.unarchive');
    Route::post('/chevaux/{horse}/transferer', [Controllers\HorseController::class, 'transfer'])->name('horses.transfer');
    Route::post('/chevaux/{horse}/hors-ligne', [Controllers\HorseController::class, 'toggleOffline'])->name('horses.offline');

    Route::scopeBindings()->prefix('/chevaux/{horse}')->name('horses.')->group(function () {
        Route::get('/genealogie', [Controllers\PedigreeController::class, 'show'])->name('pedigree');
        Route::put('/genealogie', [Controllers\PedigreeController::class, 'update'])->name('pedigree.update');
        Route::post('/proprietaires', [Controllers\OwnershipController::class, 'store'])->name('ownerships.store');
        Route::post('/proprietaires/{ownership}/terminer', [Controllers\OwnershipController::class, 'end'])->name('ownerships.end');
        Route::post('/sources/{source}/{decision}', [Controllers\HorseSourceController::class, 'decide'])->whereIn('decision', ['confirm', 'reject'])->name('sources.decide');

        Route::post('/photos', [Controllers\HorsePhotoController::class, 'store'])->name('photos.store');
        Route::get('/photos/{photo}', [Controllers\HorsePhotoController::class, 'show'])->name('photos.show');
        Route::post('/photos/{photo}/principale', [Controllers\HorsePhotoController::class, 'makeMain'])->name('photos.main');
        Route::delete('/photos/{photo}', [Controllers\HorsePhotoController::class, 'destroy'])->name('photos.destroy');

        Route::get('/documents', [Controllers\HorseDocumentController::class, 'index'])->name('documents.index');
        Route::post('/documents', [Controllers\HorseDocumentController::class, 'store'])->name('documents.store');
        Route::get('/documents/{document}', [Controllers\HorseDocumentController::class, 'download'])->name('documents.download');
        Route::delete('/documents/{document}', [Controllers\HorseDocumentController::class, 'destroy'])->name('documents.destroy');

        Route::get('/sante', [Controllers\CareRecordController::class, 'index'])->name('care.index');
        Route::get('/soins/nouveau', [Controllers\CareRecordController::class, 'create'])->name('care.create');
        Route::post('/soins', [Controllers\CareRecordController::class, 'store'])->name('care.store');
        Route::get('/soins/{care}', [Controllers\CareRecordController::class, 'show'])->name('care.show');
        Route::get('/soins/{care}/modifier', [Controllers\CareRecordController::class, 'edit'])->name('care.edit');
        Route::put('/soins/{care}', [Controllers\CareRecordController::class, 'update'])->name('care.update');
        Route::delete('/soins/{care}', [Controllers\CareRecordController::class, 'destroy'])->name('care.destroy');

        Route::post('/traitements', [Controllers\TreatmentController::class, 'store'])->name('treatments.store');
        Route::put('/traitements/{treatment}', [Controllers\TreatmentController::class, 'update'])->name('treatments.update');
        Route::post('/traitements/{treatment}/administrer', [Controllers\TreatmentController::class, 'administer'])->name('treatments.administer');

        Route::post('/observations', [Controllers\ObservationController::class, 'store'])->name('observations.store');
        Route::post('/observations/{observation}/resoudre', [Controllers\ObservationController::class, 'resolve'])->name('observations.resolve');

        Route::get('/alimentation', [Controllers\FeedingController::class, 'show'])->name('feeding');
        Route::post('/alimentation', [Controllers\FeedingController::class, 'store'])->name('feeding.store');
        Route::get('/journal', [Controllers\DailyLogController::class, 'index'])->name('logs.index');
        Route::post('/journal', [Controllers\DailyLogController::class, 'store'])->name('logs.store');

        Route::get('/partage', [Controllers\HorseSharingController::class, 'show'])->name('sharing');
        Route::post('/partage/invitations', [Controllers\HorseSharingController::class, 'invite'])->middleware('throttle:invitations')->name('sharing.invite');
        Route::delete('/partage/invitations/{invitation}', [Controllers\HorseSharingController::class, 'revokeInvitation'])->name('sharing.invitations.revoke');
        Route::put('/partage/acces/{grant}', [Controllers\HorseSharingController::class, 'updateGrant'])->name('sharing.grants.update');
        Route::post('/partage/acces/{grant}/revoquer', [Controllers\HorseSharingController::class, 'revokeGrant'])->name('sharing.grants.revoke');
        Route::post('/partage/ecurie', [Controllers\AssignmentController::class, 'request'])->name('assignments.request');
        Route::put('/partage/ecurie/{assignment}', [Controllers\AssignmentController::class, 'update'])->name('assignments.update');
        Route::get('/historique', [Controllers\HorseSharingController::class, 'history'])->name('history');

        Route::get('/intervenants', [Controllers\ProfessionalController::class, 'forHorse'])->name('professionals');
        Route::post('/intervenants', [Controllers\ProfessionalController::class, 'attach'])->name('professionals.attach');
        Route::delete('/intervenants/{professional}', [Controllers\ProfessionalController::class, 'detach'])->withoutScopedBindings()->name('professionals.detach');

        Route::get('/export/fiche.pdf', [Controllers\ExportController::class, 'horsePdf'])->middleware('throttle:exports')->name('export.pdf');
        Route::get('/export/sante.pdf', [Controllers\ExportController::class, 'healthPdf'])->middleware('throttle:exports')->name('export.health');
        Route::get('/export/seances.{format}', [Controllers\ExportController::class, 'sessions'])->whereIn('format', ['csv', 'pdf'])->middleware('throttle:exports')->name('export.sessions');
    });

    Route::get('/sante', Controllers\HealthOverviewController::class)->name('health.index');

    // Intervenants
    Route::resource('intervenants', Controllers\ProfessionalController::class)->parameters(['intervenants' => 'professional'])->names('professionals')->except(['destroy']);
    Route::post('/intervenants/{professional}/archiver', [Controllers\ProfessionalController::class, 'archive'])->name('professionals.archive');

    // Calendrier
    Route::get('/calendrier', [Controllers\CalendarController::class, 'index'])->name('calendar.index');
    Route::post('/calendrier', [Controllers\CalendarController::class, 'store'])->name('calendar.store');
    Route::get('/calendrier/{event}', [Controllers\CalendarController::class, 'show'])->name('calendar.show');
    Route::put('/calendrier/{event}', [Controllers\CalendarController::class, 'update'])->name('calendar.update');
    Route::delete('/calendrier/{event}', [Controllers\CalendarController::class, 'destroy'])->name('calendar.destroy');

    // Séances
    Route::get('/seances', [Controllers\RidingSessionController::class, 'index'])->name('sessions.index');
    Route::get('/seances/nouvelle', [Controllers\RidingSessionController::class, 'create'])->name('sessions.create');
    Route::post('/seances', [Controllers\RidingSessionController::class, 'store'])->name('sessions.store');
    Route::get('/seances/{session}', [Controllers\RidingSessionController::class, 'show'])->name('sessions.show');
    Route::get('/seances/{session}/preparer', [Controllers\RidingSessionController::class, 'edit'])->name('sessions.edit');
    Route::put('/seances/{session}', [Controllers\RidingSessionController::class, 'update'])->name('sessions.update');
    Route::delete('/seances/{session}', [Controllers\RidingSessionController::class, 'destroy'])->name('sessions.destroy');
    Route::get('/seances/{session}/en-cours', [Controllers\RidingSessionController::class, 'live'])->name('sessions.live');
    Route::get('/seances/{session}/bilan', [Controllers\RidingSessionController::class, 'debrief'])->name('sessions.debrief');
    Route::put('/seances/{session}/bilan', [Controllers\RidingSessionController::class, 'saveDebrief'])->name('sessions.debrief.save');
    Route::post('/seances/{session}/dupliquer', [Controllers\RidingSessionController::class, 'duplicate'])->name('sessions.duplicate');
    Route::post('/seances/{session}/modele', [Controllers\SessionTemplateController::class, 'storeFromSession'])->name('sessions.template');
    Route::post('/seances/{session}/exercices', [Controllers\SessionExerciseController::class, 'store'])->name('sessions.exercises.store');
    Route::put('/seances/{session}/exercices/{item}', [Controllers\SessionExerciseController::class, 'update'])->scopeBindings()->name('sessions.exercises.update');
    Route::post('/seances/{session}/exercices/{item}/deplacer', [Controllers\SessionExerciseController::class, 'move'])->scopeBindings()->name('sessions.exercises.move');
    Route::delete('/seances/{session}/exercices/{item}', [Controllers\SessionExerciseController::class, 'destroy'])->scopeBindings()->name('sessions.exercises.destroy');
    Route::post('/seances/{session}/commentaires', [Controllers\RidingSessionController::class, 'comment'])->name('sessions.comments.store');
    Route::get('/modeles', [Controllers\SessionTemplateController::class, 'index'])->name('templates.index');
    Route::delete('/modeles/{template}', [Controllers\SessionTemplateController::class, 'destroy'])->name('templates.destroy');

    // Exercices
    Route::resource('exercices', Controllers\ExerciseController::class)->parameters(['exercices' => 'exercise'])->names('exercises')->except(['destroy']);
    Route::post('/exercices/{exercise}/dupliquer', [Controllers\ExerciseController::class, 'duplicate'])->name('exercises.duplicate');
    Route::post('/exercices/{exercise}/archiver', [Controllers\ExerciseController::class, 'archive'])->name('exercises.archive');

    // Budget
    Route::get('/budget', [Controllers\ExpenseController::class, 'index'])->name('budget.index');
    Route::post('/budget', [Controllers\ExpenseController::class, 'store'])->name('budget.store');
    Route::get('/budget/{expense}/modifier', [Controllers\ExpenseController::class, 'edit'])->name('budget.edit');
    Route::put('/budget/{expense}', [Controllers\ExpenseController::class, 'update'])->name('budget.update');
    Route::delete('/budget/{expense}', [Controllers\ExpenseController::class, 'destroy'])->name('budget.destroy');
    Route::get('/budget/{expense}/justificatif/{document}', [Controllers\ExpenseController::class, 'receipt'])->scopeBindings()->name('budget.receipt');
    Route::get('/budget/export.{format}', [Controllers\ExpenseController::class, 'export'])->whereIn('format', ['csv', 'pdf'])->middleware('throttle:exports')->name('budget.export');

    // Partages (vue d'ensemble)
    Route::get('/partages', [Controllers\HorseSharingController::class, 'overview'])->name('sharing.index');

    // Notifications
    Route::get('/notifications', [Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/lire', [Controllers\NotificationController::class, 'markAll'])->name('notifications.read-all');
    Route::get('/notifications/{id}', [Controllers\NotificationController::class, 'open'])->name('notifications.open');

    // Abonnement
    Route::get('/abonnement', [Controllers\BillingController::class, 'index'])->name('billing.index');
    Route::post('/abonnement/souscrire', [Controllers\BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('/abonnement/retour', [Controllers\BillingController::class, 'returned'])->name('billing.return');
    Route::post('/abonnement/portail', [Controllers\BillingController::class, 'portal'])->name('billing.portal');
    Route::post('/abonnement/changer', [Controllers\BillingController::class, 'swap'])->name('billing.swap');
    Route::post('/abonnement/resilier', [Controllers\BillingController::class, 'cancel'])->name('billing.cancel');
    Route::post('/abonnement/reprendre', [Controllers\BillingController::class, 'resume'])->name('billing.resume');

    // Paramètres du compte
    Route::get('/parametres', [Controllers\SettingsController::class, 'profile'])->name('settings.profile');
    Route::put('/parametres/profil', [Controllers\SettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::get('/parametres/securite', [Controllers\SettingsController::class, 'security'])->name('settings.security');
    Route::get('/parametres/donnees', [Controllers\SettingsController::class, 'privacy'])->name('settings.privacy');
    Route::get('/parametres/donnees/export', [Controllers\ExportController::class, 'personalData'])->middleware(['throttle:exports', 'password.confirm'])->name('settings.export');
    Route::post('/parametres/donnees/demande', [Controllers\SettingsController::class, 'dataRequest'])->name('settings.data-request');
    Route::get('/parametres/appareils', [Controllers\SettingsController::class, 'devices'])->name('settings.devices');
    Route::post('/parametres/appareils/{device}/purger', [Controllers\SettingsController::class, 'wipeDevice'])->name('settings.devices.wipe');

    // Synchronisation hors ligne (JSON, authentification par session + CSRF)
    Route::prefix('/sync')->middleware('throttle:sync')->name('sync.')->group(function () {
        Route::get('/session', [Controllers\SyncController::class, 'session'])->name('session');
        Route::post('/pull', [Controllers\SyncController::class, 'pull'])->name('pull');
        Route::post('/push', [Controllers\SyncController::class, 'push'])->name('push');
        Route::get('/conflits', [Controllers\SyncController::class, 'conflicts'])->name('conflicts');
        Route::post('/conflits/{conflict}', [Controllers\SyncController::class, 'resolve'])->name('conflicts.resolve');
    });
});

// --- Super-administration --------------------------------------------------
Route::middleware(['auth', 'verified', 'org', 'super-admin', 'password.confirm'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('/utilisateurs', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/utilisateurs/{user}', [Admin\UserController::class, 'show'])->name('users.show');
    Route::post('/utilisateurs/{user}/suspendre', [Admin\UserController::class, 'suspend'])->name('users.suspend');
    Route::post('/utilisateurs/{user}/reactiver', [Admin\UserController::class, 'reactivate'])->name('users.reactivate');
    Route::get('/organisations', [Admin\OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('/organisations/{organization}', [Admin\OrganizationController::class, 'show'])->name('organizations.show');
    Route::post('/organisations/{organization}/suspendre', [Admin\OrganizationController::class, 'suspend'])->name('organizations.suspend');
    Route::post('/organisations/{organization}/reactiver', [Admin\OrganizationController::class, 'reactivate'])->name('organizations.reactivate');
    Route::post('/organisations/{organization}/licences', [Admin\LicenseController::class, 'store'])->name('licenses.store');
    Route::put('/licences/{license}', [Admin\LicenseController::class, 'update'])->name('licenses.update');
    Route::resource('offres', Admin\PlanController::class)->parameters(['offres' => 'plan'])->names('plans')->except(['show', 'destroy']);
    Route::post('/offres/{plan}/archiver', [Admin\PlanController::class, 'archive'])->name('plans.archive');
    Route::get('/facturation', [Admin\BillingController::class, 'index'])->name('billing.index');
    Route::post('/facturation/evenements/{event}/retraiter', [Admin\BillingController::class, 'reprocess'])->name('billing.reprocess');
    Route::get('/parametres', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/parametres', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::get('/journal', [Admin\AuditController::class, 'index'])->name('audit.index');
    Route::get('/erreurs', [Admin\AuditController::class, 'errors'])->name('errors.index');
    Route::get('/demandes-rgpd', [Admin\DataRequestController::class, 'index'])->name('data-requests.index');
    Route::put('/demandes-rgpd/{dataRequest}', [Admin\DataRequestController::class, 'update'])->name('data-requests.update');
});
