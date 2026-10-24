<?php

namespace App\View\Components;

use App\Models\Document;
use Illuminate\View\Component;
use Illuminate\View\View;

class CitizenLayout extends Component
{
    /**
     * @param  Document|null  $speedyDocument  the request a page is about (the
     *                                         tracking page, the receipt), so
     *                                         Speedy opens already talking about it
     */
    public function __construct(public ?Document $speedyDocument = null) {}

    public function render(): View
    {
        return view('layouts.citizen');
    }
}
