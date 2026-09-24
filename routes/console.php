<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cdr:sync')->everyMinute()->withoutOverlapping();
Schedule::command('cdr:summarize')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('customers:geocode --limit=50')->daily()->withoutOverlapping();
Schedule::command('collectors:prune-positions --days=14')->daily()->withoutOverlapping();
