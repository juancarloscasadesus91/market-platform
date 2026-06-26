<?php

use App\Http\Controllers\SchwabAuthController;
use App\Models\Symbol;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
})->name('dashboard');

Route::get('/symbol/{ticker}', function (string $ticker) {
    return view('symbol.show', ['ticker' => strtoupper($ticker)]);
})->name('symbol.show');

Route::get('/heatmap', function () {
    return view('heatmap');
})->name('heatmap');

Route::get('/alerts', function () {
    return view('alerts.index');
})->name('alerts.index');

Route::get('/trading-journal', function () {
    return view('trading-journal');
})->name('trading-journal');

Route::get('/advanced-tape-flow', function () {
    return view('advanced-tape-flow');
})->name('advanced-tape-flow');

Route::get('/backtest', function () {
    return view('backtest');
})->name('backtest');

Route::get('/strategy-lab', function () {
    return view('strategy-lab');
})->name('strategy-lab');

Route::get('/schwab-account', function () {
    return view('schwab-account');
})->name('schwab.account');

Route::get('/alpaca-paper', function () {
    return view('alpaca-paper');
})->name('alpaca.paper');

Route::get('/alpaca-strategy-lab', function () {
    return view('alpaca-strategy-lab');
})->name('alpaca.strategy-lab');

Route::get('/strategy-bots', function () {
    return view('strategy-bots');
})->name('strategy-bots');

Route::get('/trading-journal/export/excel', [\App\Http\Controllers\TradingJournalExportController::class, 'exportExcel'])
    ->name('trading-journal.export.excel');
Route::get('/trading-journal/export/pdf', [\App\Http\Controllers\TradingJournalExportController::class, 'exportPDF'])
    ->name('trading-journal.export.pdf');

// Schwab OAuth routes
Route::prefix('auth/schwab')->group(function () {
    // Market Data API
    Route::get('redirect', [SchwabAuthController::class, 'redirect'])->name('schwab.redirect');
    Route::get('callback', [SchwabAuthController::class, 'callback'])->name('schwab.callback');

    // Trader API
    Route::get('trader/redirect', [SchwabAuthController::class, 'traderRedirect'])->name('schwab.trader.redirect');
    Route::get('trader/callback', [SchwabAuthController::class, 'traderCallback'])->name('schwab.trader.callback');

    Route::post('disconnect', [SchwabAuthController::class, 'disconnect'])->name('schwab.disconnect');
});

// Tunnel API routes (without CSRF protection)
Route::prefix('api/tunnel')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])->group(function () {
    Route::post('start', [\App\Http\Controllers\TunnelController::class, 'start']);
    Route::post('stop', [\App\Http\Controllers\TunnelController::class, 'stop']);
    Route::get('output', [\App\Http\Controllers\TunnelController::class, 'output']);
    Route::get('status', [\App\Http\Controllers\TunnelController::class, 'status']);
});

// Advanced Tape Flow API routes
require __DIR__.'/api_tape_flow.php';
