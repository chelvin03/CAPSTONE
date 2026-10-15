<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FeedbackController;

Route::get('/feedback', [FeedbackController::class, 'index'])->name('public.feedback');
Route::post('/feedback', [FeedbackController::class, 'requestLink'])->middleware('throttle:5,1')->name('public.feedback.request');
Route::get('/feedback/submit/{reservation}', [FeedbackController::class, 'form'])->middleware(['signed', 'throttle:30,1'])->name('public.feedback.form');
Route::post('/feedback/submit/{reservation}', [FeedbackController::class, 'store'])->middleware(['signed', 'throttle:10,1'])->name('public.feedback.store');
Route::get('/admin/feedback', [\App\Http\Controllers\Admin\FeedbackController::class, 'index'])->middleware(['auth', 'role:admin'])->name('admin.feedback.index');
