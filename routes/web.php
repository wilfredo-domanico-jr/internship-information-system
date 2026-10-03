<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Adviser;
use App\Http\Controllers\Auth\AccountStatusController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterCompanyController;
use App\Http\Controllers\Auth\RegisterInternController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\Company;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Intern;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

/* Guests */
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::get('register/intern', [RegisterInternController::class, 'create'])->name('register.intern');
    Route::post('register/intern', [RegisterInternController::class, 'store'])->name('register.intern.store');
    Route::get('register/company', [RegisterCompanyController::class, 'create'])->name('register.company');
    Route::post('register/company', [RegisterCompanyController::class, 'store'])->name('register.company.store');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
    Route::get('password/forgot', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('password/forgot', [ForgotPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('password/reset/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('password/reset', [ResetPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
});

/* Signed-in users, regardless of account state (a pending company can still log out) */
Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
});

/* Any signed-in user */
Route::middleware(['auth', 'account.usable'])->group(function () {
    Route::get('account/pending', [AccountStatusController::class, 'pending'])->name('account.pending');
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');
    Route::put('password', [ChangePasswordController::class, 'update'])->name('password.update');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/avatar', [AvatarController::class, 'store'])->name('profile.avatar.store');
    Route::delete('profile/avatar', [AvatarController::class, 'destroy'])->name('profile.avatar.destroy');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('files/{kind}/{id}', [FileController::class, 'show'])->where('id', '[0-9]+')->name('files.show');

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('dashboard', Admin\DashboardController::class)->name('dashboard');
    });

    Route::prefix('adviser')->name('adviser.')->middleware('role:adviser')->group(function () {
        Route::get('dashboard', Adviser\DashboardController::class)->name('dashboard');
    });

    Route::prefix('company')->name('company.')->middleware('role:company')->group(function () {
        Route::get('dashboard', Company\DashboardController::class)->name('dashboard');
    });

    Route::prefix('intern')->name('intern.')->middleware('role:intern')->group(function () {
        Route::get('dashboard', Intern\DashboardController::class)->name('dashboard');
    });
});
