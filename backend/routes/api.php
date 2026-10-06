<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BillingReportController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);
    Route::put('/customers/{customer}', [CustomerController::class, 'update']);
    Route::get('/billings', [BillingController::class, 'index']);
    Route::post('/billings', [BillingController::class, 'store']);
    Route::get('/billings/{billing}', [BillingController::class, 'show']);
    Route::put('/billings/{billing}', [BillingController::class, 'update']);
    Route::post('/billings/{billing}/payment', [BillingController::class, 'payment']);
    Route::get('/reports/billings', [BillingReportController::class, 'index']);
    Route::get('/reports/billings.csv', [BillingReportController::class, 'export']);
    Route::get('/reports/billings.pdf', [BillingReportController::class, 'exportPdf']);
});
