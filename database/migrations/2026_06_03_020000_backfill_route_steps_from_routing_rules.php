<?php

use App\Models\Document;
use App\Models\RoutingRule;
use Illuminate\Database\Migrations\Migration;

/**
 * route_steps is now the single source of truth for a document's routing
 * (the RoutingRule fallback was removed from the Document model). Backfill
 * per-document steps for any legacy document that only had global RoutingRules,
 * so its routing chain survives the change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Document::withTrashed()->with('routeSteps')->chunkById(200, function ($documents) {
            foreach ($documents as $document) {
                if ($document->routeSteps->isNotEmpty()) {
                    continue;
                }

                $rules = RoutingRule::where('document_type', $document->document_type)
                    ->orderBy('step_order')
                    ->get();
                if ($rules->isEmpty()) {
                    continue;
                }

                $chain = collect([$rules->first()->from_department_id]);
                foreach ($rules as $rule) {
                    if ($rule->to_department_id) {
                        $chain->push($rule->to_department_id);
                    }
                }

                $ids = $chain->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
                if (! empty($ids)) {
                    $document->syncRouteSteps($ids);
                }
            }
        });
    }

    public function down(): void
    {
        // No safe reverse — backfilled steps are indistinguishable from real ones.
    }
};
