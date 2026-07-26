<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Document;
use App\Support\AssignmentScope;
use Illuminate\Database\Eloquent\Builder;

trait ScopesToAssignedWork
{
    protected function scopeDocuments(Builder $query): Builder
    {
        return AssignmentScope::applyDocumentScope($query);
    }

    protected function scopeCurrentDocuments(Builder $query): Builder
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
