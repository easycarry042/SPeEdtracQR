<?php

use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\AuditLogController;
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
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicTicketController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\StaffDashboardController;
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

    $user = auth()->user();

    // super_admin gets the org-wide analytics command center; supervisors get the
    // operational Requests dashboard (they cannot access admin.dashboard).
    if ($user->can('manage system')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->hasRole('Supervisor') || $user->hasRole('department_admin')) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('staff.profile', ['user' => $user->id]);
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
        Route::get('assignments/unclaimed', [AssignmentController::class, 'unclaimed'])->name('assignments.unclaimed');
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

    // Staff directory + quick search (any staff can view any profile).
    Route::get('/staff', [StaffProfileController::class, 'index'])->name('staff.index');
    Route::get('/staff/search', [StaffProfileController::class, 'search'])->name('staff.search');

    // Staff operational dashboard — requests assigned to the authenticated staff member.
    Route::get('/my-dashboard', [StaffDashboardController::class, 'index'])->name('staff.dashboard');

    // Supervisor approve = assign to staff (staff must accept on Requests page).
    Route::post('/documents/{document}/assign-approve', [ReviewController::class, 'assignApprove'])->name('documents.assign-approve');
    // Supervisor deny = reject the request (terminal).
    Route::post('/documents/{document}/deny', [ReviewController::class, 'deny'])->name('documents.deny');

    // Staff assignment triage on the Requests page.
    Route::post('/documents/{document}/assignment/accept', [ReviewController::class, 'acceptAssignment'])->name('documents.assignment.accept');
    Route::post('/documents/{document}/assignment/decline', [ReviewController::class, 'declineAssignment'])->name('documents.assignment.decline');
    Route::post('/documents/{document}/assignment/revision', [ReviewController::class, 'requestRevision'])->name('documents.assignment.revision');

    // Staff review lifecycle: open (→ In Review) and approve (→ Completed / History).
    Route::post('/documents/{document}/review/open', [ReviewController::class, 'open'])->name('documents.review.open');
    Route::patch('/documents/{document}/review/complete', [ReviewController::class, 'complete'])->name('documents.review.complete');

    // Staff profile (identity rail + activity feed). Viewable by any staff user.
    Route::get('/staff/{user}', [StaffProfileController::class, 'show'])->name('staff.profile');
    Route::post('/staff/highlights', [StaffProfileController::class, 'store'])->name('staff.highlights.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');

    Route::get('/documents/create', [DocumentWebController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentWebController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}/created', [DocumentWebController::class, 'created'])->name('documents.created');
    Route::get('/documents/{document}/edit', [DocumentWebController::class, 'edit'])->name('documents.edit');
    Route::put('/documents/{document}', [DocumentWebController::class, 'update'])->name('documents.update');
    Route::get('/documents/{document}/sticker', [DocumentWebController::class, 'printSticker'])->name('documents.sticker');
    Route::patch('/documents/{trackingNumber}/complete', [DocumentWebController::class, 'complete'])->name('documents.complete');

    // Per-document staff collaboration feed (assignee or admin).
    Route::post('/documents/{document}/comments', [CommentController::class, 'store'])->name('documents.comments.store');

    // Manual status progression by the assigned staff member (or an admin).
    Route::patch('/documents/{document}/status/advance', [DocumentStatusController::class, 'advance'])->name('documents.status.advance');
    Route::patch('/documents/{document}/status/revert', [DocumentStatusController::class, 'revert'])->name('documents.status.revert');
    Route::patch('/documents/{document}/hold', [DocumentStatusController::class, 'hold'])->name('documents.status.hold');
    Route::patch('/documents/{document}/unhold', [DocumentStatusController::class, 'unhold'])->name('documents.status.unhold');
    Route::patch('/documents/{document}/status', [DocumentStatusController::class, 'set'])->name('documents.status.set');

    Route::middleware('permission:view reports')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/analytics/data', [AnalyticsController::class, 'chartData'])->name('analytics.data');
    });

    Route::get('/history', [HistoryController::class, 'index'])->name('history');
    Route::get('/history/export', [HistoryController::class, 'export'])->name('history.export');

    Route::patch('/documents/{document}/accept', [AssignmentController::class, 'accept'])
        ->middleware('permission:accept documents|assign documents')
        ->name('documents.accept');

    // Private document attachments — access checked per-department in the controller.
    Route::post('/documents/{document}/attachments', [AttachmentController::class, 'store'])->name('documents.attachments.store');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
});

require __DIR__.'/auth.php';
