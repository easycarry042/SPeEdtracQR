<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Ai\HelpAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpAssistantController extends Controller
{
    /**
     * Answer a procedural question from the citizen landing page.
     *
     * Public + throttled. The assistant is grounded only in the published
     * service catalogue and fixed process explanations — it holds no document
     * context, so it cannot reveal anything about an individual request.
     */
    public function ask(Request $request, HelpAssistant $assistant): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:500'],
        ]);

        $result = $assistant->answer($validated['question']);

        return response()->json([
            'answer' => $result['answer'],
            'source' => $result['source'],
        ]);
    }
}
