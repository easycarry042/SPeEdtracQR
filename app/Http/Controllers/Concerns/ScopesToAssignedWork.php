<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Document;
use App\Support\AssignmentScope;

trait ScopesToAssignedWork
{
    protected function scopeDocuments($query)
    {
        return AssignmentScope::applyDocumentScope($query);
    }

    protected function scopeCurrentDocuments($query)
    {
        return AssignmentScope::applyDocumentScope($query);
    }

    protected function authorizeDocumentAccess(Document $document): void
    {
        if (! AssignmentScope::userCanAccessDocument($document)) {
            abort(403, 'You do not have access to this document.');
        }
    }
}
