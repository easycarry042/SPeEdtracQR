<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Mail\TicketSubmitted;
use App\Models\Booking;
use App\Models\Document;
use App\Models\RequestType;
use App\Notifications\DocumentEvent;
use App\Services\QrCodeService;
use App\Support\DocumentFormOptions;
use App\Support\UploadRules;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Phase 2 — citizen self-service ticket creation (no account).
 *
 * A submitted request becomes a normal documents row with source='online'. All
 * input is treated as untrusted: honeypot + route throttle, validated and
 * size/type-restricted uploads stored on the PRIVATE disk, RA 10173 consent.
 */
class PublicTicketController extends Controller
{
    public function __construct(private QrCodeService $qrCodeService) {}

    public function create(): Factory|View
    {
        return view('public.request', [
            'categories' => DocumentFormOptions::categoryOptions(),
            // Both kinds: document types carry requirements, booking types carry
            // a resource + date/time. The form branches on kind client-side.
            'requestTypes' => RequestType::query()
                ->where('is_active', true)
                ->with(['requirements', 'resource'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        // Honeypot: bots fill hidden fields. Silently bounce without creating.
        if (filled($request->input('website'))) {
            return to_route('public.request.create')
                ->with('status', 'Your request has been received.');
        }

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'citizen_name' => ['required', 'string', 'max:255'],
            'citizen_email' => ['required', 'email', 'max:255'],
            'citizen_contact' => ['nullable', 'string', 'max:255'],
            // Optional per-requirement uploads, keyed by request_type_requirement id.
            'requirements' => ['nullable', 'array'],
            'requirements.*' => UploadRules::rules(),
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'You must agree to the data privacy notice to submit a request.',
            'requirements.*.mimes' => 'Each requirement file must be an image (JPG/PNG), a PDF, or a Word document (DOCX).',
        ]);

        // Resolve the selected type up front so reservation requests can be
        // schedule-validated (and facilities conflict-checked) BEFORE anything is
        // created.
        $type = RequestType::where('name', $validated['document_type'])
            ->with(['requirements', 'resource'])
            ->first();

        $isFacility = $type && $type->kind === RequestType::KIND_BOOKING;
        $isEquipment = $type && $type->kind === RequestType::KIND_EQUIPMENT;
        $isService = $type && $type->kind === RequestType::KIND_SERVICE;

        // Resource reservation (facility/equipment) built by the branches below.
        $reservation = null;
        // Quantity + due date live on the document itself (equipment: how many
        // units; service: how many to produce, by needed_by). Null otherwise.
        $quantity = null;
        $neededBy = null;

        if ($isFacility) {
            // A facility is reserved for a specific window on a single day.
            $schedule = $request->validate([
                'booking_date' => ['required', 'date', 'after_or_equal:today'],
                'start_time' => ['required', 'date_format:H:i'],
                'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            ], [
                'booking_date.after_or_equal' => 'Please choose today or a future date.',
                'end_time.after' => 'The end time must be after the start time.',
            ]);

            $starts = Carbon::parse($schedule['booking_date'].' '.$schedule['start_time']);
            $ends = Carbon::parse($schedule['booking_date'].' '.$schedule['end_time']);

            if ($starts->isPast()) {
                return back()->withInput()->withErrors([
                    'start_time' => 'That start time has already passed. Please choose a later time.',
                ]);
            }

            if ($type->resource && $type->resource->conflicts($starts, $ends)->isNotEmpty()) {
                return back()->withInput()->withErrors([
                    'booking_date' => "{$type->resource->name} is already booked during that time. Please choose another slot.",
                ]);
            }

            $reservation = ['starts_at' => $starts, 'ends_at' => $ends];
        } elseif ($isEquipment) {
            // Equipment is borrowed in a quantity from a pickup date to a return
            // date. Items are shared stock, so there is no exclusive conflict —
            // staff confirm availability when approving.
            $schedule = $request->validate([
                'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
                'needed_date' => ['required', 'date', 'after_or_equal:today'],
                'return_date' => ['required', 'date', 'after_or_equal:needed_date'],
            ], [
                'needed_date.after_or_equal' => 'Please choose today or a future date.',
                'return_date.after_or_equal' => 'The return date cannot be before the pickup date.',
            ]);

            $quantity = (int) $schedule['quantity'];
            $neededBy = Carbon::parse($schedule['needed_date'])->toDateString();
            $reservation = [
                'starts_at' => Carbon::parse($schedule['needed_date'])->setTime(8, 0),
                'ends_at' => Carbon::parse($schedule['return_date'])->setTime(17, 0),
            ];
        } elseif ($isService) {
            // Service/production request (e.g. lei making): make a quantity by a
            // due date. No resource is reserved; it runs the normal workflow.
            $schedule = $request->validate([
                'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
                'needed_by' => ['required', 'date', 'after_or_equal:today'],
            ], [
                'needed_by.after_or_equal' => 'Please choose today or a future date.',
            ]);

            $quantity = (int) $schedule['quantity'];
            $neededBy = Carbon::parse($schedule['needed_by'])->toDateString();
        }

        $trackingNumber = $this->qrCodeService->generateTrackingNumber();

        $document = Document::create([
            'tracking_number' => $trackingNumber,
            'document_type' => $validated['document_type'],
            // Auto-route to the request type's handling department (its Supervisor
            // triages and assigns a staff member). Null if the type has no dept.
            'department_id' => $type?->department_id,
            'citizen_name' => $validated['citizen_name'],
            'citizen_email' => $validated['citizen_email'],
            'citizen_contact' => $validated['citizen_contact'] ?? null,
            'purpose' => $validated['purpose'] ?? null,
            'description' => $validated['description'] ?? null,
            'quantity' => $quantity,
            'needed_by' => $neededBy,
            'status' => DocumentStatus::Pending->value,
            'status_changed_at' => now(),
            'source' => 'online',
            // Routing is automatic, never a choice on the form: anything filed
            // through the public form is an EXTERNAL (citizen) request and enters
            // the citizen flow; dept-to-dept requests are created only through the
            // internal wizard, which stamps ORIGIN_INTERNAL. Set explicitly rather
            // than relying on the column default so the flow is visible here.
            'origin' => Document::ORIGIN_EXTERNAL,
            'created_by' => null, // submitted by a citizen, not a staff user
        ]);

        $trackingUrl = url('/track/'.$trackingNumber);
        $qrResult = $this->qrCodeService->generateAndStore($trackingNumber, $trackingUrl);
        if ($qrResult['success']) {
            $document->update(['qr_code_path' => $qrResult['relative_path']]);
        }

        if ($reservation) {
            // Facility/equipment request: create the pending reservation (staff
            // approve/reschedule on the calendar; approval re-checks conflicts).
            $document->booking()->create([
                'resource_id' => $type->resource_id,
                'starts_at' => $reservation['starts_at'],
                'ends_at' => $reservation['ends_at'],
                'status' => Booking::STATUS_PENDING,
            ]);
        }

        if ($type) {
            // Snapshot the requirement checklist for EVERY kind — a facility or
            // equipment request can require a Letter of Request just like a
            // document request lists its supporting papers. Stores any optional
            // citizen upload per requirement (private disk).
            $uploads = $request->file('requirements', []);
            foreach ($type->requirements as $requirement) {
                $file = $uploads[$requirement->id] ?? null;
                $path = ($file && $file->isValid())
                    ? $file->store('document-requirements', 'local')
                    : null;

                $document->requirements()->create([
                    'request_type_requirement_id' => $requirement->id,
                    'label' => $requirement->label,
                    'is_mandatory' => $requirement->is_mandatory,
                    'uploaded_file_path' => $path,
                ]);
            }
        }

        activity()
            ->performedOn($document)
            ->log('Citizen submitted online request');

        // Header-bell ping: the handling department's Supervisors (+ super admins)
        // have a new request to triage and assign. Falls back to all triagers
        // when the request type has no department.
        Notification::send(
            DocumentEvent::departmentTriagers($document->department_id),
            DocumentEvent::newTicket($document),
        );

        if ($document->citizen_email) {
            Mail::to($document->citizen_email)->send(new TicketSubmitted($document));
            activity()->performedOn($document)->log('Emailed TicketSubmitted to citizen');
        }

        // Show the QR + tracking number first (do NOT jump straight to the
        // tracker) so the citizen can save the QR before navigating away.
        return view('public.request-submitted', [
            'document' => $document->fresh(),
        ]);
    }
}
