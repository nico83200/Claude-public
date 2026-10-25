<?php

use App\Services\NotificationDiagnostics;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * Planificateur : une seule tâche cron suffit sur l'hébergement
 *   * * * * * cd /chemin/app && php artisan schedule:run >> /dev/null 2>&1
 * ou, si l'hébergeur demande un fichier PHP : /chemin/app/cron.php
 *
 * Les tâches sont exécutées dans le même processus (Artisan::call) et non dans des processus séparés :
 * sur un hébergement mutualisé, lancer un sous-processus PHP échoue souvent (proc_open désactivé,
 * binaire PHP du cron différent de celui de la ligne de commande).
 */
$task = fn (string $command, array $args = []) => Schedule::call(fn () => Artisan::call($command, $args))->name($command)->withoutOverlapping(30);

// Battement : montre dans l'administration que le cron du serveur tourne bien.
Schedule::call(fn () => NotificationDiagnostics::beat())->everyMinute()->name('heartbeat');
$task('reminders:send')->dailyAt('07:30');
// Rations arrivées à leur date de fin : archivées, responsables prévenus.
$task('feeding:archive')->dailyAt('07:10');
// Bilan après une séance ou un cours, rappel quotidien de saisie (heure choisie par chaque utilisateur).
$task('reminders:activity')->everyFiveMinutes();
// Échéances des dépenses récurrentes (pension…), notifiées le jour même.
$task('expenses:recurring')->dailyAt('06:05');
// Rappels intelligents : rythme habituel des soins (« on prend rendez-vous ? »).
$task('reminders:rhythm')->dailyAt('08:50');
// Points d'attention ouverts : « Des nouvelles ? » (le service ne relance jamais la nuit).
$task('attention:follow-up')->hourlyAt(20);
// Suggestion d'activer le rappel quotidien (au plus 3 fois, toutes les 2 semaines).
$task('reminders:daily-nudge')->dailyAt('11:10');
// Récapitulatif de la semaine, dimanche soir (relancé jusqu'à 21 h si le cron a manqué un passage).
$task('reminders:weekly')->sundays()->hourlyAt(5)->between('18:00', '21:10');
// Surveillance : alerte e-mail aux super-administrateurs (cron arrêté, erreurs en hausse).
$task('monitor:check')->everyFiveMinutes();
$task('licenses:refresh')->hourly();
// Filet de sécurité si un webhook Stripe manque : état des abonnements relu chez Stripe.
$task('billing:sync')->hourly();
$task('billing:price-migrations')->dailyAt('03:15');
$task('data:purge')->weeklyOn(0, '03:45');
// Sauvegarde chiffrée sur Google Drive : chaque nuit à partir de 3 h (rattrapée si le cron a manqué), ou dans la minute
// quand elle est demandée depuis l'administration. Verrou long : l'envoi des fichiers peut durer.
Schedule::call(fn () => Artisan::call('backup:run', ['--auto' => true]))->name('backup:run')->everyMinute()->withoutOverlapping(240);
// Hébergement mutualisé sans worker permanent : traitement périodique de la file.
$task('queue:work', ['--stop-when-empty' => true, '--max-time' => 50])->everyMinute();
$task('auth:clear-resets')->daily();

// Mise à jour installée depuis l'administration mais non finalisée (navigateur fermé…)
$task('app:finalize-update')->everyMinute()->evenInMaintenanceMode();
