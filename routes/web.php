<?php

use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\RequestTypeController;
use App\Http\Controllers\Admin\RouteTemplateController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CitizenController;
use App\Http\Controllers\CitizenDocumentUploadController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentAssistantController;
use App\Http\Controllers\DocumentCustodyController;
use App\Http\Controllers\DocumentReleaseController;
use App\Http\Controllers\DocumentRequirementController;
use App\Http\Controllers\DocumentStatusController;
use App\Http\Controllers\DocumentWebController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\InternalRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicTicketController;
use App\Http\Controllers\RequestStepController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\StaffDashboardController;
use App\Http\Controllers\StaffProfileController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckResultsController;

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

    // Landing mirrors the post-login redirect: super_admin → command center,
    // supervisors → Dashboard, intake-only → Look up hub, staff → Requests.
    if ($user->can('manage system')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->hasRole('Supervisor')) {
        return redirect()->route('dashboard');
    }

    if ($user->can('scan documents') && ! $user->can('create documents')) {
        return redirect()->route('track.index', ['find' => 1]);
    }

    return redirect()->route('staff.dashboard');
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

// Public authenticity check — the signed QR on an issued document lands here.
Route::get('/verify/{trackingNumber}', [VerificationController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('verify.show');

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

        // Departments (office directory for internal request routing)
        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::get('departments/create', [DepartmentController::class, 'create'])->name('departments.create');
        Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::patch('departments/{department}/toggle-active', [DepartmentController::class, 'toggleActive'])->name('departments.toggle-active');

        // Route templates (endorsement chains prefilled onto internal requests)
        Route::get('route-templates', [RouteTemplateController::class, 'index'])->name('route-templates.index');
        Route::get('route-templates/create', [RouteTemplateController::class, 'create'])->name('route-templates.create');
        Route::post('route-templates', [RouteTemplateController::class, 'store'])->name('route-templates.store');
        Route::get('route-templates/{routeTemplate}/edit', [RouteTemplateController::class, 'edit'])->name('route-templates.edit');
        Route::put('route-templates/{routeTemplate}', [RouteTemplateController::class, 'update'])->name('route-templates.update');
        Route::patch('route-templates/{routeTemplate}/toggle-active', [RouteTemplateController::class, 'toggleActive'])->name('route-templates.toggle-active');
        Route::delete('route-templates/{routeTemplate}', [RouteTemplateController::class, 'destroy'])->name('route-templates.destroy');

        // Request types + their requirement checklists (public request catalog).
        Route::get('request-types', [RequestTypeController::class, 'index'])->name('request-types.index');
        Route::get('request-types/create', [RequestTypeController::class, 'create'])->name('request-types.create');
        Route::post('request-types', [RequestTypeController::class, 'store'])->name('request-types.store');
        Route::get('request-types/{requestType}/edit', [RequestTypeController::class, 'edit'])->name('request-types.edit');
        Route::put('request-types/{requestType}', [RequestTypeController::class, 'update'])->name('request-types.update');
        Route::patch('request-types/{requestType}/toggle-active', [RequestTypeController::class, 'toggleActive'])->name('request-types.toggle-active');
        Route::delete('request-types/{requestType}', [RequestTypeController::class, 'destroy'])->name('request-types.destroy');
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

// Resource booking calendar (covered court, plaza, sound system, …).
Route::middleware(['auth', 'verified', 'permission:assign documents'])
    ->group(function () {
        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::patch('bookings/{booking}/approve', [BookingController::class, 'approve'])->name('bookings.approve');
        Route::patch('bookings/{booking}/reschedule', [BookingController::class, 'reschedule'])->name('bookings.reschedule');
        Route::patch('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    });

// System health dashboard (DB, disk, scheduler, prod-posture checks). Restricted
// to system admins — it exposes operational state, not for public/uptime pings.
Route::get('health', HealthCheckResultsController::class)
    ->middleware(['auth', 'verified', 'permission:manage system'])
    ->name('health');

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
        Route::post('users/bulk', [AdminUserController::class, 'bulk'])->name('users.bulk');
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

    // Internal dept-to-dept requests (supervisor wizard: OCR upload → route → QR).
    // The inbox authorizes in-controller: dept supervisors AND org-wide admins enter.
    Route::get('/requests', [InternalRequestController::class, 'index'])->name('requests.index');
    Route::middleware('permission:create internal requests')->group(function () {
        Route::get('/requests/create', [InternalRequestController::class, 'create'])->name('requests.create');
        Route::post('/requests', [InternalRequestController::class, 'store'])->name('requests.store');
    });
    Route::get('/requests/{document}/created', [InternalRequestController::class, 'created'])->name('requests.created');
    Route::get('/requests/{document}', [InternalRequestController::class, 'show'])->name('requests.show');

    // Hop actions on the endorsement chain (current-department supervisors only;
    // each action re-confirms the password, approval affixes the e-signature).
    Route::middleware('permission:act on internal requests')->group(function () {
        Route::post('/requests/{document}/steps/approve', [RequestStepController::class, 'approve'])->name('requests.steps.approve');
        Route::post('/requests/{document}/steps/deny', [RequestStepController::class, 'deny'])->name('requests.steps.deny');
        Route::post('/requests/{document}/steps/return', [RequestStepController::class, 'returnToRequester'])->name('requests.steps.return');
    });
    Route::get('/request-steps/{requestStep}/signature', [RequestStepController::class, 'signature'])->name('requests.steps.signature');

    // Registered e-signature (drawn once on the profile page).
    Route::post('/profile/signature', [SignatureController::class, 'store'])->name('profile.signature.store');
    Route::get('/profile/signature', [SignatureController::class, 'show'])->name('profile.signature.show');
    Route::delete('/profile/signature', [SignatureController::class, 'destroy'])->name('profile.signature.destroy');

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

    // Supporting-requirement verification (staff confirm they've seen originals).
    Route::post('/documents/{document}/requirements/{requirement}/verify', [DocumentRequirementController::class, 'toggle'])->name('documents.requirements.toggle');
    Route::get('/documents/{document}/requirements/{requirement}/file', [DocumentRequirementController::class, 'file'])->name('documents.requirements.file');

    // Staff profile (identity rail + activity feed). Viewable by any staff user.
    Route::get('/staff/{user}', [StaffProfileController::class, 'show'])->name('staff.profile');
    Route::post('/staff/highlights', [StaffProfileController::class, 'store'])->name('staff.highlights.store');

    // Header bell: open a notification (mark read + follow) / clear all.
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

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

    // Physical custody trail — "the folder is now with me" (scan or click).
    Route::post('/documents/{document}/custody', [DocumentCustodyController::class, 'store'])->name('documents.custody.store');

    // QR-gated release: citizen presents their QR, staff mark the hand-over.
    Route::patch('/documents/{document}/release', [DocumentReleaseController::class, 'store'])->name('documents.release');

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
