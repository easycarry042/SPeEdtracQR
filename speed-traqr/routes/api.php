<?php

use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/scan', [ScanController::class, 'store'])->name('api.scan.store');
    Route::post('/scan/sync', [ScanController::class, 'sync'])->name('api.scan.sync');
});