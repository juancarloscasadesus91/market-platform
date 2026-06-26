<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refresh Schwab API token every 20 minutes (token expires in 30 minutes)
Schedule::command('schwab:refresh-token')->cron('*/20 * * * *');

// Schedule symbol synchronization
// Runs daily at 6:00 AM (before market opens)
Schedule::command('schwab:sync-symbols')->dailyAt('06:00')->timezone('America/New_York');

// Alternative: Run every Sunday at 2:00 AM for weekly sync
// Schedule::command('schwab:sync-symbols')->weeklyOn(0, '02:00')->timezone('America/New_York');

// Update market quotes every 2 seconds during market hours (9:30 AM - 4:00 PM ET)
Schedule::command('market:update-quotes')
    ->everyTwoSeconds()
    ->between('9:30', '16:00')
    ->timezone('America/New_York')
    ->withoutOverlapping();

// Option monitor cron - 0-2 DTE every 2 minutes during market hours
Schedule::command('option:monitor-cron --underlying=SPX --max-dte=2 --min-volume=50 --atm-range=10')
    ->everyTwoMinutes()
    ->between('9:30', '16:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->runInBackground();

// Option monitor cron - 3-5 DTE every 5 minutes during market hours
Schedule::command('option:monitor-cron --underlying=SPX --max-dte=5 --min-volume=50 --atm-range=7')
    ->everyFiveMinutes()
    ->between('9:30', '16:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->runInBackground();
