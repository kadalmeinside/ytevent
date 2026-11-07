<?php

use App\Modules\Simulation\SimulationController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('simulation')->name('simulation.')->group(function () {
    Route::post('/toggle', [SimulationController::class, 'toggle'])->name('toggle');
    Route::post('/switch-persona', [SimulationController::class, 'switchPersona'])->name('switch-persona');
    Route::post('/register-team', [SimulationController::class, 'registerTeam'])->name('register-team');
    Route::post('/pay-team', [SimulationController::class, 'payTeam'])->name('pay-team');
    Route::post('/fill-roster', [SimulationController::class, 'fillRoster'])->name('fill-roster');
    Route::post('/admin-approve-payments', [SimulationController::class, 'adminApprovePayments'])->name('admin-approve-payments');
    Route::post('/admin-approve-screening', [SimulationController::class, 'adminApproveScreening'])->name('admin-approve-screening');
    Route::post('/fast-forward-team', [SimulationController::class, 'fastForwardFullTeam'])->name('fast-forward-team');
});

