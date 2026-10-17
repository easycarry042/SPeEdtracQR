<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSlipTest extends TestCase
{
    use RefreshDatabase;

    public function test_slip_page_renders_with_the_tracking_number(): void
    {
        $document = Document::create([
            'tracking_number' => 'SPD-20260728-ABC234',
            'document_type' => 'Business Permit',
            'citizen_name' => 'Juan Dela Cruz',
            'status' => 'pending',
        ]);

        $this->get(route('track.slip', $document->tracking_number))
            ->assertOk()
            ->assertSee($document->tracking_number)
            ->assertSee('Business Permit')
            ->assertSee('Print / Save as PDF');
    }

    public function test_unknown_tracking_number_returns_404(): void
    {
        $this->get(route('track.slip', 'SPD-00000000-NOPE99'))->assertNotFound();
    }

    public function test_internal_prefix_is_distinct_from_external(): void
    {
        $service = app(QrCodeService::class);

        $this->assertStringStartsWith('SPD-', $service->generateTrackingNumber());
        $this->assertStringStartsWith('INT-', $service->generateTrackingNumber('INT'));
    }
}
