<?php

namespace App\Jobs;

use App\Mail\SlaBreachMail;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class CheckSlaJob implements ShouldQueue
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

        Mail::to($department->email)->send(new SlaBreachMail($document, $department));
    }
}
