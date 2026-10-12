<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminFinanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth:web', 'admin.idle', 'admin']);

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminController::class, 'loginForm'])->name('admin.login');
    Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login.submit');
});

Route::prefix('admin')->name('admin.')->middleware(['auth:web', 'admin.idle', 'admin'])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::middleware('admin:ops.moderator,system.admin')->group(function () {
        Route::get('/providers', [AdminController::class, 'providers'])->name('providers');
        Route::get('/providers/pending', [AdminController::class, 'providersPending'])->name('providers.pending');
        Route::get('/providers/{provider}', [AdminController::class, 'providerDetail'])->name('provider.detail');
        Route::get('/providers/{provider}/documents/{document}', [AdminController::class, 'providerDocument'])
            ->middleware('signed')
            ->name('provider.document');
        Route::post('/providers/{provider}/approve', [AdminController::class, 'providerApprove'])->name('provider.approve');
        Route::post('/providers/{provider}/reject', [AdminController::class, 'providerReject'])->name('provider.reject');
        Route::post('/providers/{provider}/suspend', [AdminController::class, 'providerSuspend'])->name('provider.suspend');
        Route::post('/providers/{provider}/reactivate', [AdminController::class, 'providerReactivate'])->name('provider.reactivate');
    });
    Route::middleware('admin:finance.reviewer,system.admin')->prefix('finance')->name('finance.')->group(function () {
        Route::get('/payments', [AdminFinanceController::class, 'index'])->name('payments');
        Route::get('/payments/{payment}/proof', [AdminFinanceController::class, 'proof'])
            ->middleware('signed')
            ->name('payment.proof');
        Route::post('/payments/{payment}/review', [AdminFinanceController::class, 'review'])->name('payment.review');
    });
    Route::get('/audit-log', [AdminController::class, 'auditLog'])
        ->middleware('admin:system.admin')
        ->name('audit-log');
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
});