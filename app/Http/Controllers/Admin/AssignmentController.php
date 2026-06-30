<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentStatus;
use App\Events\DocumentCommentPosted;
use App\Http\Controllers\Controller;
use App\Mail\AssignmentNotice;
use App\Models\Document;
use App\Models\User;
use App\Support\DepartmentScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Admin assignment desk: lists documents and lets an admin assign the staff
 * member responsible for advancing each file through its status stages.
 */
class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Document::with(['assignedTo', 'creator'])->latest();

        // Department admins see their department's documents; super admins see all.
        $query = DepartmentScope::applyDocumentScope($query);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($term) {
                $q->where('tracking_number', 'like', $term)
                    ->orWhere('citizen_name', 'like', $term)
                    ->orWhere('document_type', 'like', $term);
            });
        }

        $documents = $query->paginate(20)->withQueryString();
        $staff = $this->assignableStaff();
        $statuses = DocumentStatus::cases();

        return view('admin.assignments.index', compact('documents', 'staff', 'statuses'));
    }

    public function assign(Request $request, Document $document)
    {
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $assignedTo = $validated['assigned_to'] ?? null;

        $document->update([
            'assigned_to' => $assignedTo,
            'assigned_by' => $assignedTo ? auth()->id() : null,
            'assigned_at' => $assignedTo ? now() : null,
        ]);

        $assignee = $assignedTo ? User::find($assignedTo) : null;
        $name = $assignee?->name ?? ($assignedTo ? 'a staff member' : null);

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->log($assignedTo ? "Assigned to {$name}" : 'Cleared assignment');

        // Mirror into the unified per-document feed (staff timeline).
        $systemComment = $document->logSystemComment($assignedTo ? "Assigned to {$name}" : 'Assignment cleared');
        DocumentCommentPosted::dispatch($systemComment);

        // Notify the newly assigned staff member by email (queued, configurable).
        if ($assignee && $assignee->email && config('tracking.notify_staff_on_assignment', true)) {
            Mail::to($assignee->email)->send(new AssignmentNotice($document->fresh(), $assignee));
            activity()->performedOn($document)->causedBy(auth()->user())->log("Emailed AssignmentNotice to {$name}");
        }

        // Assignment is purely manual (admin picks the staff member); it does NOT
        // change the document's status. Status only moves when the assigned staff
        // advance it via the status controls (DocumentStatusController).
        return back()->with('status', $assignedTo
            ? "Assigned {$document->tracking_number} to {$name}."
            : "Cleared assignment for {$document->tracking_number}.");
    }

    /**
     * Staff eligible to be responsible for a document — those who can advance it.
     * Scoped to the admin's department unless they are org-wide.
     */
    private function assignableStaff()
    {
        return User::query()
            ->permission('advance documents')
            ->when(DepartmentScope::departmentId(), fn ($q, $deptId) => $q->where('department_id', $deptId))
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);
    }
}
