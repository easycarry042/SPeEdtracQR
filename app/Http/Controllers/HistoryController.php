<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\ScopesByDepartment;
use App\Models\Document;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    use ScopesByDepartment;

    public function index(Request $request)
    {
        $query = $this->scopeDocuments(Document::query()->with('assignedTo'));

        $this->applyFilters($query, $request);

        $documents = $query->latest('created_at')->paginate(15);
        $documentTypes = $this->scopeDocuments(Document::query())->distinct()->pluck('document_type');
        $statuses = DocumentStatus::values();

        return view('history.index', compact('documents', 'documentTypes', 'statuses'));
    }

    public function export(Request $request)
    {
        $query = $this->scopeDocuments(Document::query()->with('assignedTo'));

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
            $query->where(function ($q) use ($request) {
                $q->where('tracking_number', 'like', '%'.$request->search.'%')
                    ->orWhere('citizen_name', 'like', '%'.$request->search.'%')
                    ->orWhere('document_type', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
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
