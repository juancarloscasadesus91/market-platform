<?php

use App\Jobs\RunStrategyBotJob;
use App\Jobs\RunAlpacaStrategyLabJob;
use App\Models\AlpacaStrategyLabSession;
use App\Models\StrategyBot;
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
// Schedule::command('market:update-quotes')
//     ->everyTwoSeconds()
//     ->between('9:30', '16:00')
//     ->timezone('America/New_York')
//     ->withoutOverlapping();

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

// Live strategy bots run only during regular market hours.
Schedule::call(function () {
    StrategyBot::where('status', 'running')
        ->where('paper_mode', false)
        ->get()
        ->each(fn ($bot) => RunStrategyBotJob::dispatch($bot->id));
})
    ->everyMinute()
    ->between('9:30', '16:00')
    ->timezone('America/New_York')
    ->name('strategy-bots-live-tick')
    ->withoutOverlapping();

// Paper strategy bots can be tested during extended hours. Run synchronously so
// TP/SL monitoring still works when a queue worker is not running locally.
Schedule::call(function () {
    StrategyBot::where('status', 'running')
        ->where('paper_mode', true)
        ->get()
        ->each(fn ($bot) => RunStrategyBotJob::dispatchSync($bot->id));
})
    ->everyMinute()
    ->between('4:00', '20:00')
    ->timezone('America/New_York')
    ->name('strategy-bots-paper-extended-tick')
    ->withoutOverlapping();

// Alpaca Strategy Lab uses real Alpaca Paper orders. Run sync locally so fills,
// SL/TP checks, and status logs keep moving even without a queue worker.
Schedule::call(function () {
    AlpacaStrategyLabSession::where('status', 'running')
        ->where('mode', 'paper')
        ->get()
        ->each(fn ($session) => RunAlpacaStrategyLabJob::dispatchSync($session->id));
})
    ->everyMinute()
    ->between('4:00', '20:00')
    ->timezone('America/New_York')
    ->name('alpaca-strategy-lab-paper-tick')
    ->withoutOverlapping();
