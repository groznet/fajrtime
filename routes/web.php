<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PrayerTimeController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/prayer-times', [PrayerTimeController::class, 'index'])->name('prayer-times');

// Static pages (placeholder text until the content is written).
Route::view('/how-it-works', 'pages.placeholder', ['title' => 'Как это работает'])->name('how-it-works');
Route::view('/faq', 'pages.placeholder', ['title' => 'Вопросы и ответы'])->name('faq');
Route::view('/contact', 'pages.placeholder', ['title' => 'Контакты'])->name('contact');
Route::view('/terms', 'pages.placeholder', ['title' => 'Пользовательское соглашение'])->name('terms');
Route::view('/privacy', 'pages.placeholder', ['title' => 'Политика конфиденциальности'])->name('privacy');
Route::view('/offer', 'pages.placeholder', ['title' => 'Публичная оферта'])->name('offer');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
});

require __DIR__.'/auth.php';
