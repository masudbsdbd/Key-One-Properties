<?php

use Illuminate\Support\Facades\Schedule;

// Run `php artisan schedule:run` every minute from cron (see docs/deployment.md).
Schedule::command('keyone:backup')->dailyAt('02:30')->withoutOverlapping()->onOneServer();
Schedule::command('activitylog:clean')->weekly();
Schedule::command('auth:clear-resets')->daily();
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
Schedule::command('keyone:reconcile')->dailyAt('03:10')->withoutOverlapping()->onOneServer()
    ->appendOutputTo(storage_path('logs/reconcile.log'));
Schedule::command('keyone:collections-daily')->dailyAt('01:20')->withoutOverlapping()->onOneServer();
