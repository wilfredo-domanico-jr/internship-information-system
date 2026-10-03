<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Adviser;
use App\Http\Controllers\Auth\AccountStatusController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterInternController;
use App\Http\Controllers\Company;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\Intern;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

/* Guests */
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::get('register/intern', [RegisterInternController::class, 'create'])->name('register.intern');
    Route::post('register/intern', [RegisterInternController::class, 'store'])->name('register.intern.store');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

/* Signed-in users, regardless of account state (a pending company can still log out) */
Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
});

/* Any signed-in user */
Route::middleware(['auth', 'account.usable'])->group(function () {
    Route::get('account/pending', [AccountStatusController::class, 'pending'])->name('account.pending');
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');

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
