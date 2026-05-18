<?php

namespace App\Jobs;

use App\Models\Document;
use App\Mail\OverdueAlertMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOverdueAlert implements ShouldQueue
{
    public $document;

    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    public function handle()
    {
        $deptHeadEmail = $this->document->currentDepartment->email;
        if ($deptHeadEmail) {
            Mail::to($deptHeadEmail)->send(new OverdueAlertMail($this->document));
        }
    }
}