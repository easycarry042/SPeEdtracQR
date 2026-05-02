<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DocumentWebController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScanController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return auth()->check() ? redirect('/dashboard') : redirect()->route('login');
});

Route::get('/track', [TrackController::class, 'index'])->name('track.index');
Route::get('/track-search', [TrackController::class, 'index'])->name('track.search');
Route::get('/track/{trackingNumber}', [TrackController::class, 'show'])->name('track.show');


/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    Route::get('/scanner', fn () => redirect()->route('scan.index'))->name('scanner');
    Route::get('/documents/create', [DocumentWebController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentWebController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}/created', [DocumentWebController::class, 'created'])->name('documents.created');
    Route::get('/documents/{document}/sticker', [DocumentWebController::class, 'printSticker'])->name('documents.sticker');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
    Route::get('/analytics/data', [AnalyticsController::class, 'chartData'])->name('analytics.data');

    Route::get('/history', [HistoryController::class, 'index'])->name('history');
    Route::get('/history/export', [HistoryController::class, 'export'])->name('history.export');
});

require __DIR__.'/auth.php';