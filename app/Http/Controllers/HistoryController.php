<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\ScopesToAssignedWork;
use App\Models\Document;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * History is the archive of finished work: it lists COMPLETED requests only.
 * Anything still moving belongs on a dashboard, not in history — mixing the two
 * made History a second, confusing worklist.
 */
class HistoryController extends Controller
{
    use ScopesToAssignedWork;

    public function index(Request $request): Factory|View
    {
        $query = $this->completedDocuments();

        $this->applyFilters($query, $request);

        $documents = $query->latest('completed_at')->latest('created_at')->paginate(15);
        $documentTypes = $this->completedDocuments()->distinct()->pluck('document_type');

        return view('history.index', ['documents' => $documents, 'documentTypes' => $documentTypes]);
    }

    /** Completed requests within the viewer's scope. */
    private function completedDocuments()
    {
        return $this->scopeDocuments(
            Document::query()
                ->with('assignedTo')
                ->where('status', DocumentStatus::Completed->value)
        );
    }

    public function export(Request $request)
    {
        $query = $this->completedDocuments();

        $this->applyFilters($query, $request);

        $documents = $query->orderBy('created_at', 'desc')->get();

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['Tracking Number', 'Document Type', 'Citizen Name', 'Status', 'Assignee', 'Created At']);
        foreach ($documents as $doc) {
            fputcsv($handle, [
                $doc->tracking_number,
                $doc->document_type,
                $doc->citizen_name ?? 'N/A',
                $doc->status,
                $doc->assignedTo->name ?? 'Unassigned',
                $doc->created_at,
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="documents_history.csv"');
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request): void {
                $q->where('tracking_number', 'like', '%'.$request->search.'%')
                    ->orWhere('citizen_name', 'like', '%'.$request->search.'%')
                    ->orWhere('document_type', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }
    }
}
