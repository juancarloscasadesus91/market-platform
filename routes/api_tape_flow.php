<?php

use App\Http\Controllers\AdvancedTapeFlowController;
use Illuminate\Support\Facades\Route;

// Advanced Tape Flow API Routes
Route::prefix('api/advanced-tape-flow')->name('api.advanced-tape-flow.')->group(function () {

    // Current flow data
    Route::get('current', [AdvancedTapeFlowController::class, 'getCurrentFlow'])
        ->name('current');

    // Contracts data
    Route::get('contracts', [AdvancedTapeFlowController::class, 'getContracts'])
        ->name('contracts');

    Route::get('contracts/{contractKey}', [AdvancedTapeFlowController::class, 'getContractFlow'])
        ->name('contract.flow');

    // Positions data
    Route::get('positions', [AdvancedTapeFlowController::class, 'getPositions'])
        ->name('positions');

    Route::get('positions/active', [AdvancedTapeFlowController::class, 'getActivePositions'])
        ->name('positions.active');

    Route::get('positions/building', [AdvancedTapeFlowController::class, 'getBuildingPositions'])
        ->name('positions.building');

    Route::get('positions/exiting', [AdvancedTapeFlowController::class, 'getExitingPositions'])
        ->name('positions.exiting');

    // Top contracts by category
    Route::get('contracts/top-bullish', [AdvancedTapeFlowController::class, 'getTopBullishContracts'])
        ->name('contracts.top-bullish');

    Route::get('contracts/top-bearish', [AdvancedTapeFlowController::class, 'getTopBearishContracts'])
        ->name('contracts.top-bearish');

    Route::get('contracts/most-aggressive', [AdvancedTapeFlowController::class, 'getMostAggressiveContracts'])
        ->name('contracts.most-aggressive');

    Route::get('contracts/high-mid-noise', [AdvancedTapeFlowController::class, 'getHighMidNoiseContracts'])
        ->name('contracts.high-mid-noise');

    // Tape stream
    Route::get('tape/recent', [AdvancedTapeFlowController::class, 'getRecentTape'])
        ->name('tape.recent');

    // Dashboard summary
    Route::get('dashboard', [AdvancedTapeFlowController::class, 'getDashboardSummary'])
        ->name('dashboard');

    // Trade processing (for WebSocket integration)
    Route::post('process-trade', [AdvancedTapeFlowController::class, 'processTrade'])
        ->name('process-trade');

    // Admin/Management endpoints
    Route::post('snapshots/create', [AdvancedTapeFlowController::class, 'createSnapshots'])
        ->name('snapshots.create');

    Route::post('reset-window', [AdvancedTapeFlowController::class, 'resetWindow'])
        ->name('reset-window');
});
