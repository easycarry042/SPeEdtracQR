<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\StaffHighlight;
use App\Models\User;
use App\Support\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Staff profile (Direction 1b): identity rail + tabbed feed. Any authenticated
 * staff user may view any profile (peer view is read-only); only the owner sees
 * the highlight composer. Citizens are not users and cannot reach this page.
 */
class StaffProfileController extends Controller
{
    public function show(Request $request, User $user)
    {
        $profile = new StaffProfile($user);
        $tab = in_array($request->get('tab'), ['activity', 'assigned', 'completions'], true)
            ? $request->get('tab')
            : 'activity';

        $isOwn = (int) auth()->id() === (int) $user->id;

        return view('staff.profile', [
            'profileUser' => $user,
            'isOwn' => $isOwn,
            'roleLabel' => $user->getRoleNames()->first(),
            'tab' => $tab,
            'kpis' => $profile->kpis(),
            'lastActiveAt' => $profile->lastActiveAt(),
            'heatmap' => $profile->heatmap(),
            'feed' => $tab === 'activity' ? $profile->feed() : collect(),
            'assigned' => $tab === 'assigned' ? $profile->assigned() : collect(),
            'completions' => $tab === 'completions' ? $profile->completions() : collect(),
            // The composer document picker lists only the author's own documents.
            'ownDocuments' => $isOwn ? $this->ownDocuments($user) : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'highlight_type' => ['required', Rule::in(StaffHighlight::TYPES)],
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
        ]);

        // A highlight may only reference a document the author owns (assigned or created).
        if (! empty($validated['document_id'])) {
            $owns = Document::where('id', $validated['document_id'])
                ->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id))
                ->exists();

            if (! $owns) {
                throw ValidationException::withMessages([
                    'document_id' => 'You can only attach a document assigned to or created by you.',
                ]);
            }
        }

        $user->highlights()->create([
            'document_id' => $validated['document_id'] ?? null,
            'highlight_type' => $validated['highlight_type'],
            'body' => $validated['body'],
        ]);

        return redirect()
            ->route('staff.profile', ['user' => $user->id, 'tab' => 'activity'])
            ->with('status', 'Highlight posted.');
    }

    /** The author's own documents (assigned or created), for the composer picker. */
    private function ownDocuments(User $user)
    {
        return Document::where('assigned_to', $user->id)
            ->orWhere('created_by', $user->id)
            ->latest()
            ->limit(50)
            ->get(['id', 'tracking_number', 'document_type', 'status']);
    }
}
