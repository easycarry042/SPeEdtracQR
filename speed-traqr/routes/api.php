<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']); // you'll need to create AuthController

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('documents', DocumentController::class);
    Route::get('documents/history/{trackingNumber}', [DocumentController::class, 'history']);
    Route::post('documents/scan', [DocumentController::class, 'scan']);
    Route::get('dashboard', [DashboardController::class, 'index']);
});