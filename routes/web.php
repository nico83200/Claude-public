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
Route::post('/push/recu', [Controllers\PushSubscriptionController::class, 'receipt'])->middleware('throttle:60,1')->name('push.receipt');
Route::get('/agenda/abonnement/{token}.ics', [Controllers\CalendarFeedController::class, 'show'])->where('token', '[A-Za-z0-9]{32,64}')->middleware('throttle:60,1')->name('calendar.feed');
Route::get('/app.webmanifest', Controllers\ManifestController::class)->name('manifest');
Route::get('/urgence/{token}', [Controllers\EmergencySheetController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:60,1')->name('emergency.public');
Route::get('/urgence/{token}/photo', [Controllers\EmergencySheetController::class, 'photo'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:60,1')->name('emergency.photo');
Route::get('/email/logo', [Controllers\Admin\MailDesignController::class, 'logo'])->middleware('throttle:120,1')->name('mail.logo');
Route::get('/maintenance/reprise', Controllers\MaintenanceResumeController::class)->middleware('throttle:6,1')->name('maintenance.resume');
Route::get('/tarifs', [Controllers\PublicController::class, 'pricing'])->name('pricing');
Route::get('/confidentialite', [Controllers\PublicController::class, 'privacy'])->name('legal.privacy');
Route::get('/conditions', [Controllers\PublicController::class, 'terms'])->name('legal.terms');
Route::view('/application-mobile', 'public.install')->name('app.install');

// Webhook Stripe (signature vérifiée, CSRF exclu dans bootstrap/app.php)
Route::post('/stripe/webhook', Controllers\StripeWebhookController::class)->name('stripe.webhook');

// Continuer avec Google / Apple (retour d'Apple en POST : CSRF exclu dans bootstrap/app.php, état vérifié)
Route::prefix('/connexion/{provider}')->whereIn('provider', ['google', 'apple'])->name('social.')->middleware('throttle:30,1')->group(function () {
    Route::get('/', [Controllers\SocialAuthController::class, 'redirect'])->name('redirect');
    Route::match(['get', 'post'], '/retour', [Controllers\SocialAuthController::class, 'callback'])->name('callback');
    Route::get('/fin', [Controllers\SocialAuthController::class, 'finish'])->name('finish');
});
Route::delete('/parametres/securite/{provider}', [Controllers\SocialAuthController::class, 'unlink'])->whereIn('provider', ['google', 'apple'])->middleware('auth')->name('social.unlink');

// Invitations (consultables avant connexion)
Route::get('/email/confirmer/{id}/{hash}', Controllers\EmailVerificationController::class)->middleware(['signed', 'throttle:12,1'])->name('verification.public');
Route::get('/invitations/{token}', [Controllers\InvitationController::class, 'show'])->name('invitations.show');

Route::middleware(['auth', 'verified', 'org'])->group(function () {
    Route::post('/invitations/recues/{invitation}', [Controllers\InvitationController::class, 'respond'])->name('invitations.respond');
    Route::post('/invitations/{token}/accepter', [Controllers\InvitationController::class, 'accept'])->name('invitations.accept');
    Route::post('/invitations/{token}/refuser', [Controllers\InvitationController::class, 'decline'])->name('invitations.decline');

    Route::get('/dashboard', Controllers\DashboardController::class)->name('dashboard');
    Route::get('/ma-semaine', Controllers\DigestController::class)->name('digest.show');
    Route::post('/demarrage/masquer', [Controllers\OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');
    Route::post('/demarrage/fiche-urgence/masquer', [Controllers\OnboardingController::class, 'dismissEmergency'])->name('onboarding.emergency.dismiss');
    Route::get('/recherche', Controllers\SearchController::class)->name('search');

    // Organisations / écuries
    Route::post('/espaces/changer', [Controllers\OrganizationController::class, 'switch'])->name('organizations.switch');
    Route::get('/espaces/nouveau', [Controllers\OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('/espaces', [Controllers\OrganizationController::class, 'store'])->name('organizations.store');
    Route::get('/organisation', [Controllers\OrganizationController::class, 'show'])->name('organization.show');
    // Écurie › Pension : contrats, prestations, relevés mensuels par propriétaire.
    Route::get('/ecurie/pension', [Controllers\BoardingController::class, 'index'])->name('boarding.index');
    Route::post('/ecurie/pension/contrats', [Controllers\BoardingController::class, 'storeContract'])->name('boarding.contracts.store');
    Route::post('/ecurie/pension/contrats/{contract}/fin', [Controllers\BoardingController::class, 'endContract'])->name('boarding.contracts.end');
    Route::post('/ecurie/pension/prestations', [Controllers\BoardingController::class, 'storeCharge'])->name('boarding.charges.store');
    Route::delete('/ecurie/pension/prestations/{charge}', [Controllers\BoardingController::class, 'destroyCharge'])->name('boarding.charges.destroy');
    Route::post('/ecurie/pension/releves', [Controllers\BoardingController::class, 'generate'])->name('boarding.generate');
    Route::post('/ecurie/pension/releves/{statement}/envoyer', [Controllers\BoardingController::class, 'send'])->name('boarding.send');
    Route::post('/ecurie/pension/releves/{statement}/paiement', [Controllers\BoardingController::class, 'pay'])->name('boarding.pay');
    Route::get('/releves-pension/{statement}', [Controllers\BoardingController::class, 'show'])->name('boarding.statement');
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

    // Chevaux
    Route::resource('chevaux', Controllers\HorseController::class)->parameters(['chevaux' => 'horse'])->names('horses');
    Route::post('/chevaux/{horse}/archiver', [Controllers\HorseController::class, 'archive'])->name('horses.archive');
    Route::post('/chevaux/{horse}/restaurer', [Controllers\HorseController::class, 'unarchive'])->name('horses.unarchive');
    Route::post('/chevaux/{horse}/transferer', [Controllers\HorseController::class, 'transfer'])->name('horses.transfer');

    Route::scopeBindings()->prefix('/chevaux/{horse}')->name('horses.')->group(function () {
        Route::get('/genealogie', [Controllers\PedigreeController::class, 'show'])->name('pedigree');
        Route::put('/genealogie', [Controllers\PedigreeController::class, 'update'])->name('pedigree.update');
        Route::post('/proprietaires', [Controllers\OwnershipController::class, 'store'])->name('ownerships.store');
        Route::post('/proprietaires/{ownership}/terminer', [Controllers\OwnershipController::class, 'end'])->name('ownerships.end');

        Route::get('/urgence', [Controllers\EmergencySheetController::class, 'edit'])->name('emergency.edit');
        Route::put('/urgence', [Controllers\EmergencySheetController::class, 'update'])->name('emergency.update');
        Route::get('/urgence/imprimer', [Controllers\EmergencySheetController::class, 'print'])->name('emergency.print');
        Route::get('/urgence/qr-code', [Controllers\EmergencySheetController::class, 'qr'])->name('emergency.qr');
        Route::post('/photos', [Controllers\HorsePhotoController::class, 'store'])->name('photos.store');
        Route::get('/photos/{photo}', [Controllers\HorsePhotoController::class, 'show'])->name('photos.show');
        Route::post('/photos/{photo}/principale', [Controllers\HorsePhotoController::class, 'makeMain'])->name('photos.main');
        Route::delete('/photos/{photo}', [Controllers\HorsePhotoController::class, 'destroy'])->name('photos.destroy');

        Route::get('/documents', [Controllers\HorseDocumentController::class, 'index'])->name('documents.index');
        Route::post('/documents', [Controllers\HorseDocumentController::class, 'store'])->name('documents.store');
        Route::get('/documents/{document}', [Controllers\HorseDocumentController::class, 'download'])->name('documents.download');
        Route::delete('/documents/{document}', [Controllers\HorseDocumentController::class, 'destroy'])->name('documents.destroy');

        Route::get('/sante', [Controllers\CareRecordController::class, 'index'])->name('care.index');
        Route::put('/rappels-suivi', [Controllers\CareReminderController::class, 'update'])->name('care.reminders');
        Route::post('/rythme-habituel', [Controllers\CareReminderController::class, 'adoptRhythm'])->name('care.rhythm');
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
        Route::get('/poids', [Controllers\MeasurementController::class, 'index'])->name('weight');
        Route::post('/poids', [Controllers\MeasurementController::class, 'store'])->name('weight.store');
        Route::put('/poids/rappel', [Controllers\MeasurementController::class, 'reminder'])->name('weight.reminder');
        Route::delete('/poids/{measurement}', [Controllers\MeasurementController::class, 'destroy'])->withoutScopedBindings()->name('weight.destroy');
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
    Route::post('/intervenants/rapide', [Controllers\ProfessionalController::class, 'quickStore'])->middleware('throttle:30,1')->name('professionals.quick');
    Route::resource('intervenants', Controllers\ProfessionalController::class)->parameters(['intervenants' => 'professional'])->names('professionals')->except(['destroy']);
    Route::post('/intervenants/{professional}/archiver', [Controllers\ProfessionalController::class, 'archive'])->name('professionals.archive');

    // Calendrier
    Route::get('/calendrier', [Controllers\CalendarController::class, 'index'])->name('calendar.index');
    Route::post('/calendrier', [Controllers\CalendarController::class, 'store'])->name('calendar.store');
    Route::get('/calendrier/{event}', [Controllers\CalendarController::class, 'show'])->name('calendar.show');
    Route::post('/calendrier/synchronisation/nouvelle-adresse', [Controllers\CalendarFeedController::class, 'regenerate'])->name('calendar.feed.regenerate');
    Route::get('/calendrier/{event}/modifier', [Controllers\CalendarController::class, 'edit'])->name('calendar.edit');
    Route::put('/calendrier/{event}', [Controllers\CalendarController::class, 'update'])->name('calendar.update');
    Route::delete('/calendrier/{event}', [Controllers\CalendarController::class, 'destroy'])->name('calendar.destroy');

    // Séances
    Route::get('/seances', [Controllers\RidingSessionController::class, 'index'])->name('sessions.index');
    Route::get('/seances/nouvelle', [Controllers\RidingSessionController::class, 'create'])->name('sessions.create');
    Route::post('/seances', [Controllers\RidingSessionController::class, 'store'])->name('sessions.store');
    Route::get('/seances/{session}', [Controllers\RidingSessionController::class, 'show'])->name('sessions.show');
    Route::get('/seances/{session}/preparer', [Controllers\RidingSessionController::class, 'edit'])->name('sessions.edit');
    Route::get('/seances/{session}/modifier', [Controllers\RidingSessionController::class, 'details'])->name('sessions.details');
    Route::put('/seances/{session}', [Controllers\RidingSessionController::class, 'update'])->name('sessions.update');
    Route::delete('/seances/{session}', [Controllers\RidingSessionController::class, 'destroy'])->name('sessions.destroy');
    Route::get('/seances/{session}/en-cours', [Controllers\RidingSessionController::class, 'live'])->name('sessions.live');
    Route::get('/seances/{session}/bilan', [Controllers\RidingSessionController::class, 'debrief'])->name('sessions.debrief');
    Route::put('/seances/{session}/bilan', [Controllers\RidingSessionController::class, 'saveDebrief'])->name('sessions.debrief.save');
    Route::post('/seances/{session}/dupliquer', [Controllers\RidingSessionController::class, 'duplicate'])->name('sessions.duplicate');
    Route::post('/seances/{session}/modele', [Controllers\SessionTemplateController::class, 'storeFromSession'])->name('sessions.template');
    Route::post('/seances/{session}/exercices', [Controllers\SessionExerciseController::class, 'store'])->name('sessions.exercises.store');
    Route::put('/seances/{session}/exercices/{item}', [Controllers\SessionExerciseController::class, 'update'])->scopeBindings()->name('sessions.exercises.update');
    Route::post('/seances/{session}/exercices/ordre', [Controllers\SessionExerciseController::class, 'layout'])->name('sessions.exercises.layout');
    Route::post('/seances/{session}/exercices/{item}/deplacer', [Controllers\SessionExerciseController::class, 'move'])->scopeBindings()->name('sessions.exercises.move');
    Route::delete('/seances/{session}/exercices/{item}', [Controllers\SessionExerciseController::class, 'destroy'])->scopeBindings()->name('sessions.exercises.destroy');
    Route::post('/seances/{session}/commentaires', [Controllers\RidingSessionController::class, 'comment'])->name('sessions.comments.store');
    Route::get('/modeles', [Controllers\SessionTemplateController::class, 'index'])->name('templates.index');
    Route::delete('/modeles/{template}', [Controllers\SessionTemplateController::class, 'destroy'])->name('templates.destroy');

    // Exercices
    Route::resource('exercices', Controllers\ExerciseController::class)->parameters(['exercices' => 'exercise'])->names('exercises')->except(['destroy']);
    Route::post('/exercices/{exercise}/dupliquer', [Controllers\ExerciseController::class, 'duplicate'])->name('exercises.duplicate');
    Route::post('/exercices/{exercise}/archiver', [Controllers\ExerciseController::class, 'archive'])->name('exercises.archive');
    Route::get('/exercices/{exercise}/demonstration', [Controllers\ExerciseController::class, 'media'])->name('exercises.media');

    // Budget
    Route::get('/budget', [Controllers\ExpenseController::class, 'index'])->name('budget.index');
    Route::post('/budget', [Controllers\ExpenseController::class, 'store'])->name('budget.store');
    // Facture photographiée lue par l'assistant (vérification avant enregistrement).
    Route::post('/budget/facture', [Controllers\InvoiceController::class, 'read'])->middleware('throttle:10,1')->name('budget.invoice.read');
    Route::get('/budget/facture/{token}', [Controllers\InvoiceController::class, 'review'])->where('token', '[A-Za-z0-9]{32}')->name('budget.invoice.review');
    Route::post('/budget/facture/{token}', [Controllers\InvoiceController::class, 'save'])->where('token', '[A-Za-z0-9]{32}')->name('budget.invoice.save');
    Route::delete('/budget/facture/{token}', [Controllers\InvoiceController::class, 'cancel'])->where('token', '[A-Za-z0-9]{32}')->name('budget.invoice.cancel');
    Route::get('/budget/{expense}/modifier', [Controllers\ExpenseController::class, 'edit'])->name('budget.edit');
    Route::put('/budget/{expense}', [Controllers\ExpenseController::class, 'update'])->name('budget.update');
    Route::delete('/budget/{expense}', [Controllers\ExpenseController::class, 'destroy'])->name('budget.destroy');
    Route::post('/budget/{expense}/paiement', [Controllers\ExpenseController::class, 'pay'])->name('budget.pay');
    Route::put('/budget/recurrentes/{recurring}', [Controllers\RecurringExpenseController::class, 'update'])->name('budget.recurring.update');
    Route::post('/budget/recurrentes/{recurring}/pause', [Controllers\RecurringExpenseController::class, 'toggle'])->name('budget.recurring.toggle');
    Route::delete('/budget/recurrentes/{recurring}', [Controllers\RecurringExpenseController::class, 'destroy'])->name('budget.recurring.destroy');
    Route::get('/budget/{expense}/justificatif/{document}', [Controllers\ExpenseController::class, 'receipt'])->scopeBindings()->name('budget.receipt');
    Route::get('/budget/export.{format}', [Controllers\ExpenseController::class, 'export'])->whereIn('format', ['csv', 'pdf'])->middleware('throttle:exports')->name('budget.export');
    Route::get('/budget/{expense}', [Controllers\ExpenseController::class, 'show'])->whereNumber('expense')->name('budget.show');

    // Partages (vue d'ensemble)
    Route::get('/partages', [Controllers\HorseSharingController::class, 'overview'])->name('sharing.index');

    // Notifications
    Route::get('/notifications', [Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/tutoriels', [Controllers\VideoController::class, 'index'])->name('videos.index');
    Route::get('/videos/{video}/fichier/{kind}', [Controllers\VideoController::class, 'file'])->whereIn('kind', ['video', 'audio', 'captions', 'poster'])->name('videos.file');
    Route::post('/videos/{video}/vue', [Controllers\VideoController::class, 'seen'])->name('videos.seen');
    Route::post('/notifications/lire', [Controllers\NotificationController::class, 'markAll'])->name('notifications.read-all');
    Route::post('/notifications/appareils', [Controllers\PushSubscriptionController::class, 'store'])->middleware('throttle:20,1')->name('push.subscribe');
    Route::delete('/notifications/appareils', [Controllers\PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    Route::post('/notifications/etat', [Controllers\PushSubscriptionController::class, 'state'])->middleware('throttle:30,1')->name('push.state');
    Route::post('/notifications/essai', [Controllers\PushSubscriptionController::class, 'test'])->middleware('throttle:6,1')->name('push.test');
    Route::get('/notifications/{id}', [Controllers\NotificationController::class, 'open'])->name('notifications.open');

    // Abonnement
    Route::get('/assistant', [Controllers\AssistantController::class, 'history'])->name('assistant.history');
    Route::get('/assistant/etat', [Controllers\AssistantController::class, 'status'])->name('assistant.status');
    Route::post('/assistant/transcription', [Controllers\AssistantController::class, 'transcribe'])->middleware('throttle:20,1')->name('assistant.transcribe');
    Route::post('/assistant/consigne', [Controllers\AssistantController::class, 'command'])->middleware('throttle:20,1')->name('assistant.command');
    Route::post('/assistant/actions/{action}/annuler', [Controllers\AssistantController::class, 'undo'])->name('assistant.undo');

    Route::get('/abonnement', [Controllers\BillingController::class, 'index'])->name('billing.index');
    Route::post('/abonnement/souscrire', [Controllers\BillingController::class, 'checkout'])->name('billing.checkout');
    Route::post('/abonnement/code', [Controllers\BillingController::class, 'redeem'])->middleware('throttle:10,1')->name('billing.redeem');
    Route::get('/abonnement/retour', [Controllers\BillingController::class, 'returned'])->name('billing.return');
    Route::post('/abonnement/portail', [Controllers\BillingController::class, 'portal'])->name('billing.portal');
    Route::post('/abonnement/changer', [Controllers\BillingController::class, 'swap'])->name('billing.swap');
    Route::post('/abonnement/resilier', [Controllers\BillingController::class, 'cancel'])->name('billing.cancel');
    Route::post('/abonnement/reprendre', [Controllers\BillingController::class, 'resume'])->name('billing.resume');

    // Paramètres du compte
    Route::get('/parametres', [Controllers\SettingsController::class, 'profile'])->name('settings.profile');
    Route::put('/parametres/profil', [Controllers\SettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::get('/parametres/notifications', [Controllers\SettingsController::class, 'notifications'])->name('settings.notifications');
    Route::put('/parametres/notifications', [Controllers\SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
    Route::post('/parametres/niveau', [Controllers\SettingsController::class, 'ridingLevel'])->name('settings.riding-level');
    Route::post('/parametres/notifications/rappel-quotidien', [Controllers\SettingsController::class, 'enableDailyReminder'])->name('settings.daily-reminder.enable');
    Route::post('/parametres/notifications/rappel-quotidien/ne-plus-proposer', [Controllers\SettingsController::class, 'stopDailyNudge'])->name('settings.daily-reminder.stop');
    Route::get('/parametres/securite', [Controllers\SettingsController::class, 'security'])->name('settings.security');
    Route::put('/parametres/apparence', [Controllers\SettingsController::class, 'updateTheme'])->name('settings.theme');
    Route::post('/parametres/securite/lien-mot-de-passe', [Controllers\SettingsController::class, 'sendPasswordLink'])->middleware('throttle:3,10')->name('settings.password.link');
    Route::get('/parametres/donnees', [Controllers\SettingsController::class, 'privacy'])->name('settings.privacy');
    Route::get('/parametres/donnees/export', [Controllers\ExportController::class, 'personalData'])->middleware(['throttle:exports', 'password.confirm'])->name('settings.export');
    Route::post('/parametres/donnees/demande', [Controllers\SettingsController::class, 'dataRequest'])->name('settings.data-request');

});

// --- Super-administration --------------------------------------------------
Route::middleware(['auth', 'verified', 'org', 'super-admin', 'password.confirm'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('/statistiques', Admin\StatsController::class)->name('stats.index');
    Route::get('/utilisateurs', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/utilisateurs/{user}', [Admin\UserController::class, 'show'])->name('users.show');
    Route::post('/utilisateurs/{user}/suspendre', [Admin\UserController::class, 'suspend'])->name('users.suspend');
    Route::post('/utilisateurs/{user}/reactiver', [Admin\UserController::class, 'reactivate'])->name('users.reactivate');
    Route::get('/organisations', [Admin\OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organisations/ecuries-en-double/{user}', [Admin\OrganizationController::class, 'keepStable'])->name('organizations.keep-stable');
    Route::get('/organisations/{organization}', [Admin\OrganizationController::class, 'show'])->name('organizations.show');
    Route::post('/organisations/{organization}/suspendre', [Admin\OrganizationController::class, 'suspend'])->name('organizations.suspend');
    Route::post('/organisations/{organization}/reactiver', [Admin\OrganizationController::class, 'reactivate'])->name('organizations.reactivate');
    Route::delete('/organisations/{organization}', [Admin\OrganizationController::class, 'destroy'])->name('organizations.destroy');
    Route::post('/organisations/{organization}/licences', [Admin\LicenseController::class, 'store'])->name('licenses.store');
    Route::put('/licences/{license}', [Admin\LicenseController::class, 'update'])->name('licenses.update');
    Route::resource('offres', Admin\PlanController::class)->parameters(['offres' => 'plan'])->names('plans')->except(['show', 'destroy']);
    Route::post('/offres/{plan}/archiver', [Admin\PlanController::class, 'archive'])->name('plans.archive');
    Route::get('/facturation', [Admin\BillingController::class, 'index'])->name('billing.index');
    Route::get('/assistant', [Admin\AssistantSettingsController::class, 'edit'])->name('assistant.edit');
    Route::put('/assistant', [Admin\AssistantSettingsController::class, 'update'])->name('assistant.update');
    Route::post('/assistant/test', [Admin\AssistantSettingsController::class, 'test'])->middleware('throttle:6,1')->name('assistant.test');
    Route::get('/mises-a-jour', [Admin\UpdateController::class, 'index'])->name('updates.index');
    Route::post('/mises-a-jour/envoi', [Admin\UpdateController::class, 'upload'])->middleware('throttle:600,1')->name('updates.upload');
    Route::get('/mises-a-jour/{update}', [Admin\UpdateController::class, 'show'])->name('updates.show');
    Route::post('/mises-a-jour/{update}/installer', [Admin\UpdateController::class, 'apply'])->name('updates.apply');
    Route::get('/mises-a-jour/{update}/finaliser', [Admin\UpdateController::class, 'finalize'])->middleware('signed')->name('updates.finalize');
    Route::post('/mises-a-jour/{update}/restaurer', [Admin\UpdateController::class, 'restore'])->name('updates.restore');
    Route::get('/mises-a-jour/{update}/sauvegarde/{kind}', [Admin\UpdateController::class, 'download'])->whereIn('kind', ['code', 'base'])->name('updates.download');
    Route::delete('/mises-a-jour/{update}', [Admin\UpdateController::class, 'destroy'])->name('updates.destroy');
    Route::get('/integrations', [Admin\IntegrationController::class, 'edit'])->name('integrations.edit');
    Route::put('/integrations/email', [Admin\IntegrationController::class, 'updateMail'])->name('integrations.mail');
    Route::post('/integrations/email/test', [Admin\IntegrationController::class, 'testMail'])->middleware('throttle:5,1')->name('integrations.mail.test');
    Route::put('/integrations/stripe', [Admin\IntegrationController::class, 'updateStripe'])->name('integrations.stripe');
    Route::put('/integrations/connexion', [Admin\IntegrationController::class, 'updateSocial'])->name('integrations.social');
    Route::post('/integrations/stripe/test', [Admin\IntegrationController::class, 'testStripe'])->middleware('throttle:5,1')->name('integrations.stripe.test');
    Route::get('/codes', [Admin\PromoCodeController::class, 'index'])->name('promo-codes.index');
    Route::post('/codes', [Admin\PromoCodeController::class, 'store'])->name('promo-codes.store');
    Route::get('/codes/qr-code', [Admin\PromoCodeController::class, 'qrGenerator'])->name('promo-codes.qr-generator');
    Route::post('/codes/sources', [Admin\PromoCodeController::class, 'storeSource'])->name('promo-codes.sources.store');
    Route::patch('/codes/sources/{source}', [Admin\PromoCodeController::class, 'updateSource'])->name('promo-codes.sources.update');
    Route::delete('/codes/sources/{source}', [Admin\PromoCodeController::class, 'destroySource'])->name('promo-codes.sources.destroy');
    Route::get('/codes/{promoCode}', [Admin\PromoCodeController::class, 'show'])->name('promo-codes.show');
    Route::put('/codes/{promoCode}/reglages', [Admin\PromoCodeController::class, 'updateSettings'])->name('promo-codes.settings');
    Route::get('/codes/{promoCode}/qr/{source}', [Admin\PromoCodeController::class, 'qr'])->name('promo-codes.qr');
    Route::patch('/codes/{promoCode}', [Admin\PromoCodeController::class, 'update'])->name('promo-codes.update');
    Route::post('/facturation/evenements/{event}/retraiter', [Admin\BillingController::class, 'reprocess'])->name('billing.reprocess');
    Route::get('/exercices', [Admin\ExerciseController::class, 'index'])->name('exercises.index');
    Route::get('/exercices/nouveau', [Admin\ExerciseController::class, 'create'])->name('exercises.create');
    Route::post('/exercices', [Admin\ExerciseController::class, 'store'])->name('exercises.store');
    Route::get('/exercices/{exercise}', [Admin\ExerciseController::class, 'edit'])->name('exercises.edit');
    Route::put('/exercices/{exercise}', [Admin\ExerciseController::class, 'update'])->name('exercises.update');
    Route::post('/exercices/{exercise}/archiver', [Admin\ExerciseController::class, 'archive'])->name('exercises.archive');
    Route::post('/exercices/{exercise}/demonstration', [Admin\ExerciseController::class, 'upload'])->name('exercises.upload');
    Route::delete('/exercices/{exercise}/demonstration', [Admin\ExerciseController::class, 'removeMedia'])->name('exercises.remove-media');
    Route::get('/notifications', [Admin\NotificationDiagnosticsController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/essai', [Admin\NotificationDiagnosticsController::class, 'test'])->middleware('throttle:10,1')->name('notifications.test');
    Route::get('/emails', [Admin\MailDesignController::class, 'edit'])->name('mail-design.edit');
    Route::put('/emails', [Admin\MailDesignController::class, 'update'])->name('mail-design.update');
    Route::post('/emails/apercu', [Admin\MailDesignController::class, 'preview'])->name('mail-design.preview');
    Route::post('/emails/essai', [Admin\MailDesignController::class, 'test'])->middleware('throttle:6,1')->name('mail-design.test');
    Route::post('/emails/logo', [Admin\MailDesignController::class, 'uploadLogo'])->name('mail-design.logo');
    Route::delete('/emails/logo', [Admin\MailDesignController::class, 'removeLogo'])->name('mail-design.logo.destroy');
    Route::get('/messages', [Admin\AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/messages', [Admin\AnnouncementController::class, 'store'])->middleware('throttle:10,1')->name('announcements.store');
    Route::get('/videos', [Admin\VideoController::class, 'index'])->name('videos.index');
    Route::post('/videos', [Admin\VideoController::class, 'store'])->name('videos.store');
    Route::get('/videos/{video}', [Admin\VideoController::class, 'edit'])->name('videos.edit');
    Route::put('/videos/{video}', [Admin\VideoController::class, 'update'])->name('videos.update');
    Route::post('/videos/{video}/publication', [Admin\VideoController::class, 'publish'])->name('videos.publish');
    Route::post('/videos/{video}/fichiers/{kind}', [Admin\VideoController::class, 'upload'])->whereIn('kind', ['video', 'audio', 'captions', 'poster'])->name('videos.upload');
    Route::delete('/videos/{video}/fichiers/{kind}', [Admin\VideoController::class, 'removeFile'])->whereIn('kind', ['audio', 'captions', 'poster'])->name('videos.remove-file');
    Route::delete('/videos/{video}', [Admin\VideoController::class, 'destroy'])->name('videos.destroy');
    Route::get('/parametres', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/parametres', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::get('/journal', [Admin\AuditController::class, 'index'])->name('audit.index');
    Route::get('/erreurs', [Admin\AuditController::class, 'errors'])->name('errors.index');
    Route::get('/demandes-rgpd', [Admin\DataRequestController::class, 'index'])->name('data-requests.index');
    Route::put('/demandes-rgpd/{dataRequest}', [Admin\DataRequestController::class, 'update'])->name('data-requests.update');
});
