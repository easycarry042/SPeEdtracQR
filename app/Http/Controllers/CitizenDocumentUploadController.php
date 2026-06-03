<?php

namespace App\Http\Controllers;

use App\Mail\CitizenDocumentUploadMail;
use App\Models\Department;
use App\Models\DepartmentNotification;
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
            'attachments.*' => 'image|max:10240',
            'note' => 'nullable|string|max:1000',
        ]);

        $document = Document::where('tracking_number', $trackingNumber)
            ->with(['currentDepartment', 'routeSteps.department'])
            ->firstOrFail();

        if ($document->status === 'completed') {
            return back()->withErrors(['attachments' => 'This ticket is already completed. Uploads are no longer accepted.']);
        }

        $department = $this->resolveNotifyDepartment($document);
        if (! $department) {
            return back()->withErrors(['attachments' => 'This ticket is not assigned to a department yet. Please try again later.']);
        }

        $fileCount = $this->storeAttachments($document, $request, $department->id);

        $message = "Citizen uploaded {$fileCount} file(s) for {$document->tracking_number}";

        DepartmentNotification::create([
            'department_id' => $department->id,
            'document_id' => $document->id,
            'type' => 'citizen_upload',
            'message' => $message,
            'file_count' => $fileCount,
        ]);

        if ($department->email) {
            Mail::to($department->email)->send(
                new CitizenDocumentUploadMail(
                    $document,
                    $department,
                    $fileCount,
                    $validated['note'] ?? null
                )
            );
        }

        return back()->with('upload_success', "Your file(s) were sent to {$department->name}. They will be notified.");
    }

    private function resolveNotifyDepartment(Document $document): ?Department
    {
        if ($document->current_department_id && $document->currentDepartment) {
            return $document->currentDepartment;
        }

        $firstStep = $document->routeSteps->sortBy('step_order')->first();

        return $firstStep?->department;
    }

    private function storeAttachments(Document $document, Request $request, int $departmentId): int
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
                'department_id' => $departmentId,
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
