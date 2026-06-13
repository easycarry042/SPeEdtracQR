<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Support\Ai\DocumentAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentAssistantController extends Controller
{
    /**
     * Answer a citizen's natural-language question about ONE document.
     *
     * Public + throttled. The assistant is grounded only in this document's
     * public-safe facts (see DocumentAssistant), so knowing the tracking number
     * grants no more than the tracking page already shows.
     */
    public function ask(Request $request, string $trackingNumber, DocumentAssistant $assistant): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:500'],
        ]);

        $document = Document::where('tracking_number', $trackingNumber)
            ->with(['scans.department', 'scans.user', 'currentDepartment', 'routeSteps.department'])
            ->firstOrFail();

        $result = $assistant->answer($document, $validated['question']);

        return response()->json([
            'answer' => $result['answer'],
            'source' => $result['source'],
        ]);
    }
}
