<?php

use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DepartmentController as AdminDepartmentController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CitizenController;
use App\Http\Controllers\CitizenDocumentUploadController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentAssistantController;
use App\Http\Controllers\DocumentStatusController;
use App\Http\Controllers\DocumentWebController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicTicketController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\StaffProfileController;
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

    return auth()->user()->can('manage system')
        ? redirect()->route('admin.dashboard')
        : redirect()->route('dashboard');
});

/*
|--------------------------------------------------------------------------
| Public Tracking Routes (no auth required)
|--------------------------------------------------------------------------
*/

// Citizen self-service ticket creation (no account). Throttled + honeypot as
// anti-abuse; uploads validated and stored on the private disk.
Route::get('/request', [PublicTicketController::class, 'create'])->name('public.request.create');
Route::post('/request', [PublicTicketController::class, 'store'])
    ->middleware('throttle:8,1')
    ->name('public.request.store');

Route::get('/track', [TrackController::class, 'index'])->name('track.index');
Route::get('/track-search', [TrackController::class, 'index'])->name('track.search');
// Rate-limited as defense-in-depth against tracking-number guessing
// (primary defense is the high-entropy tracking number). 60/min/IP is
// generous for legit use — the citizen page only polls status every 30s.
Route::get('/track/{trackingNumber}/status', [TrackController::class, 'status'])
    ->middleware('throttle:60,1')
    ->name('track.status');
Route::get('/track/{trackingNumber}', [TrackController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('track.show');
Route::post('/track/{trackingNumber}/upload', [CitizenDocumentUploadController::class, 'store'])
    ->middleware('throttle:12,1')
    ->name('track.citizen-upload');
// Self-hosted AI assistant — answers questions grounded in this document only.
Route::post('/track/{trackingNumber}/ask', [DocumentAssistantController::class, 'ask'])
    ->middleware('throttle:20,1')
    ->name('track.ask');

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

// Org-wide system administration (super_admin only via manage system permission)
Route::middleware(['auth', 'verified', 'permission:manage system'])
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

// Document assignment desk (admins assign the responsible staff member)
Route::middleware(['auth', 'verified', 'permission:assign documents'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::patch('assignments/{document}', [AssignmentController::class, 'assign'])->name('assignments.assign');
    });

// User management (controller enforces department scoping for dept admins)
Route::middleware(['auth', 'verified', 'permission:manage users'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/toggle-active', [AdminUserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::patch('users/{user}/archive', [AdminUserController::class, 'archive'])->name('users.archive');
        Route::patch('users/{user}/restore', [AdminUserController::class, 'restore'])->name('users.restore')->withTrashed();
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy')->withTrashed();
    });

/*
|--------------------------------------------------------------------------
| Staff / Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Staff profile (identity rail + activity feed). Viewable by any staff user.
    Route::get('/staff/{user}', [StaffProfileController::class, 'show'])->name('staff.profile');
    Route::post('/staff/highlights', [StaffProfileController::class, 'store'])->name('staff.highlights.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    Route::get('/scanner', fn () => redirect()->route('scan.index'))->name('scanner');

    Route::get('/documents/create', [DocumentWebController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentWebController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}/created', [DocumentWebController::class, 'created'])->name('documents.created');
    Route::get('/documents/{document}/edit', [DocumentWebController::class, 'edit'])->name('documents.edit');
    Route::put('/documents/{document}', [DocumentWebController::class, 'update'])->name('documents.update');
    Route::get('/documents/{document}/sticker', [DocumentWebController::class, 'printSticker'])->name('documents.sticker');
    Route::patch('/documents/{trackingNumber}/complete', [DocumentWebController::class, 'complete'])->name('documents.complete');
    Route::post('/documents/{document}/undo-scan', [ScanController::class, 'undoLast'])->name('documents.undo-scan');

    // Per-document staff collaboration feed (assignee or admin).
    Route::post('/documents/{document}/comments', [CommentController::class, 'store'])->name('documents.comments.store');

    // Manual status progression by the assigned staff member (or an admin).
    Route::patch('/documents/{document}/status/advance', [DocumentStatusController::class, 'advance'])->name('documents.status.advance');
    Route::patch('/documents/{document}/status/revert', [DocumentStatusController::class, 'revert'])->name('documents.status.revert');
    Route::patch('/documents/{document}/status', [DocumentStatusController::class, 'set'])->name('documents.status.set');

    Route::middleware('permission:view reports')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/analytics/data', [AnalyticsController::class, 'chartData'])->name('analytics.data');
    });

    Route::get('/history', [HistoryController::class, 'index'])->name('history');
    Route::get('/history/export', [HistoryController::class, 'export'])->name('history.export');

    Route::get('/movements', [MovementController::class, 'index'])->name('movements.index');

    // Private document attachments — access checked per-department in the controller.
    Route::post('/documents/{document}/attachments', [AttachmentController::class, 'store'])->name('documents.attachments.store');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});

require __DIR__.'/auth.php';
