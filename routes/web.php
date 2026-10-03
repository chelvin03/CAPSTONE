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
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicReservationController;
use App\Http\Controllers\Staff\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::get('/reserve', [PublicReservationController::class, 'create'])
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

            Route::resource('facilities', FacilityController::class)
                ->except(['show']);

            Route::resource('staff', StaffController::class)
                ->only(['index', 'create', 'store']);
            Route::patch('/staff/{staff}/permissions', [StaffController::class, 'updatePermissions'])->name('staff.permissions');

            Route::get('/reports', [AdminReportController::class, 'index'])
                ->name('reports.index');
            Route::get('/reports-export', [AdminReportController::class, 'export'])->name('reports.export');
            Route::get('/reports/generate/excel', [AdminReportController::class, 'excel'])->name('reports.excel');
            Route::get('/reports/generate/word', [AdminReportController::class, 'word'])->name('reports.word');
            Route::get('/reports/{report}', [AdminReportController::class, 'show'])
                ->name('reports.show');

            Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
            Route::post('/schedule/blocks', [ScheduleController::class, 'store'])->name('schedule.blocks.store');
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

            Route::resource('reservations', ReservationController::class)
                ->only([
                    'index',
                    'create',
                    'store',
                    'show',
                    'edit',
                    'update',
                ]);

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
        });

    /*
    |--------------------------------------------------------------------------
    | Staff Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:staff')
        ->prefix('staff')
        ->name('staff.')
        ->group(function (): void {

            Route::get('/dashboard', function () {
                return view('staff.dashboard');
            })->name('dashboard');

            Route::get(
                '/reservations',
                [ReservationController::class, 'staffIndex']
            )->name('reservations.index');

            Route::get(
                '/reservations/{reservation}',
                [ReservationController::class, 'staffShow']
            )->name('reservations.show');

            Route::get(
                '/facilities',
                [FacilityController::class, 'staffIndex']
            )->name('facilities.index');

            Route::get('/equipment', [EquipmentController::class, 'staffIndex'])
                ->name('equipment.index');

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
