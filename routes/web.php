<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\BayController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\loginController;
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [loginController::class, 'showLoginForm'])->name('loginform');
Route::post('/login', [loginController::class, 'login'])->name('login.perform');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [loginController::class, 'logout'])->name('logout');

    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');

    Route::get('/dtr', [AttendanceController::class, 'show'])->name('dtr.show');
    Route::post('/dtr/clock-in', [AttendanceController::class, 'clockIn'])->name('dtr.clock-in');
    Route::post('/dtr/clock-out', [AttendanceController::class, 'clockOut'])->name('dtr.clock-out');
    Route::get('/dashboard/status', [DashboardController::class, 'status'])->name('dashboard.status');
    Route::get('/dashboard/customers/search', [DashboardController::class, 'searchCustomers'])->name('customers.search');

    Route::get('/receipts/{transaction}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::post('/receipts/{transaction}/settle', [ReceiptController::class, 'settle'])->name('receipts.settle');

    Route::post('/bays/{bay}/allocate', [BayController::class, 'allocate'])->name('bays.allocate');
    Route::post('/bays/{bay}/queue', [BayController::class, 'queue'])->name('bays.queue');
    Route::post('/queue/{bayQueue}/cancel', [BayController::class, 'cancelQueue'])->name('queue.cancel');

    Route::post('/bay-sessions/{baySession}/extend', [BayController::class, 'extend'])->name('sessions.extend');
    Route::post('/bay-sessions/{baySession}/checkout', [BayController::class, 'checkout'])->name('sessions.checkout');

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/transactions', [AdminController::class, 'transactions'])->name('transactions');
        Route::get('/employees', [AdminController::class, 'employees'])->name('employees');
        Route::post('/employees', [AdminController::class, 'storeEmployee'])->name('employees.store');

        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::post('/users/{user}/password', [AdminController::class, 'resetUserPassword'])->name('users.password');
        Route::post('/users/{user}/role', [AdminController::class, 'updateUserRole'])->name('users.role');
        Route::get('/customers', [AdminController::class, 'customers'])->name('customers');
        Route::get('/activity', [AdminController::class, 'activity'])->name('activity');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
        Route::post('/bays/{bay}/maintenance', [AdminController::class, 'toggleMaintenance'])->name('bays.maintenance');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    });
});
