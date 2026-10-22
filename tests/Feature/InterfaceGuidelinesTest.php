<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Compliance with the Web Interface Guidelines
 * (https://vercel.com/design/guidelines).
 *
 * These are the rules that hold for EVERY page rather than for one screen, so
 * they are asserted once here instead of being re-checked per view. Each test
 * names the guideline it enforces.
 *
 * The point is regression: a reduced-motion reset or a skip link is invisible
 * in normal use, so nothing else in the suite would notice it being dropped.
 */
class InterfaceGuidelinesTest extends TestCase
{
    use RefreshDatabase;

    private function css(): string
    {
        return file_get_contents(resource_path('css/app.css'));
    }

    private function admin(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('super_admin');
    }

    // ── Animations ────────────────────────────────────────────────────────

    /** "Honor prefers-reduced-motion" — one blanket reset, not per-component. */
    public function test_reduced_motion_is_honoured_globally(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{\s*\*,/',
            $css,
            'There is no global prefers-reduced-motion reset — only per-component ones, which miss anything nobody remembered to cover.'
        );
    }

    /**
     * "Never transition: all" — it animates properties the browser cannot
     * composite and catches ones nobody intended to animate.
     */
    public function test_no_view_uses_transition_all(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $body = file_get_contents($file);
            if (preg_match('/transition-all|transition:\s*all/', $body)) {
                $offenders[] = str_replace(resource_path('views').'/', '', $file);
            }
        }

        $this->assertSame([], $offenders, 'These views animate every property instead of naming the ones that change: '.implode(', ', $offenders));
    }

    // ── Design ────────────────────────────────────────────────────────────

    /** "Set the appropriate color-scheme" — this system is light-only. */
    public function test_color_scheme_is_declared(): void
    {
        $this->assertStringContainsString('color-scheme: light', $this->css());
    }

    /** "Browser UI matches your background". */
    public function test_shells_declare_a_theme_colour(): void
    {
        foreach (['app', 'citizen', 'guest'] as $layout) {
            $this->assertStringContainsString(
                'name="theme-color"',
                file_get_contents(resource_path("views/layouts/{$layout}.blade.php")),
                "The {$layout} layout does not set a theme-color, so mobile browser chrome will not match the page."
            );
        }
    }

    // ── Forms ─────────────────────────────────────────────────────────────

    /**
     * "Mobile input size" — under 16px, iOS Safari zooms the page on focus and
     * leaves the viewport scaled. The compact desktop control is kept.
     */
    public function test_controls_reach_sixteen_pixels_on_small_screens(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/@media\s*\(max-width:\s*639px\)\s*\{[^}]*font-size:\s*16px/s',
            $css,
            'Form controls are still under 16px on phones, so iOS will zoom when one takes focus.'
        );
    }

    // ── Content / accessibility ───────────────────────────────────────────

    /** "Headings & skip link" — the first tab stop skips the chrome. */
    public function test_signed_in_pages_offer_a_skip_link_to_a_real_target(): void
    {
        $html = $this->actingAs($this->admin())->get(route('requests.index'))->assertOk()->getContent();

        $this->assertStringContainsString('href="#main"', $html, 'No skip link.');
        $this->assertStringContainsString('id="main"', $html, 'The skip link has no target to jump to.');
    }

    /** The public landing page has chrome to skip too. */
    public function test_the_landing_page_offers_a_skip_link_to_a_real_target(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('href="#main"', $html);
        $this->assertStringContainsString('id="main"', $html);
    }

    /**
     * "Accurate page titles" — every screen names itself, so a row of tabs and
     * the browser history are navigable.
     */
    public function test_pages_set_their_own_title(): void
    {
        $untitled = [];

        foreach ($this->bladeFiles() as $file) {
            $body = file_get_contents($file);
            if (! str_contains($body, '<x-app-layout')) {
                continue;
            }
            // `:title="$user->name"` names the screen as well as a literal does
            // — better, where the screen is about one record — so a bound
            // attribute counts too.
            if (! preg_match('/<x-app-layout[^>]*\s:?title="/', $body)) {
                $untitled[] = str_replace(resource_path('views').'/', '', $file);
            }
        }

        $this->assertSame([], $untitled, 'These pages fall back to the bare app name in the tab: '.implode(', ', $untitled));
    }

    /** Two different screens must not present the same title. */
    public function test_titles_distinguish_one_screen_from_another(): void
    {
        $admin = $this->admin();

        $titleOf = function (string $url) use ($admin): string {
            $html = $this->actingAs($admin)->get($url)->getContent();
            preg_match('/<title>(.*?)<\/title>/s', $html, $m);

            return trim($m[1] ?? '');
        };

        $requests = $titleOf(route('requests.index'));
        $users = $titleOf(route('admin.users.index'));

        $this->assertNotSame($requests, $users, 'Two different screens share one title.');
        $this->assertStringContainsString('Internal Requests', $requests);
        $this->assertStringContainsString('Users', $users);
    }

    /**
     * @return list<string>
     */
    private function bladeFiles(): array
    {
        $files = [];
        $dir = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($dir as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
