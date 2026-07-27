<?php

namespace App\Http\Controllers;

use App\Mail\CitizenDocumentUploadMail;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentRequirement;
use App\Notifications\DocumentEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CitizenDocumentUploadController extends Controller
{
    /**
     * Citizen re-uploads a single supporting document that staff returned for
     * revision — nothing else needs to be resubmitted. Resets the item to
     * pending review and notifies the assigned staff member.
     */
    public function reupload(Request $request, string $trackingNumber, DocumentRequirement $requirement)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:10240'],
        ]);

        $document = Document::where('tracking_number', $trackingNumber)->with('assignedTo')->firstOrFail();
        abort_unless($requirement->document_id === $document->id, 404);

        if ($document->status === 'completed') {
            return back()->withErrors(['file' => 'This ticket is already completed. Uploads are no longer accepted.']);
        }

        if (! $requirement->needsRevision()) {
            return back()->withErrors(['file' => 'This document is not awaiting revision.']);
        }

        $path = $validated['file']->store('document-requirements', 'local');

        $requirement->update([
            'uploaded_file_path' => $path,
            'review_status' => DocumentRequirement::REVIEW_PENDING,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ]);

        activity()->performedOn($document)->log("Citizen re-uploaded \"{$requirement->label}\" for re-review");

        // Ping the responsible staff member: bell + email.
        $assignee = $document->assignedTo;
        if ($assignee) {
            $assignee->notify(DocumentEvent::revisionResubmitted($document, $requirement->label));
            if ($assignee->email) {
                Mail::to($assignee->email)->send(
                    new CitizenDocumentUploadMail($document, 1, "Revised document: {$requirement->label}")
                );
            }
        }

        return back()->with('upload_success', "Your revised \"{$requirement->label}\" was sent for re-review.");
    }

    public function store(Request $request, string $trackingNumber)
    {
        $validated = $request->validate([
            'attachments' => ['required', 'array', 'min:1', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:10240'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $document = Document::where('tracking_number', $trackingNumber)
            ->with('assignedTo')
            ->firstOrFail();

        if ($document->status === 'completed') {
            return back()->withErrors(['attachments' => 'This ticket is already completed. Uploads are no longer accepted.']);
        }

        $fileCount = $this->storeAttachments($document, $request);

        // Notify the staff member responsible for the document, if assigned.
        $assignee = $document->assignedTo;
        if ($assignee?->email) {
            Mail::to($assignee->email)->send(
                new CitizenDocumentUploadMail(
                    $document,
                    $fileCount,
                    $validated['note'] ?? null,
                )
            );
        }

        $where = $assignee?->name ?? 'the office';

        return back()->with('upload_success', "Your file(s) were sent to {$where}. They will be notified.");
    }

    private function storeAttachments(Document $document, Request $request): int
    {
        $files = collect($request->file('attachments', []))->filter();
        $sort = (int) $document->attachments()->max('sort_order') + 1;
        $count = 0;

        foreach ($files as $file) {
            $path = $file->store('document-attachments', 'local');

            DocumentAttachment::create([
                'document_id' => $document->id,
                'file_path' => $path,
                'uploaded_by' => null,
                'sort_order' => $sort++,
            ]);

            if ($count === 0) {
                $document->update(['attachment_path' => $path]);
            }

            $count++;
        }

        return $count;
    }
}
