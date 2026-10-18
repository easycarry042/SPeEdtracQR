<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Support\CitizenReceipt;
use App\Support\CitizenView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every page a citizen moves through wears the landing page's look: the same
 * header, the same intro, the same cards and buttons ("Citizen surface set" in
 * resources/css/app.css). They used to be three dialects — the landing page's
 * utilities, the request form's frosted glass, and the staff registry panels on
 * the tracking page — so moving from one page to the next felt like leaving
 * the site. Each page is held to the shared set here, by name.
 */
class CitizenPageParityTest extends TestCase
{
    use RefreshDatabase;

    private function document(): Document
    {
        return Document::create([
            'tracking_number' => 'SPD-20261017-PRT234',
            'document_type' => 'Business Permit',
            'citizen_name' => 'Juan Dela Cruz',
            'citizen_email' => 'juan@example.com',
            'status' => 'pending',
        ]);
    }

    /**
     * Rendered HTML of every citizen page, keyed by name.
     *
     * @return array<string, string>
     */
    private function pages(): array
    {
        $document = $this->document();

        $pages = [
            'landing page' => $this->get(CitizenView::homeUrl())->assertOk()->getContent(),
            'request form' => $this->get(route('public.request.create'))->assertOk()->getContent(),
            'track lookup' => $this->get(route('citizen.track'))->assertOk()->getContent(),
            'tracking result' => $this->get($document->citizenTrackingUrl())->assertOk()->getContent(),
            'privacy notice' => $this->get(route('privacy'))->assertOk()->getContent(),
        ];

        $pages['receipt'] = $this->withSession([CitizenReceipt::SESSION_KEY => [
            'tracking_number' => $document->tracking_number,
            'expires_at' => now()->addMinutes(CitizenReceipt::LIFETIME_MINUTES)->getTimestamp(),
        ]])->get(route('public.request.submitted'))->assertOk()->getContent();

        return $pages;
    }

    /** The rendered <header> element alone. */
    private function header(string $content): string
    {
        $start = (int) strpos($content, '<header');

        return substr($content, $start, (int) strpos($content, '</header>', $start) - $start);
    }

    public function test_every_page_wears_the_same_header(): void
    {
        foreach ($this->pages() as $name => $content) {
            $header = $this->header($content);

            $this->assertStringContainsString('alt="City of San Pedro seal"', $header, "{$name}: no City seal in the header");
            $this->assertStringContainsString('images/landing/speed-icon.png', $header, "{$name}: no product mark in the header");
            $this->assertStringContainsString('City of San Pedro, Laguna', $header, "{$name}: no owner line in the header");
            $this->assertStringContainsString('backdrop-blur-md', $header, "{$name}: not the frosted header");
        }
    }

    public function test_every_page_after_the_landing_page_opens_with_the_shared_intro(): void
    {
        foreach (array_diff_key($this->pages(), ['landing page' => true]) as $name => $content) {
            $this->assertMatchesRegularExpression('/<h1 class="cz-title[ "]/', $content, "{$name}: its title is not the shared one");
        }
    }

    public function test_every_page_uses_the_shared_buttons(): void
    {
        // The privacy notice is read, not acted on, so it has no primary action.
        foreach (array_diff_key($this->pages(), ['privacy notice' => true]) as $name => $content) {
            $this->assertStringContainsString('cz-btn cz-btn-primary', $content, "{$name}: no shared primary button");
        }
    }

    public function test_no_page_falls_back_to_a_retired_dialect(): void
    {
        foreach ($this->pages() as $name => $content) {
            // The staff registry panels and buttons the tracking page borrowed.
            $this->assertStringNotContainsString('class="panel', $content, "{$name}: staff panel");
            $this->assertStringNotContainsString('cr-btn', $content, "{$name}: staff button");
            // The request form's and lookup's one-off buttons.
            $this->assertStringNotContainsString('req-submit', $content, "{$name}: old submit button");
            $this->assertStringNotContainsString('track-cta', $content, "{$name}: old lookup button");
            // The receipt's own grey page and header.
            $this->assertStringNotContainsString('bg-[#f1f2f1]', $content, "{$name}: old receipt backdrop");
        }
    }

    public function test_the_tracking_stepper_keeps_its_captions_inside_the_card(): void
    {
        // The old stepper hung a fixed-width caption off a zero-width stage, so
        // the first and last ones ran past the card edge and were clipped
        // ("ending", "Complet") until they were inset by hand. The citizen page
        // draws the Look Up rail (<x-stage-bar surface="citizen">) now, which
        // gives every stage its own grid column — a caption is bounded by its
        // column, so there is no edge left to hang over.
        $this->get($this->document()->citizenTrackingUrl())
            ->assertOk()
            ->assertSee('dm-steps dm-steps-cz', false);

        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.dm-steps \{[^}]*grid-template-columns: repeat\(4, minmax\(0, 1fr\)\);/',
            $css,
            'the rail must lay its stages out in equal, bounded columns'
        );
    }

    public function test_the_stepper_circles_sit_above_the_line(): void
    {
        // The segments run centre to centre under each circle and come later in
        // the markup, so without a stacking order the line crossed the numbers.
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression('/\.dm-step-dot \{[^}]*position: relative;[^}]*z-index: 1;/', $css);
    }

    public function test_the_completion_date_is_not_set_in_monospace_for_citizens(): void
    {
        $content = $this->get($this->document()->citizenTrackingUrl())->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/Likely ready by <span class="stamp">/', $content);
    }
}
