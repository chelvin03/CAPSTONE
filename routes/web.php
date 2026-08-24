<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicReservationController;
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
    ->name('reservation.create');

Route::post('/reserve', [PublicReservationController::class, 'store'])
    ->name('reservation.store');

Route::post('/reserve/send-verification', [PublicReservationController::class, 'sendVerification'])
    ->middleware('throttle:5,1')
    ->name('reservation.verification.send');

Route::post('/reserve/verify-email', [PublicReservationController::class, 'verifyEmail'])
    ->middleware('throttle:10,1')
    ->name('reservation.verification.verify');

Route::get('/reserve/availability', [PublicReservationController::class, 'availability'])
    ->name('reservation.availability');

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

            Route::resource('equipment', EquipmentController::class)
                ->except(['show']);

            Route::resource('reservations', ReservationController::class)
                ->only([
                    'index',
                    'create',
                    'store',
                    'show',
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

            Route::get(
                '/equipment',
                [EquipmentController::class, 'staffIndex']
            )->name('equipment.index');
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
