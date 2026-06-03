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
