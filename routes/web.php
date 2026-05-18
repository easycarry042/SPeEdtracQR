<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DocumentWebController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CitizenController;

/*
|--------------------------------------------------------------------------
| Root — redirect authenticated users to their role-specific dashboard
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()->hasRole('admin')
        ? redirect()->route('admin.dashboard')
        : redirect()->route('dashboard');
});

/*
|--------------------------------------------------------------------------
| Public Tracking Routes (no auth required)
|--------------------------------------------------------------------------
*/

Route::get('/track', [TrackController::class, 'index'])->name('track.index');
Route::get('/track-search', [TrackController::class, 'index'])->name('track.search');
Route::get('/track/{trackingNumber}', [TrackController::class, 'show'])->name('track.show');

/*
|--------------------------------------------------------------------------
| Citizen / Guest Routes (no auth required)
|--------------------------------------------------------------------------
*/

Route::prefix('citizen')->name('citizen.')->group(function () {
    Route::get('/',      [CitizenController::class, 'index'])->name('dashboard');
    Route::get('/track', [CitizenController::class, 'track'])->name('track');
});

/*
|--------------------------------------------------------------------------
| Admin Routes (auth + admin role required)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    });

/*
|--------------------------------------------------------------------------
| Staff / Authenticated Routes
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
