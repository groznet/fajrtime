<?php

use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PrayerTimeController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\Webhooks\YooKassaWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/prayer-times', [PrayerTimeController::class, 'index'])->name('prayer-times');

// Static pages (placeholder text until the content is written).
Route::view('/how-it-works', 'pages.placeholder', ['title' => 'Как это работает'])->name('how-it-works');
Route::view('/faq', 'pages.placeholder', ['title' => 'Вопросы и ответы'])->name('faq');
Route::view('/contact', 'legal.contact')->name('contact');
Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/offer', 'legal.offer')->name('offer');
Route::view('/consent', 'legal.consent')->name('consent');

Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
Route::get('/groups/{group:slug}', [GroupController::class, 'show'])->name('groups.show');
Route::get('/invite/{token}', [GroupController::class, 'invite'])->name('groups.invite');

Route::middleware('auth')->group(function () {
    Route::get('/check-in', [CheckInController::class, 'show'])->name('check-in');
    Route::get('/check-in/state', [CheckInController::class, 'state'])->middleware('throttle:30,1')->name('check-in.state');
    Route::post('/check-in', [CheckInController::class, 'store'])->middleware('throttle:20,1')->name('check-in.store');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');

    Route::get('/my/groups', [GroupController::class, 'mine'])->name('groups.mine');
    Route::get('/my/groups/create', [GroupController::class, 'create'])->name('groups.create');
    Route::post('/my/groups', [GroupController::class, 'store'])->middleware('throttle:10,1')->name('groups.store');
    Route::post('/groups/{group:slug}/join', [GroupController::class, 'join'])->name('groups.join');
    Route::post('/groups/{group:slug}/leave', [GroupController::class, 'leave'])->name('groups.leave');
    Route::post('/groups/{group:slug}/challenges', [ChallengeController::class, 'store'])->name('challenges.store');

    Route::get('/challenges/{challenge}', [ChallengeController::class, 'show'])->name('challenges.show');
    Route::post('/challenges/{challenge}/join', [ChallengeController::class, 'join'])->middleware('throttle:20,1')->name('challenges.join');
    Route::post('/challenges/{challenge}/leave', [ChallengeController::class, 'leave'])->middleware('throttle:20,1')->name('challenges.leave');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet');
    Route::post('/wallet/top-up', [WalletController::class, 'topUp'])->middleware('throttle:10,1')->name('wallet.top-up');
    Route::get('/wallet/payments/{payment}/return', [WalletController::class, 'paymentReturn'])->name('wallet.payments.return');
    Route::post('/wallet/withdrawals', [WalletController::class, 'withdraw'])->middleware('throttle:5,1')->name('wallet.withdraw');
});

// CSRF is disabled for webhooks/* in bootstrap/app.php.
Route::post('/webhooks/yookassa', YooKassaWebhookController::class)->middleware('throttle:120,1')->name('webhooks.yookassa');

require __DIR__.'/auth.php';
