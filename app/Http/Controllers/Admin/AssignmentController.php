<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentStatus;
use App\Events\DocumentCommentPosted;
use App\Events\DocumentStatusUpdated;
use App\Http\Controllers\Controller;
use App\Mail\AssignmentNotice;
use App\Models\Document;
use App\Models\User;
use App\Support\AssignmentScope;
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
        $query = AssignmentScope::applyDocumentScope($query);

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

    public function unclaimed(Request $request)
    {
        abort_unless(AssignmentScope::canViewUnclaimedQueue(auth()->user()), 403);

        $query = Document::with(['assignedTo', 'creator'])
            ->whereNull('assigned_to')
            ->latest();

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($term) {
                $q->where('tracking_number', 'like', $term)
                    ->orWhere('citizen_name', 'like', $term)
                    ->orWhere('document_type', 'like', $term);
            });
        }

        $documents = AssignmentScope::applyDocumentScope($query)->paginate(20)->withQueryString();

        return view('admin.assignments.unclaimed', compact('documents'));
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
            'accepted_at' => null,
        ]);

        DocumentStatusUpdated::dispatch($document->fresh(), auth()->user());

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

    public function accept(Document $document)
    {
        $user = auth()->user();
        abort_unless($user?->can('accept documents') || $user?->can('assign documents'), 403);
        abort_unless($document->assigned_to === null, 422, 'Document is already assigned.');
        abort_unless(AssignmentScope::canViewUnclaimedQueue($user), 403);

        $document->update([
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        if ($document->statusEnum() === DocumentStatus::Pending) {
            $document->applyStatus(DocumentStatus::InProgress);
            $document->save();
        }
        DocumentStatusUpdated::dispatch($document->fresh(), $user);

        activity()
            ->performedOn($document)
            ->causedBy($user)
            ->log("Accepted assignment by {$user->name}");

        $systemComment = $document->logSystemComment("Accepted assignment by {$user->name}");
        DocumentCommentPosted::dispatch($systemComment);

        return back()->with('status', "Accepted {$document->tracking_number}.");
    }

    /**
     * Staff eligible to be responsible for a document — those who can advance it.
     * Scoped to the admin's department unless they are org-wide.
     */
    private function assignableStaff()
    {
        return User::query()
            ->permission('advance documents')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
