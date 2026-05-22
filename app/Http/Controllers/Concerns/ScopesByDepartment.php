<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Document;
use App\Support\DepartmentScope;

trait ScopesByDepartment
{
    protected function scopeDocuments($query)
    {
        return DepartmentScope::applyDocumentScope($query);
    }

    protected function scopeCurrentDocuments($query)
    {
        return DepartmentScope::applyCurrentDepartmentScope($query);
    }

    protected function scopeScans($query)
    {
        return DepartmentScope::applyScanScope($query);
    }

    protected function authorizeDocumentAccess(Document $document): void
    {
        if (! DepartmentScope::userCanAccessDocument($document)) {
            abort(403, 'This document is not assigned to your department.');
        }
    }
}
