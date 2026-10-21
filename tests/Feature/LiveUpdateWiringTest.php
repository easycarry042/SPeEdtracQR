<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Live updates are wired, not just configured.
 *
 * @vite ships app.js as a deferred <script type="module">, so it runs after
 * every inline <script> in a page body. A page that subscribed behind a plain
 * `if (window.Echo)` was therefore reading an undefined global: the branch was
 * always false, the channel was never joined, and the citizen tracker silently
 * fell back to 30-second polling forever. Nothing failed loudly, which is why
 * it survived — so the shape of the fix is pinned here.
 */
class LiveUpdateWiringTest extends TestCase
{
    use RefreshDatabase;

    /** Layouts that boot the bundle must define whenEcho() before it. */
    public function test_every_layout_that_loads_the_bundle_defines_when_echo(): void
    {
        $missing = [];

        foreach (glob(resource_path('views/layouts/*.blade.php')) as $layout) {
            $body = file_get_contents($layout);

            if (! str_contains($body, '@vite(')) {
                continue;
            }

            if (! str_contains($body, 'layouts.partials.echo-ready')) {
                $missing[] = basename($layout);

                continue;
            }

            // Order matters: a classic script after a deferred module still
            // runs first, but keeping the include above @vite documents intent
            // and survives anyone switching the bundle to a blocking script.
            $this->assertLessThan(
                strpos($body, '@vite('),
                strpos($body, 'layouts.partials.echo-ready'),
                basename($layout).' must include the shim before @vite'
            );
        }

        $this->assertSame([], $missing, 'Layouts loading the bundle without whenEcho(): '.implode(', ', $missing));
    }

    /**
     * No view may gate a subscription on a bare `window.Echo` truth test —
     * that is the exact branch that was always false.
     */
    public function test_no_view_subscribes_behind_a_bare_echo_guard(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // Comments explain the trap by quoting it, so strip prose before
            // looking for the real thing.
            $body = preg_replace(
                ['/\{\{--.*?--\}\}/s', '~^\s*//.*$~m', '~/\*.*?\*/~s'],
                '',
                file_get_contents($file->getPathname())
            );

            if (preg_match('/if\s*\(\s*!?\s*window\.Echo\s*[)&|]/', $body)) {
                $offenders[] = str_replace(resource_path('views').'/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'These views gate Echo on a global that is undefined when they run: '.implode(', ', $offenders));
    }

    /** The citizen tracker is the page the whole feature exists for. */
    public function test_the_citizen_tracker_subscribes_through_when_echo(): void
    {
        $document = Document::create([
            'tracking_number' => 'SPD-20260101-LIVE01',
            'document_type' => 'Business Permit',
            'status' => 'pending',
            'origin' => 'citizen',
        ]);

        $html = $this->get(route('track.show', $document->tracking_number))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('window.whenEcho', $html, 'the tracker must wait for Echo');
        $this->assertStringContainsString("documents.' + trackingNumber", $html, 'the tracker must join its own channel');
        $this->assertStringContainsString('echo:ready', $html, 'the shim must be on the page');
    }

    /** The staff conversation listens on the private channel the same way. */
    public function test_the_staff_document_page_subscribes_through_when_echo(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-20260101-LIVE02',
            'document_type' => 'Business Permit',
            'status' => 'pending',
            'origin' => 'citizen',
            'assigned_to' => $staff->id,
            'department_id' => $staff->department_id,
        ]);

        $html = $this->actingAs($staff)
            ->get(route('track.show', $document->tracking_number))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('window.whenEcho', $html);
        $this->assertStringContainsString(".staff'", $html, 'the staff page must join the private channel');
    }
}
