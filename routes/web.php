<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicReservationController;
use App\Http\Controllers\Staff\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [\App\Http\Controllers\PublicHomeController::class, 'index'])->name('home');
Route::get('/schedule', [\App\Http\Controllers\PublicHomeController::class, 'schedule'])->name('public.schedule');

Route::get('/reserve', [\App\Http\Controllers\PublicHomeController::class, 'index'])->name('reservation.landing');

Route::get('/reserve/request', [PublicReservationController::class, 'create'])
    ->middleware('prevent.back.history')
    ->name('reservation.create');

Route::post('/reserve', [PublicReservationController::class, 'store'])
    ->name('reservation.store');

Route::post('/reserve/send-code', [PublicReservationController::class, 'sendVerificationCode'])
    ->middleware('throttle:5,1')->name('reservation.send_code');

Route::post('/reserve/verify-code', [PublicReservationController::class, 'verifyVerificationCode'])
    ->middleware('throttle:30,1')->name('reservation.verify_code');

Route::post('/reserve/edit-details', [PublicReservationController::class, 'editDetails'])
    ->name('reservation.edit_details');

Route::get('/reserve/availability', [PublicReservationController::class, 'availability'])
    ->name('reservation.availability');

Route::get('/track', [PublicReservationController::class, 'track'])
    ->name('reservation.track');

Route::get('/schedule-proposals/{proposal}', [\App\Http\Controllers\ScheduleProposalController::class, 'show'])
    ->middleware(['signed', 'throttle:30,1'])->name('reservation.schedule-proposal');
Route::post('/schedule-proposals/{proposal}', [\App\Http\Controllers\ScheduleProposalController::class, 'respond'])
    ->middleware(['signed', 'throttle:10,1'])->name('reservation.schedule-proposal.respond');

Route::post('/reservation/{reservation}/cancel', [PublicReservationController::class, 'cancel'])
    ->middleware('throttle:10,1')->name('reservation.cancel');

Route::get('/reservation/{reservation}/equipment-access', [\App\Http\Controllers\EquipmentRequestController::class, 'access'])
    ->middleware(['signed', 'throttle:30,1'])->name('reservation.equipment.access');
Route::post('/reservation/{reservation}/equipment/{equipment}/response', [\App\Http\Controllers\EquipmentRequestController::class, 'respond'])
    ->middleware('throttle:30,1')->name('reservation.equipment.respond');

Route::get(
    '/reservation/success/{referenceNumber}',
    [PublicReservationController::class, 'success']
)->name('reservation.success');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'verified',
    'prevent.back.history',
])->group(function (): void {

    /*
    |--------------------------------------------------------------------------
    | Role-Based Dashboard Redirect
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', function () {
        $user = request()->user();

        abort_unless($user, 403);

        return match ($user->role) {
            'admin' => redirect()->route('admin.dashboard'),
            'staff' => redirect()->route('staff.dashboard'),
            default => abort(403),
        };
    })->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')
        ->prefix('admin')
        ->name('admin.')
        ->group(function (): void {

            Route::get('/dashboard', DashboardController::class)
                ->name('dashboard');
            Route::get('/analytics/{section?}', \App\Http\Controllers\Admin\AnalyticsController::class)
                ->where('section', 'reservations|operations|utilization')->name('analytics');
            Route::put('/dashboard/kpi-targets', [\App\Http\Controllers\Admin\KpiTargetController::class, 'update'])->name('dashboard.kpi-targets');

            Route::get('/requestor-restrictions', [\App\Http\Controllers\Admin\RequestorRestrictionController::class, 'index'])->name('requestor-restrictions.index');
            Route::post('/requestor-restrictions', [\App\Http\Controllers\Admin\RequestorRestrictionController::class, 'store'])->name('requestor-restrictions.store');
            Route::delete('/requestor-restrictions/{restriction}', [\App\Http\Controllers\Admin\RequestorRestrictionController::class, 'destroy'])->name('requestor-restrictions.destroy');

            Route::resource('staff', StaffController::class)
                ->only(['index', 'create', 'store', 'edit', 'update']);
            Route::patch('/staff/{staff}/permissions', [StaffController::class, 'updatePermissions'])->name('staff.permissions');

            Route::get('/reports', [AdminReportController::class, 'index'])
                ->name('reports.index');
            Route::get('/reports-export', [AdminReportController::class, 'export'])->name('reports.export');
            Route::delete('/reports/recent/{key}', [AdminReportController::class, 'forgetRecent'])->name('reports.recent.destroy');
            Route::get('/reports/generate/excel', [AdminReportController::class, 'excel'])->name('reports.excel');
            Route::get('/reports/generate/pdf', [AdminReportController::class, 'pdf'])->name('reports.pdf');
            Route::get('/reports/generate/word', [AdminReportController::class, 'word'])->name('reports.word');
            Route::get('/reports/{report}', [AdminReportController::class, 'show'])
                ->name('reports.show');

            Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
            Route::get('/planning-schedule', [\App\Http\Controllers\Admin\PlanningScheduleController::class, 'index'])->name('planning.index');
            Route::patch('/planning-schedule/{reservation}/preparation', [\App\Http\Controllers\Admin\PlanningScheduleController::class, 'update'])->name('planning.update');
            Route::post('/schedule/blocks', [ScheduleController::class, 'store'])->name('schedule.blocks.store');
            Route::post('/schedule/blocks/preview', [ScheduleController::class, 'preview'])->name('schedule.blocks.preview');
            Route::delete('/schedule/blocks/{block}', [ScheduleController::class, 'destroy'])->name('schedule.blocks.destroy');
            Route::get('/settings', [SystemSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [SystemSettingsController::class, 'update'])->name('settings.update');
            Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
            Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit.export');

            Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
            Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
            Route::post('/backups/import', [BackupController::class, 'import'])->name('backups.import');
            Route::get('/backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
            Route::post('/backups/{backup}/verify', [BackupController::class, 'verify'])->name('backups.verify');
            Route::post('/backups/{backup}/restore', [BackupController::class, 'restore'])->name('backups.restore');
            Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');

            Route::resource('equipment', EquipmentController::class)
                ->except(['show']);

            Route::get('/reservation-follow-up', [\App\Http\Controllers\Admin\ReservationFollowUpController::class, 'index'])->name('reservations.follow-up');
            Route::post('/reservation-follow-up/send', [\App\Http\Controllers\Admin\ReservationFollowUpController::class, 'send'])->middleware('throttle:20,1')->name('reservations.follow-up.send');

            Route::get('/reservations/waiting-list', [\App\Http\Controllers\Admin\WaitingListController::class, 'index'])->name('reservations.waiting-list');
            Route::patch('/reservations/{reservation}/review', [\App\Http\Controllers\Admin\ReservationReviewController::class, 'update'])->name('reservations.review');

            Route::resource('reservations', ReservationController::class)
                ->only([
                    'index',
                    'create',
                    'store',
                    'show',
                    'edit',
                    'update',
                ]);

            Route::post('/reservations/{reservation}/equipment/{equipment}/review', [\App\Http\Controllers\EquipmentRequestController::class, 'review'])
                ->name('reservations.equipment.review');

            Route::patch(
                '/reservations/{reservation}/approve',
                [ReservationController::class, 'approve']
            )->name('reservations.approve');

            Route::patch(
                '/reservations/{reservation}/reject',
                [ReservationController::class, 'reject']
            )->name('reservations.reject');

            Route::patch(
                '/reservations/{reservation}/cancel',
                [ReservationController::class, 'cancel']
            )->name('reservations.cancel');
            Route::patch('/reservations/{reservation}/reschedule', [ReservationController::class, 'reschedule'])->name('reservations.reschedule');
            Route::post('/reservations/{reservation}/reschedule-preview', [ReservationController::class, 'reschedulePreview'])->name('reservations.reschedule-preview');
            Route::post('/reservations/{reservation}/outcome', [\App\Http\Controllers\EventOutcomeController::class, 'store'])->name('reservations.outcome');
            Route::post('/reservations/{reservation}/equipment/{equipment}/return', [\App\Http\Controllers\EquipmentReturnController::class, 'store'])->name('reservations.equipment.return');
        });

    /*
    |--------------------------------------------------------------------------
    | Staff Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware(['role:staff', \App\Http\Middleware\StaffReadOnly::class])
        ->prefix('staff')
        ->name('staff.')
        ->group(function (): void {

            Route::get('/dashboard', \App\Http\Controllers\Staff\DashboardController::class)->name('dashboard');
            Route::get('/schedules', [\App\Http\Controllers\Staff\ScheduleController::class, 'index'])->name('schedules.index');

            Route::get(
                '/reservations',
                [\App\Http\Controllers\Staff\ReservationController::class, 'index']
            )->name('reservations.index');

            Route::get(
                '/reservations/{reservation}',
                [\App\Http\Controllers\Staff\ReservationController::class, 'show']
            )->name('reservations.show');

            Route::get('/equipment', [EquipmentController::class, 'staffIndex'])
                ->name('equipment.index');
            Route::post('/reservations/{reservation}/outcome', [\App\Http\Controllers\EventOutcomeController::class, 'store'])->name('reservations.outcome');
            Route::post('/reservations/{reservation}/equipment/{equipment}/return', [\App\Http\Controllers\EquipmentReturnController::class, 'store'])->name('reservations.equipment.return');

            Route::get('/reports', [ReportController::class, 'index'])
                ->name('reports.index');

            Route::get('/reports/export', [ReportController::class, 'export'])
                ->name('reports.export');

            Route::post('/reports', [ReportController::class, 'store'])
                ->name('reports.store');
        });

    /*
    |--------------------------------------------------------------------------
    | Profile Routes
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/feedback.php';
require __DIR__.'/reservation-documents.php';
