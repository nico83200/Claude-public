<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Planificateur : une seule tâche cron suffit sur l'hébergement
 *   * * * * * cd /chemin/app && php artisan schedule:run >> /dev/null 2>&1
 */
Schedule::command('reminders:send')->dailyAt('07:30')->withoutOverlapping();
// Bilan après une séance ou un cours, rappel quotidien de saisie (heure choisie par chaque utilisateur).
Schedule::command('reminders:activity')->everyFiveMinutes()->withoutOverlapping();
// Échéances des dépenses récurrentes (pension…), notifiées le jour même.
Schedule::command('expenses:recurring')->dailyAt('06:05')->withoutOverlapping();
Schedule::command('licenses:refresh')->hourly()->withoutOverlapping();
// Filet de sécurité si un webhook Stripe manque : état des abonnements relu chez Stripe.
Schedule::command('billing:sync')->hourly()->withoutOverlapping();
Schedule::command('billing:price-migrations')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('data:purge')->weeklyOn(0, '03:45')->withoutOverlapping();
// Hébergement mutualisé sans worker permanent : traitement périodique de la file.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
Schedule::command('auth:clear-resets')->daily();

// Mise à jour installée depuis l'administration mais non finalisée (navigateur fermé…)
Schedule::command('app:finalize-update')->everyMinute()->evenInMaintenanceMode()->withoutOverlapping();
