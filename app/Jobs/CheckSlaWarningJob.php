<?php

namespace App\Jobs;

use App\Mail\SlaWarningMail;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class CheckSlaWarningJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $documentId,
        public int $departmentId
    ) {
    }

    public function handle(): void
    {
        $document = Document::with('currentDepartment')->find($this->documentId);
        if (! $document) {
            return;
        }

        // Only warn if still in the same department and not yet done
        if ((int) $document->current_department_id !== $this->departmentId) {
            return;
        }

        if ($document->status === 'completed') {
            return;
        }

        $department = $document->currentDepartment;
        if (! $department?->email) {
            return;
        }

        Mail::to($department->email)->send(new SlaWarningMail($document, $department));
    }
}