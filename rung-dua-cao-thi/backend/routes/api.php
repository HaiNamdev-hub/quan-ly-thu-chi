<?php

use App\Http\Controllers\Api\Admin\ExcelImportController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\GuideController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PhotoPriceController;
use App\Http\Controllers\Api\TourController;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/cash-reconciliation', [DashboardController::class, 'cashReconciliation'])->middleware('role:ADMIN,MANAGER');
    Route::apiResource('expenses', ExpenseController::class)->except(['create', 'edit', 'show'])->middleware('role:ADMIN,MANAGER');
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::get('/inventory/transactions', [InventoryController::class, 'transactions'])->middleware('role:ADMIN,MANAGER');
    Route::post('/inventory/transactions', [InventoryController::class, 'storeTransaction'])->middleware('role:ADMIN,MANAGER');
    Route::post('/inventory/items', [InventoryController::class, 'store'])->middleware('role:ADMIN,MANAGER');
    Route::put('/inventory/items/{inventoryItem}', [InventoryController::class, 'update'])->middleware('role:ADMIN,MANAGER');
    Route::get('/photos', [PhotoPriceController::class, 'index']);
    Route::get('/admin/photo-prices', [PhotoPriceController::class, 'history'])->middleware('role:ADMIN');
    Route::post('/admin/photo-prices', [PhotoPriceController::class, 'store'])->middleware('role:ADMIN');

    Route::get('/guides', [GuideController::class, 'index']);
    Route::get('/guides/{guide}/history', [GuideController::class, 'history'])->middleware('role:ADMIN,MANAGER');
    Route::post('/guides', [GuideController::class, 'store'])->middleware('role:ADMIN,MANAGER');
    Route::put('/guides/{guide}', [GuideController::class, 'update'])->middleware('role:ADMIN,MANAGER');

    Route::apiResource('tours', TourController::class)->except(['create', 'edit']);
    Route::post('/tours/{tour}/submit', [TourController::class, 'submit']);
    Route::post('/tours/{tour}/approve', [TourController::class, 'approve'])->middleware('role:ADMIN,MANAGER');
    Route::post('/tours/{tour}/lock', [TourController::class, 'lock'])->middleware('role:ADMIN,MANAGER');
    Route::post('/tours/{tour}/unlock', [TourController::class, 'unlock'])->middleware('role:ADMIN');

    Route::middleware('role:ADMIN,MANAGER')->get('/audit-logs', function (Request $request) {
        $query = AuditLog::query()->with('user:id,name')->latest('created_at');

        if ($request->user()->role->value === 'MANAGER') {
            $query->whereNotIn('action', ['user.created', 'user.updated', 'user.password_reset']);
        }

        return $query->paginate(50);
    });

    Route::middleware('role:ADMIN')->prefix('admin')->group(function (): void {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
        Route::get('/users/{user}/activity', [UserController::class, 'activity']);
        Route::get('/imports', [ExcelImportController::class, 'index']);
        Route::post('/imports/upload', [ExcelImportController::class, 'upload']);
        Route::post('/imports/{batch}/analyze', [ExcelImportController::class, 'analyze']);
        Route::get('/imports/{batch}/preview', [ExcelImportController::class, 'preview']);
        Route::post('/imports/{batch}/confirm', [ExcelImportController::class, 'confirm']);
        Route::post('/imports/{batch}/include-in-dashboard', [ExcelImportController::class, 'includeInDashboard']);
        Route::get('/audit-logs', function (Request $request) {
            return AuditLog::query()->with('user:id,name')->latest('created_at')->paginate(50);
        });
    });
});
