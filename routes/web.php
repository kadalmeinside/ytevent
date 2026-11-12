<?php

use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EmailLogController as AdminEmailLogController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\IntegrationMonitorController as AdminIntegrationMonitorController;
use App\Http\Controllers\Admin\PaymentVerificationController as AdminPaymentVerificationController;
use App\Http\Controllers\Admin\RolePermissionController as AdminRolePermissionController;
use App\Http\Controllers\Admin\ScreeningController as AdminScreeningController;
use App\Http\Controllers\Admin\SystemSettingController as AdminSystemSettingController;
use App\Http\Controllers\Admin\TeamManagementController as AdminTeamManagementController;
use App\Http\Controllers\Admin\TournamentController as AdminTournamentController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Manager\AccreditationController as ManagerAccreditationController;
use App\Http\Controllers\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\Manager\MatchLineupController as ManagerMatchLineupController;
use App\Http\Controllers\Manager\OfficialController as ManagerOfficialController;
use App\Http\Controllers\Manager\PaymentController as ManagerPaymentController;
use App\Http\Controllers\Manager\PlayerController as ManagerPlayerController;
use App\Http\Controllers\PrivateMediaController;
use App\Http\Controllers\Public\PlayerVerificationController;
use App\Http\Controllers\Public\SeoAndGeoController;
use App\Http\Controllers\PublicEventController;
use Illuminate\Support\Facades\Route;

// SEO & AI Generative Engine Optimization (GEO) Endpoints
Route::get('/robots.txt', [SeoAndGeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoAndGeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/llms.txt', [SeoAndGeoController::class, 'llmsTxt'])->name('seo.llms');
Route::get('/llms-full.txt', [SeoAndGeoController::class, 'llmsFullTxt'])->name('seo.llms-full');

// Area Publik
Route::get('/', [PublicEventController::class, 'index'])->name('home');
Route::get('/events/{event:slug}', [PublicEventController::class, 'show'])->name('events.show');
Route::get('/events/{event:slug}/register', [PublicEventController::class, 'showRegister'])->name('events.register');
Route::post('/events/{event:slug}/register', [PublicEventController::class, 'register'])
    ->middleware('throttle:team-registration')
    ->name('events.register.store');
Route::middleware('throttle:public-verify')->group(function () {
    Route::get('/verify/player/{code}', [PlayerVerificationController::class, 'verify'])->name('public.player.verify');
    Route::get('/verify/player/{code}/photo', [PlayerVerificationController::class, 'photo'])->name('public.player.verify.photo');
    Route::get('/verify/official/{code}', [PlayerVerificationController::class, 'verifyOfficial'])->name('public.official.verify');
    Route::get('/verify/official/{code}/photo', [PlayerVerificationController::class, 'officialPhoto'])->name('public.official.verify.photo');
});

// Autentikasi Manajer Tim & Pemulihan Kata Sandi Mandiri
Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'showManagerLogin'])->name('login');
    Route::post('/login', [SessionController::class, 'managerLogin'])->name('login.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:password-reset')->name('password.store');
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
    Route::put('/password', [ManagerDashboardController::class, 'updatePassword'])->name('password.update');

    Route::get('/payments', [ManagerPaymentController::class, 'index'])->name('payments');
    Route::post('/payments/{payment}/gateway', [ManagerPaymentController::class, 'initiateGateway'])->name('payments.gateway');
    Route::post('/payments/{payment}/simulate-sandbox', [ManagerPaymentController::class, 'simulateSandbox'])->name('payments.simulate');
    Route::post('/payments/{payment}/manual-proof', [ManagerPaymentController::class, 'uploadManualProof'])->name('payments.manual-proof');
    Route::post('/teams/{team}/reclaim-slot', [ManagerPaymentController::class, 'reclaimSlot'])->name('payments.reclaim-slot');

    Route::get('/players', [ManagerPlayerController::class, 'index'])->name('players');
    Route::post('/players/submit-all', [ManagerPlayerController::class, 'submitAllReady'])->name('players.submit-all');
    Route::post('/teams/{team}/players', [ManagerPlayerController::class, 'store'])->name('players.store');
    Route::post('/players/{player}/update', [ManagerPlayerController::class, 'update'])->name('players.update');
    Route::delete('/players/{player}', [ManagerPlayerController::class, 'destroy'])->name('players.destroy');
    Route::post('/players/{player}/documents', [ManagerPlayerController::class, 'uploadDocument'])->name('players.documents.store');
    Route::post('/players/{player}/submit', [ManagerPlayerController::class, 'submitPlayer'])->name('players.submit');
    Route::post('/players/{player}/recall', [ManagerPlayerController::class, 'recallPlayer'])->name('players.recall');

    Route::get('/officials', [ManagerOfficialController::class, 'index'])->name('officials');
    Route::post('/teams/{team}/officials', [ManagerOfficialController::class, 'store'])->name('officials.store');
    Route::post('/teams/{team}/officials/{official}/update', [ManagerOfficialController::class, 'update'])->name('officials.update');
    Route::delete('/teams/{team}/officials/{official}', [ManagerOfficialController::class, 'destroy'])->name('officials.destroy');

    // Akreditasi Skuad Resmi & Penguncian Roster Final
    Route::get('/accreditation', [ManagerAccreditationController::class, 'index'])->name('accreditation.index');
    Route::post('/teams/{team}/lock-roster', [ManagerAccreditationController::class, 'lockRoster'])->name('teams.lock-roster');
    Route::get('/teams/{team}/accreditation/roster-sheet', [ManagerAccreditationController::class, 'downloadRosterSheet'])->name('accreditation.roster-sheet');
    Route::get('/teams/{team}/accreditation/id-cards', [ManagerAccreditationController::class, 'downloadIdCards'])->name('accreditation.id-cards');
    Route::get('/players/{player}/certificate', [ManagerMatchLineupController::class, 'downloadPlayerCertificate'])->name('players.certificate');

    // Fase 5.3: Jadwal Pertandingan Tim & Daftar Susunan Pemain (Digital Match Lineup / DSP)
    Route::get('/matches', [ManagerMatchLineupController::class, 'index'])->name('matches.index');
    Route::post('/matches/{match}/lineup', [ManagerMatchLineupController::class, 'saveLineup'])->name('matches.lineup.save');
    Route::get('/matches/{match}/lineup-pdf', [ManagerMatchLineupController::class, 'downloadLineupPdf'])->name('matches.lineup.pdf');
});

// Area Operasional Admin & Finance
Route::middleware(['admin.portal'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])
        ->middleware('permission:dashboard.admin.view')
        ->name('dashboard');

    Route::put('/password', [AdminDashboardController::class, 'updatePassword'])
        ->name('password.update');

    // Baca (read-only): events.view
    Route::middleware('permission:events.view')->group(function () {
        Route::get('/events', [AdminEventController::class, 'index'])->name('events.index');
        Route::get('/events/{event}/tournament', [AdminTournamentController::class, 'show'])->name('events.tournament');
    });

    // Buat event baru: events.create
    Route::middleware('permission:events.create')->group(function () {
        Route::get('/events/create', [AdminEventController::class, 'create'])->name('events.create');
        Route::post('/events', [AdminEventController::class, 'store'])->name('events.store');
    });

    // Ubah event, jalankan turnamen, jadwal, skor, pengumuman, & rilis sertifikat: events.update
    Route::middleware('permission:events.update')->group(function () {
        Route::post('/events/resync-slots', [AdminEventController::class, 'resyncSlots'])->name('events.resync-slots');
        Route::post('/events/{event}/resync-slots', [AdminEventController::class, 'resyncEventSlots'])->name('events.resync-event-slots');
        Route::get('/events/{event}/edit', [AdminEventController::class, 'edit'])->name('events.edit');
        Route::post('/events/{event}/update', [AdminEventController::class, 'update'])->name('events.update');

        // Fase 5 & 6: Pusat Kendali Turnamen, Grup, Jadwal, Skor, & Pengumuman
        Route::post('/events/{event}/groups/generate', [AdminTournamentController::class, 'generateGroups'])->name('events.groups.generate');
        Route::post('/events/{event}/groups/toggle-lock', [AdminTournamentController::class, 'toggleGroupLock'])->name('events.groups.toggle-lock');
        Route::post('/events/{event}/groups/move-team', [AdminTournamentController::class, 'moveTeamGroup'])->name('events.groups.move-team');
        Route::post('/events/{event}/matches/generate-round-robin', [AdminTournamentController::class, 'generateGroupMatches'])->name('events.matches.generate');
        Route::post('/events/{event}/matches/generate-knockout', [AdminTournamentController::class, 'generateKnockoutMatches'])->name('events.matches.generate-knockout');
        Route::post('/events/{event}/matches', [AdminTournamentController::class, 'storeMatch'])->name('events.matches.store');
        Route::post('/matches/{match}/reschedule', [AdminTournamentController::class, 'rescheduleMatch'])->name('matches.reschedule');
        Route::post('/matches/{match}/result', [AdminTournamentController::class, 'updateMatchResult'])->name('matches.result');
        Route::delete('/matches/{match}', [AdminTournamentController::class, 'destroyMatch'])->name('matches.destroy');
        Route::post('/events/{event}/announcements', [AdminTournamentController::class, 'storeAnnouncement'])->name('events.announcements.store');
        Route::delete('/announcements/{announcement}', [AdminTournamentController::class, 'destroyAnnouncement'])->name('announcements.destroy');
        Route::post('/teams/{team}/achievement', [AdminTournamentController::class, 'updateTeamAchievement'])->name('teams.achievement');
        Route::post('/events/{event}/certificates/toggle-release', [AdminTournamentController::class, 'toggleCertificateRelease'])->name('events.certificates.toggle-release');
    });

    Route::get('/teams', [AdminTeamManagementController::class, 'index'])
        ->middleware('permission:teams.view_all')
        ->name('teams.index');

    Route::get('/teams/export-csv', [AdminTeamManagementController::class, 'exportTeamsCsv'])
        ->middleware('permission:teams.view_all')
        ->name('teams.export');

    Route::get('/teams/export-players-csv', [AdminTeamManagementController::class, 'exportPlayersCsv'])
        ->middleware('permission:teams.view_all')
        ->name('teams.export-players');

    Route::get('/teams/{team}', [AdminTeamManagementController::class, 'show'])
        ->middleware('permission:teams.view_all')
        ->name('teams.show');

    Route::middleware('permission:teams.manage_status')->group(function () {
        Route::post('/teams/{team}/unlock-roster', [AdminTeamManagementController::class, 'unlockRoster'])
            ->name('teams.unlock-roster');
        Route::post('/teams/{team}/force-lock-roster', [AdminTeamManagementController::class, 'forceLockRoster'])
            ->name('teams.force-lock-roster');
        Route::post('/teams/{team}/update-status', [AdminTeamManagementController::class, 'updateStatus'])
            ->name('teams.update-status');
        Route::put('/teams/{team}/notes', [AdminTeamManagementController::class, 'updateNotes'])
            ->name('teams.update-notes');
    });

    Route::post('/teams/{team}/officials/{official}/verify', [AdminTeamManagementController::class, 'verifyOfficial'])
        ->middleware('permission:screening.verify_player')
        ->name('teams.officials.verify');

    Route::get('/teams/{team}/accreditation/roster-sheet', [ManagerAccreditationController::class, 'downloadRosterSheet'])
        ->middleware('permission:teams.view_all')
        ->name('teams.accreditation.roster-sheet');

    Route::get('/teams/{team}/accreditation/id-cards', [ManagerAccreditationController::class, 'downloadIdCards'])
        ->middleware('permission:teams.view_all')
        ->name('teams.accreditation.id-cards');

    // Area Operasional Screening Berkas Pemain & QR Scanner Lapangan
    Route::middleware('permission:screening.view_queue')->group(function () {
        Route::get('/screening', [AdminScreeningController::class, 'index'])->name('screening.index');
        Route::get('/screening/events/{event}/teams/{team}', [AdminScreeningController::class, 'teamReview'])->name('screening.team');
        Route::get('/qr-scanner', [AdminTournamentController::class, 'qrScanner'])->name('qr-scanner');
        Route::post('/qr-scanner/lookup', [AdminTournamentController::class, 'qrLookup'])->middleware('throttle:qr-lookup')->name('qr-scanner.lookup');
    });

    Route::get('/players/{player}/certificate', [ManagerMatchLineupController::class, 'downloadPlayerCertificate'])
        ->middleware('permission:teams.view_all')
        ->name('players.certificate');

    Route::post('/screening/players/{player}/verify', [AdminScreeningController::class, 'verifyPlayer'])
        ->middleware('permission:screening.verify_player')
        ->name('screening.player.verify');

    Route::post('/screening/documents/{document}/verify', [AdminScreeningController::class, 'verifyDocument'])
        ->middleware('permission:screening.verify_player')
        ->name('screening.document.verify');

    Route::post('/screening/players/{player}/unlock', [AdminScreeningController::class, 'unlockPlayer'])
        ->middleware('permission:screening.unlock_player')
        ->name('screening.player.unlock');

    Route::get('/payments/manual-verification', [AdminPaymentVerificationController::class, 'manualQueue'])
        ->middleware('permission:payments.verify_manual')
        ->name('payments.manual-verification');

    Route::post('/payments/{payment}/verify', [AdminPaymentVerificationController::class, 'verifyManual'])
        ->middleware('permission:payments.verify_manual')
        ->name('payments.verify');

    Route::post('/payments/{payment}/mark-refunded', [AdminPaymentVerificationController::class, 'markRefunded'])
        ->middleware('permission:payments.verify_manual')
        ->name('payments.mark-refunded');

    Route::get('/payments/transactions', [AdminPaymentVerificationController::class, 'transactions'])
        ->middleware('permission:payments.view_all')
        ->name('payments.transactions');

    Route::get('/payments/transactions/export-csv', [AdminPaymentVerificationController::class, 'exportCsv'])
        ->middleware('permission:payments.view_all')
        ->name('payments.transactions.export');

    // Audit Logs & Audit Trail (Dukungan URL /admin/audit-logs, /admin/audit-trail, dan /admin/system/audit-logs)
    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])
        ->middleware('permission:audit_logs.view')
        ->name('audit-logs.direct');
    Route::get('/audit-logs/export-csv', [AdminAuditLogController::class, 'export'])
        ->middleware('permission:audit_logs.view')
        ->name('audit-logs.direct.export');
    Route::get('/audit-trail', fn () => redirect()->route('admin.audit-logs.index'))
        ->middleware('permission:audit_logs.view');
    Route::get('/system/audit-logs', [AdminAuditLogController::class, 'index'])
        ->middleware('permission:audit_logs.view')
        ->name('audit-logs.index');
    Route::get('/system/audit-logs/export-csv', [AdminAuditLogController::class, 'export'])
        ->middleware('permission:audit_logs.view')
        ->name('audit-logs.export');

    // Monitoring Integrasi (Dukungan URL /admin/integration-monitor dan /admin/system/integration-monitor)
    Route::get('/integration-monitor', [AdminIntegrationMonitorController::class, 'index'])
        ->middleware('permission:audit_logs.view|system.queue.manage')
        ->name('integration-monitor.direct');
    Route::post('/integration-monitor/jobs/{id}/retry', [AdminIntegrationMonitorController::class, 'retryJob'])
        ->middleware('permission:system.queue.manage');
    Route::delete('/integration-monitor/jobs/{id}', [AdminIntegrationMonitorController::class, 'forgetJob'])
        ->middleware('role:superadmin');
    Route::get('/system/integration-monitor', [AdminIntegrationMonitorController::class, 'index'])
        ->middleware('permission:audit_logs.view|system.queue.manage')
        ->name('integration-monitor.index');
    Route::post('/system/integration-monitor/jobs/{id}/retry', [AdminIntegrationMonitorController::class, 'retryJob'])
        ->middleware('permission:system.queue.manage')
        ->name('integration-monitor.jobs.retry');
    Route::delete('/system/integration-monitor/jobs/{id}', [AdminIntegrationMonitorController::class, 'forgetJob'])
        ->middleware('role:superadmin')
        ->name('integration-monitor.jobs.forget');

    // Pemantauan Antrean Email Transaksional (Dukungan /admin/email-logs dan /admin/system/email-logs)
    Route::middleware('permission:email_logs.manage')->group(function () {
        Route::get('/email-logs', fn () => redirect()->route('admin.email-logs.index'));
        Route::get('/system/email-logs', [AdminEmailLogController::class, 'index'])->name('email-logs.index');
        Route::post('/system/email-logs/retry-failed', [AdminEmailLogController::class, 'retryAllFailed'])->name('email-logs.retry-failed');
        Route::post('/system/email-logs/{log}/retry', [AdminEmailLogController::class, 'retry'])->name('email-logs.retry');
    });

    // Area Khusus Super Administrator
    Route::middleware('role:superadmin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::match(['post', 'put'], '/users/{user}/update', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        Route::get('/roles', [AdminRolePermissionController::class, 'index'])->name('roles.index');
        Route::post('/roles/{role}/permissions', [AdminRolePermissionController::class, 'update'])->name('roles.update');

        Route::get('/settings', [AdminSystemSettingController::class, 'index'])->name('settings.index');
        Route::post('/settings/branding', [AdminSystemSettingController::class, 'updateBranding'])->name('settings.branding');
        Route::post('/settings/payment-gateway', [AdminSystemSettingController::class, 'updatePaymentGateway'])->name('settings.payment-gateway');
    });
});

// Modul Simulasi (Take-Out Ready: Hapus baris di bawah ini saat masuk Full Production)
if (config('simulation.master_enabled', false) && ! app()->isProduction() && file_exists(__DIR__.'/simulation.php')) {
    require __DIR__.'/simulation.php';
}
