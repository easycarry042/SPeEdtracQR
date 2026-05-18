<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

class OverdueAlertMail extends Mailable
{
    use Queueable;

    public $document;

    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    public function build()
    {
        return $this->subject('Document Overdue Alert - SPeED TraQR')
                    ->view('emails.overdue');
    }
}