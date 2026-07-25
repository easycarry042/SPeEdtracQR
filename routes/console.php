<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Self-healing SLA enforcement: a single hourly sweep replaces thousands of
// per-scan delayed jobs. Requires `php artisan schedule:run` on cron in prod.
Schedule::command('documents:check-sla')->hourly();

// Nightly data backups (DB dump + uploaded files) to the `backups` disk, then
// prune old archives and verify a fresh one exists. Requires the scheduler
// (`php artisan schedule:run`) on cron in production.
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('01:30');
Schedule::command('backup:monitor')->daily()->at('02:00');

// Health probes stored for the /health page; the heartbeat lets ScheduleCheck
// detect a stopped scheduler (which would silently halt SLA + backups).
Schedule::command('health:check')->everyFiveMinutes();
Schedule::command('health:schedule-check-heartbeat')->everyMinute();
