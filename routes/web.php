<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BusinessHourController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SlaPolicyController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketReplyController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->prefix('tickets')->name('tickets.')->group(function () {
    Route::get('/', [TicketController::class, 'index'])->name('index');
    Route::get('/resolved', [TicketController::class, 'resolvedIndex'])->name('resolved');
    Route::get('/create', [TicketController::class, 'create'])->name('create');
    Route::post('/', [TicketController::class, 'store'])->name('store');
    Route::get('/{ticket}', [TicketController::class, 'show'])->name('show');
    Route::get('/{ticket}/edit', [TicketController::class, 'edit'])->name('edit');
    Route::patch('/{ticket}', [TicketController::class, 'update'])->name('update');
    Route::patch('/{ticket}/status', [TicketController::class, 'updateStatus'])->name('status');
    Route::post('/{ticket}/resolve', [TicketController::class, 'resolve'])->name('resolve');
    Route::post('/{ticket}/close', [TicketController::class, 'close'])->name('close');
    Route::post('/{ticket}/reopen', [TicketController::class, 'reopen'])->name('reopen');
    Route::post('/{ticket}/replies', [TicketReplyController::class, 'store'])->name('replies.store');
    Route::post('/{ticket}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::post('/{ticket}/accept', [TicketController::class, 'accept'])->name('accept');
    Route::post('/{ticket}/assign', [TicketController::class, 'assign'])->name('assign');
    Route::post('/assign-unassigned', [TicketController::class, 'assignUnassigned'])->name('assign-unassigned');
});

Route::middleware('auth')->prefix('attachments')->name('attachments.')->group(function () {
    Route::get('/{attachment}/download', [AttachmentController::class, 'download'])->name('download');
    Route::delete('/{attachment}', [AttachmentController::class, 'destroy'])->name('destroy');
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/sla-policies', [SlaPolicyController::class, 'index'])->name('sla-policies.index');
    Route::patch('/sla-policies/{slaPolicy}', [SlaPolicyController::class, 'update'])->name('sla-policies.update');
    Route::get('/business-hours', [BusinessHourController::class, 'index'])->name('business-hours.index');
    Route::patch('/business-hours/{businessHour}', [BusinessHourController::class, 'update'])->name('business-hours.update');
});

Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::post('/{notification}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('markAsRead');
});

require __DIR__.'/auth.php';
