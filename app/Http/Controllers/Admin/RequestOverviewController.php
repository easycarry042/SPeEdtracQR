<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Support\Duration;
use App\Support\RequestReview;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The super admin's Requests page: every citizen request in the city, where
 * it stands, and who has it. Read-only by design — the office assigns, the
 * staff member advances — with a Notify for anything that needs a push.
 *
 * It is the place request links land for a super admin: the tracking page,
 * the command center's lists and the unrouted alert all open a request here
 * (?open=SPD-…), where they used to bounce back to the dashboard.
 */
class RequestOverviewController extends Controller
{
    public function index(Request $request): Factory|View
    {
        $documents = Document::query()
            ->citizenFacing()
            ->with(['attachments', 'department', 'assignedTo', 'requirements'])
            ->latest()
            ->get();

        $over = fn (Document $document): ?string => $this->overdueBy($document);

        $payload = RequestReview::collection($documents)->map(function (array $row) use ($documents, $over): array {
            $document = $documents->firstWhere('id', $row['id']);

            return $row + [
                'over_sla' => $over($document),
                // Only open work can be nudged.
                'nudge_url' => $document->statusEnum()->isTerminal() ? null : route('admin.nudge', $document),
            ];
        });

        return view('admin.requests.index', [
            'requests' => $payload,
            'openTracking' => strtoupper(trim((string) $request->query('open'))) ?: null,
        ]);
    }

    /** How far past its current stage's time an open request is, or null. */
    private function overdueBy(Document $document): ?string
    {
        $stage = $document->statusEnum();
        $sla = $stage->slaHours();
        $since = $document->status_changed_at ?? $document->updated_at ?? $document->created_at;

        if ($sla === null || $stage->isTerminal() || ! $since) {
            return null;
        }

        $hours = abs(now()->getTimestamp() - $since->getTimestamp()) / 3600 - $sla;

        return $hours > 0 ? Duration::hours($hours) : null;
    }
}
