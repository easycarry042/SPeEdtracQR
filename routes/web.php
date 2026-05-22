<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DepartmentController as AdminDepartmentController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CitizenController;
use App\Http\Controllers\CitizenDocumentUploadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentWebController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Root — redirect authenticated users to their role-specific dashboard
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (! auth()->check()) {
        return view('welcome');
    }

    return auth()->user()->hasRole('super_admin')
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
Route::get('/track/{trackingNumber}/status', [TrackController::class, 'status'])->name('track.status');
Route::get('/track/{trackingNumber}', [TrackController::class, 'show'])->name('track.show');
Route::post('/track/{trackingNumber}/upload', [CitizenDocumentUploadController::class, 'store'])
    ->middleware('throttle:12,1')
    ->name('track.citizen-upload');

/*
|--------------------------------------------------------------------------
| Citizen / Guest Routes (no auth required)
|--------------------------------------------------------------------------
*/

Route::prefix('citizen')->name('citizen.')->group(function () {
    Route::get('/', [CitizenController::class, 'index'])->name('dashboard');
    Route::get('/track', [CitizenController::class, 'track'])->name('track');
});

/*
|--------------------------------------------------------------------------
| Admin Routes (auth + admin role required)
|--------------------------------------------------------------------------
*/

// Super Admin only
Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

        // Department management
        Route::get('departments', [AdminDepartmentController::class, 'index'])->name('departments.index');
        Route::get('departments/create', [AdminDepartmentController::class, 'create'])->name('departments.create');
        Route::post('departments', [AdminDepartmentController::class, 'store'])->name('departments.store');
        Route::get('departments/{department}/edit', [AdminDepartmentController::class, 'edit'])->name('departments.edit');
        Route::put('departments/{department}', [AdminDepartmentController::class, 'update'])->name('departments.update');
        Route::delete('departments/{department}', [AdminDepartmentController::class, 'destroy'])->name('departments.destroy');

        // Audit log
        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
    });

// Super Admin + Department Admin: user management (controller enforces dept scoping)
Route::middleware(['auth', 'verified', 'role:super_admin,department_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/toggle-active', [AdminUserController::class, 'toggleActive'])->name('users.toggle-active');
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
    Route::patch('/documents/{trackingNumber}/complete', [DocumentWebController::class, 'complete'])->name('documents.complete');

    Route::middleware('role:super_admin,department_admin')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/analytics/data', [AnalyticsController::class, 'chartData'])->name('analytics.data');
    });

    Route::get('/history', [HistoryController::class, 'index'])->name('history');
    Route::get('/history/export', [HistoryController::class, 'export'])->name('history.export');

    Route::get('/movements', [MovementController::class, 'index'])->name('movements.index');

    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});

require __DIR__.'/auth.php';
