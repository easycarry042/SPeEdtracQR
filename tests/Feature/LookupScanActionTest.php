<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Look up page carries a "Scan QR" action in its title row, opening a
 * scanner that jumps straight to a request. It runs on the shared SpeedQr
 * helper, and the page itself is a fixed-height workspace.
 */
class LookupScanActionTest extends TestCase
{
    use RefreshDatabase;

    private function document(): Document
    {
        $department = Department::create(['name' => 'Records', 'code' => 'REC', 'is_active' => true]);

        return Document::create([
            'tracking_number' => 'SPD-'.now()->format('Ymd').'-K7M9Q2',
            'document_type' => 'Business Permit',
            'department_id' => $department->id,
            'citizen_name' => 'Maria Santos',
            'status' => 'in_progress',
        ]);
    }

    public function test_staff_see_the_scan_action_and_its_scanner(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('track.show', $this->document()->tracking_number))
            ->assertOk()
            ->assertSee('Scan QR')
            ->assertSee('lookupScanRegion', false)
            // The shared extractor, not a second set of code rules.
            ->assertSee('window.SpeedQr', false);
    }

    public function test_the_page_opts_into_the_fixed_height_shell(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('track.show', $this->document()->tracking_number))
            ->assertOk()
            // The lock is on <html>/<body> as well as the shell, so nothing
            // outside the shell can reintroduce a page scrollbar.
            ->assertSee('app-fixed-height', false)
            ->assertSee('main-fixed', false)
            ->assertDontSee('calc(100vh-9rem)', false);
    }
}
