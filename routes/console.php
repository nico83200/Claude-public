<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Planificateur : une seule tâche cron suffit sur l'hébergement
 *   * * * * * cd /chemin/app && php artisan schedule:run >> /dev/null 2>&1
 */
Schedule::command('reminders:send')->dailyAt('07:30')->withoutOverlapping();
Schedule::command('licenses:refresh')->hourly()->withoutOverlapping();
Schedule::command('billing:price-migrations')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('data:purge')->weeklyOn(0, '03:45')->withoutOverlapping();
// Hébergement mutualisé sans worker permanent : traitement périodique de la file.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
Schedule::command('auth:clear-resets')->daily();
