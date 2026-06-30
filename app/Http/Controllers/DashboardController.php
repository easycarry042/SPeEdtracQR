<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\ScopesByDepartment;
use App\Models\Document;
use App\Support\AssignmentScope;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ScopesByDepartment;

    public function index()
    {
        $user = auth()->user();
        $isOrgWide = AssignmentScope::canViewAll($user);
        if (! $isOrgWide) {
            return redirect()->route('staff.profile', ['user' => $user->id]);
        }
        $dept = null;

        $totalRequests = $this->scopeDocuments(Document::query())->count();
        $pendingRequest = $this->scopeDocuments(Document::query())->whereIn('status', DocumentStatus::activeValues())->count();
        $completed = $this->scopeDocuments(Document::query())->where('status', DocumentStatus::Completed->value)->count();

        $recentActivity = $this->scopeDocuments(Document::query()->latest('created_at'))->take(5)->get();

        $statusSummary = $this->scopeDocuments(Document::query())
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // At-risk = documents sitting in a non-terminal stage past 75% of that
        // stage's SLA budget (anchored to status_changed_at — the manual model).
        $activeQuery = $this->scopeDocuments(
            Document::with('assignedTo')
                ->whereIn('status', DocumentStatus::activeValues())
        );

        $atRiskDocuments = $activeQuery->get()->map(function ($doc) {
            $sla = $doc->statusEnum()->slaHours();
            $anchor = $doc->status_changed_at ?? $doc->updated_at ?? $doc->created_at;
            if (! $sla || ! $anchor) {
                return null;
            }
            $elapsed = $anchor->diffInHours(now());
            $doc->sla_elapsed_hours = $elapsed;
            $doc->sla_remaining_hours = max(0, $sla - $elapsed);
            $doc->sla_overdue = $elapsed > $sla;
            $doc->sla_ratio = $elapsed / $sla;

            return $doc;
        })->filter(fn ($doc) => $doc && $doc->sla_ratio >= 0.75)
            ->sortBy('sla_remaining_hours')
            ->values();

        $atRiskCount = $atRiskDocuments->count();

        // ── Routing slip: the single most at-risk active document ─────────────
        $slip = $this->buildRoutingSlip();

        return view('dashboard', compact(
            'totalRequests',
            'pendingRequest',
            'completed',
            'recentActivity',
            'statusSummary',
            'atRiskDocuments',
            'atRiskCount',
            'dept',
            'isOrgWide',
            'slip'
        ));
    }

    /**
     * Pick the single most at-risk in-transit document and shape it for the
     * dashboard routing slip (<x-routing-slip>). Largest overdue wins; if none
     * are overdue, the one closest to breaching its current department's SLA.
     *
     * @return array{type: string, citizen: string, code: string, overdue: bool, status_text: string, stages: array<int, string>, current: int, public_url: string}|null
     */
    private function buildRoutingSlip(): ?array
    {
        $doc = $this->scopeDocuments(
            Document::whereIn('status', DocumentStatus::activeValues())
        )->get()
            ->map(function ($doc) {
                $sla = $doc->statusEnum()->slaHours();
                $anchor = $doc->status_changed_at ?? $doc->updated_at ?? $doc->created_at;

                // Signed hours past the stage SLA: positive = overdue, negative = time left.
                $doc->slipHoursOver = ($sla && $anchor)
                    ? $anchor->diffInHours(now()) - $sla
                    : null;

                return $doc;
            })
            ->filter(fn ($doc) => $doc->slipHoursOver !== null)
            ->sortByDesc('slipHoursOver')
            ->first();

        if (! $doc) {
            return null;
        }

        $stages = array_map(fn (DocumentStatus $s) => $s->label(), DocumentStatus::flow());
        $current = $doc->statusEnum()->position() ?: 1;

        $over = (int) round($doc->slipHoursOver);
        $overdue = $over > 0;

        return [
            'type' => $doc->document_type,
            'citizen' => $doc->citizen_name ?? '—',
            'code' => $doc->tracking_number,
            'overdue' => $overdue,
            'status_text' => $overdue ? '+'.$over.'h overdue' : '~'.abs($over).'h left',
            'stages' => $stages,
            'current' => $current,
            'public_url' => route('track.show', $doc->tracking_number),
        ];
    }
}
