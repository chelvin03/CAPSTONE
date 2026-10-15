<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReservationReceiptController;
use App\Http\Controllers\ReservationDocumentController;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/reservations/{reservation}/ar', [ReservationReceiptController::class, 'show'])->name('admin.reservations.receipt');
    Route::get('/admin/reservations/{reservation}/documents/{document}/preview', [ReservationDocumentController::class, 'preview'])->name('admin.reservations.documents.preview');
});
Route::get('/staff/reservations/{reservation}/ar', [ReservationReceiptController::class, 'show'])
    ->middleware(['auth', 'role:staff', \App\Http\Middleware\StaffReadOnly::class])->name('staff.reservations.receipt');
