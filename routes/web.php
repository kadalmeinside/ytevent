<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\PaymentVerificationController as AdminPaymentVerificationController;
use App\Http\Controllers\Admin\TeamManagementController as AdminTeamManagementController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\Manager\OfficialController as ManagerOfficialController;
use App\Http\Controllers\Manager\PaymentController as ManagerPaymentController;
use App\Http\Controllers\Manager\PlayerController as ManagerPlayerController;
use App\Http\Controllers\PrivateMediaController;
use App\Http\Controllers\PublicEventController;
use Illuminate\Support\Facades\Route;

// Area Publik
Route::get('/', [PublicEventController::class, 'index'])->name('home');
Route::get('/events/{event:slug}', [PublicEventController::class, 'show'])->name('events.show');
Route::get('/events/{event:slug}/register', [PublicEventController::class, 'showRegister'])->name('events.register');
Route::post('/events/{event:slug}/register', [PublicEventController::class, 'register'])
    ->middleware('throttle:team-registration')
    ->name('events.register.store');

// Autentikasi Manajer Tim
Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'showManagerLogin'])->name('login');
    Route::post('/login', [SessionController::class, 'managerLogin'])->name('login.store');
});

Route::post('/logout', [SessionController::class, 'logout'])->middleware('auth')->name('logout');

// Pintu Gerbang Internal Panitia (/admin)
Route::get('/admin', [SessionController::class, 'adminEntry'])->name('admin.entry');
Route::get('/admin/login', [SessionController::class, 'showAdminLogin'])->name('admin.login');
Route::post('/admin/login', [SessionController::class, 'adminLogin'])->name('admin.login.store');
Route::post('/admin/logout', [SessionController::class, 'adminLogout'])->middleware('auth')->name('admin.logout');

// Rute Media Privat Terotorisasi (Policy Protected)
Route::middleware('auth')->prefix('private-media')->name('private.')->group(function () {
    Route::get('/payments/{payment}/proof', [PrivateMediaController::class, 'paymentProof'])->name('payment-proof');
    Route::get('/players/{player}/photo', [PrivateMediaController::class, 'playerPhoto'])->name('player-photo');
    Route::get('/player-documents/{document}', [PrivateMediaController::class, 'playerDocument'])->name('player-document');
    Route::get('/officials/{official}/photo', [PrivateMediaController::class, 'officialPhoto'])->name('official-photo');
    Route::get('/officials/{official}/license', [PrivateMediaController::class, 'officialLicense'])->name('official-license');
});

// Area Portal Manajer Tim
Route::middleware(['auth', 'manager.portal'])->prefix('manager')->name('manager.')->group(function () {
    Route::get('/dashboard', [ManagerDashboardController::class, 'index'])->name('dashboard');
    Route::post('/switch-team/{team}', [ManagerDashboardController::class, 'switchTeam'])->name('switch-team');

    Route::get('/team-profile', [ManagerDashboardController::class, 'teamProfile'])->name('team-profile');
    Route::post('/teams/{team}/profile', [ManagerDashboardController::class, 'updateTeamProfile'])->name('team-profile.update');

    Route::get('/payments', [ManagerPaymentController::class, 'index'])->name('payments');
    Route::post('/payments/{payment}/gateway', [ManagerPaymentController::class, 'initiateGateway'])->name('payments.gateway');
    Route::post('/payments/{payment}/simulate-sandbox', [ManagerPaymentController::class, 'simulateSandbox'])->name('payments.simulate');
    Route::post('/payments/{payment}/manual-proof', [ManagerPaymentController::class, 'uploadManualProof'])->name('payments.manual-proof');
    Route::post('/teams/{team}/reclaim-slot', [ManagerPaymentController::class, 'reclaimSlot'])->name('payments.reclaim-slot');

    Route::get('/players', [ManagerPlayerController::class, 'index'])->name('players');
    Route::post('/teams/{team}/players', [ManagerPlayerController::class, 'store'])->name('players.store');
    Route::post('/players/{player}/update', [ManagerPlayerController::class, 'update'])->name('players.update');
    Route::delete('/players/{player}', [ManagerPlayerController::class, 'destroy'])->name('players.destroy');
    Route::post('/players/{player}/documents', [ManagerPlayerController::class, 'uploadDocument'])->name('players.documents.store');
    Route::post('/players/{player}/submit', [ManagerPlayerController::class, 'submitPlayer'])->name('players.submit');

    Route::get('/officials', [ManagerOfficialController::class, 'index'])->name('officials');
    Route::post('/teams/{team}/officials', [ManagerOfficialController::class, 'store'])->name('officials.store');
    Route::post('/teams/{team}/officials/{official}/update', [ManagerOfficialController::class, 'update'])->name('officials.update');
    Route::delete('/teams/{team}/officials/{official}', [ManagerOfficialController::class, 'destroy'])->name('officials.destroy');
});

// Area Operasional Admin & Finance
Route::middleware(['admin.portal'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])
        ->middleware('permission:dashboard.admin.view')
        ->name('dashboard');

    Route::middleware('permission:events.view')->group(function () {
        Route::get('/events', [AdminEventController::class, 'index'])->name('events.index');
        Route::get('/events/create', [AdminEventController::class, 'create'])->name('events.create');
        Route::post('/events', [AdminEventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}/edit', [AdminEventController::class, 'edit'])->name('events.edit');
        Route::post('/events/{event}/update', [AdminEventController::class, 'update'])->name('events.update');
    });

    Route::get('/teams', [AdminTeamManagementController::class, 'index'])
        ->middleware('permission:teams.view_all')
        ->name('teams.index');

    Route::get('/payments/manual-verification', [AdminPaymentVerificationController::class, 'manualQueue'])
        ->middleware('permission:payments.verify_manual')
        ->name('payments.manual-verification');

    Route::post('/payments/{payment}/verify', [AdminPaymentVerificationController::class, 'verifyManual'])
        ->middleware('permission:payments.verify_manual')
        ->name('payments.verify');

    Route::get('/payments/transactions', [AdminPaymentVerificationController::class, 'transactions'])
        ->middleware('permission:payments.view_all')
        ->name('payments.transactions');
});
