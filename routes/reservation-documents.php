<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReservationReceiptController;
use App\Http\Controllers\ReservationDocumentController;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/reservations/{reservation}/ar', [ReservationReceiptController::class, 'show'])->name('admin.reservations.receipt');
    Route::get('/admin/reservations/{reservation}/ar/print', [ReservationReceiptController::class, 'print'])->name('admin.reservations.receipt.print');
    Route::get('/admin/reservations/{reservation}/documents/{document}/preview', [ReservationDocumentController::class, 'preview'])->name('admin.reservations.documents.preview');
});
Route::get('/staff/reservations/{reservation}/ar', [ReservationReceiptController::class, 'show'])
    ->middleware(['auth', 'role:staff', \App\Http\Middleware\StaffReadOnly::class])->name('staff.reservations.receipt');
Route::middleware(['auth', 'role:staff', \App\Http\Middleware\StaffReadOnly::class])->group(function () {
    Route::get('/staff/acknowledgements', [ReservationReceiptController::class, 'index'])->name('staff.ar.index');
    Route::get('/staff/reservations/{reservation}/ar/pdf', [ReservationReceiptController::class, 'pdf'])->name('staff.reservations.receipt.pdf');
    Route::post('/staff/reservations/{reservation}/ar/start', [ReservationReceiptController::class, 'start'])->name('staff.reservations.receipt.start');
    Route::post('/staff/reservations/{reservation}/ar/complete', [ReservationReceiptController::class, 'complete'])->name('staff.reservations.receipt.complete');
    Route::get('/staff/reservations/{reservation}/ar/print', [ReservationReceiptController::class, 'print'])->name('staff.reservations.receipt.print');
    Route::post('/staff/reservations/{reservation}/ar/prepare', [ReservationReceiptController::class, 'prepare'])->name('staff.reservations.receipt.prepare');
    Route::post('/staff/reservations/{reservation}/ar/send', [ReservationReceiptController::class, 'send'])->middleware('throttle:5,1')->name('staff.reservations.receipt.send');
});
Route::get('/acknowledgements/{receipt}', [ReservationReceiptController::class, 'publicPrint'])
    ->middleware(['signed:relative', 'throttle:30,1'])->name('public.reservations.receipt');
