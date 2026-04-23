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
        Route::post('broadcast', [FitbotAdminController::class, 'broadcast'])->name('admin.broadcast');
        Route::post('logout', [FitbotAdminController::class, 'logout'])->name('admin.logout');
    });
});
