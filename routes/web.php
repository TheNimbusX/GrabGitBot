<?php

use App\Http\Controllers\Admin\FitbotAdminController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/telegram/webhook', TelegramWebhookController::class);

Route::prefix('admin')->group(function () {
    Route::get('login', [FitbotAdminController::class, 'showLogin'])->name('admin.login');
    Route::post('login', [FitbotAdminController::class, 'login']);
    Route::middleware('fitbot.admin')->group(function () {
        Route::get('/', [FitbotAdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::post('broadcast/preview', [FitbotAdminController::class, 'broadcastPreview'])->name('admin.broadcast.preview');
        Route::post('broadcast/confirm', [FitbotAdminController::class, 'broadcastConfirm'])->name('admin.broadcast.confirm');
        Route::post('broadcast/cancel', [FitbotAdminController::class, 'broadcastCancel'])->name('admin.broadcast.cancel');
        Route::post('users/{user}/delete', [FitbotAdminController::class, 'destroyUser'])->name('admin.user.destroy');
        Route::post('support/{supportMessage}/read', [FitbotAdminController::class, 'markSupportRead'])->name('admin.support.read');
        Route::post('support/{supportMessage}/unread', [FitbotAdminController::class, 'markSupportUnread'])->name('admin.support.unread');
        Route::post('support/{supportMessage}/delete', [FitbotAdminController::class, 'destroySupportMessage'])->name('admin.support.destroy');
        Route::post('logout', [FitbotAdminController::class, 'logout'])->name('admin.logout');
    });
});
