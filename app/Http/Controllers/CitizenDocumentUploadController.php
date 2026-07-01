<?php

namespace App\Http\Controllers;

use App\Mail\CitizenDocumentUploadMail;
use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CitizenDocumentUploadController extends Controller
{
    public function store(Request $request, string $trackingNumber)
    {
        $validated = $request->validate([
            'attachments' => 'required|array|min:1|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:10240',
            'note' => 'nullable|string|max:1000',
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
