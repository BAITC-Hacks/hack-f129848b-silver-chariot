<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\CompletionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\SessionRoleController;
use App\Http\Controllers\UploadController;
use App\Http\Middleware\EnsureEmployeeAccess;
use App\Http\Middleware\EnsureHrRole;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('employees.index'));
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::post('/session/role', [SessionRoleController::class, 'store'])->name('session.role');
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::middleware(EnsureEmployeeAccess::class)->group(function (): void {
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::post('/employees/{employee}/recommendations', [RecommendationController::class, 'store'])->name('employees.recommendations');
        Route::post('/employees/{employee}/complete', [CompletionController::class, 'store'])->name('employees.complete');
    });
    Route::middleware(EnsureHrRole::class)->group(function (): void {
        Route::get('/hr', [HrController::class, 'index'])->name('hr.index');
        Route::resource('/hr/events', EventController::class)->except('show')->names('hr.events');
        Route::get('/admin/upload', [UploadController::class, 'create'])->name('admin.upload');
        Route::post('/admin/upload', [UploadController::class, 'store'])->name('admin.upload.store');
    });
});
