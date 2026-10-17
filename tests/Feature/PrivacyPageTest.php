<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The RA 10173 privacy notice: the page the request form's consent checkbox
 * asks citizens to agree to, so it must exist and be reachable from that box.
 */
class PrivacyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_read_the_privacy_notice(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('<title>Privacy Notice', false)
            ->assertSee('Data Privacy Act of 2012 (RA&nbsp;10173)', false)
            ->assertSeeInOrder([
                'What we collect',
                'How we use it',
                'Who can see it',
                'What the public tracking page shows',
                'How long we keep it',
                'How we protect it',
                'Your rights',
                'Contact and complaints',
            ])
            ->assertSee('National Privacy Commission');
    }

    public function test_signed_in_staff_can_read_it_too(): void
    {
        // Unlike the landing page, it never bounces a signed-in user to a desk:
        // on a shared counter PC the citizen still has to be able to read it.
        $this->seedRolesAndPermissions();

        $this->actingAs(User::factory()->create()->assignRole('Supervisor'))
            ->get(route('privacy'))
            ->assertOk();
    }

    public function test_every_table_of_contents_link_has_a_section(): void
    {
        $content = $this->get(route('privacy'))->assertOk()->getContent();

        $start = (int) strpos($content, 'aria-label="On this page"');
        $toc = substr($content, $start, (int) strpos($content, '</nav>', $start) - $start);

        preg_match_all('/<a href="#([a-z-]+)"/', $toc, $anchors);
        $this->assertCount(8, $anchors[1]);

        foreach ($anchors[1] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $content);
        }
    }

    public function test_the_consent_checkbox_links_to_the_notice_without_losing_the_form(): void
    {
        // A new tab, so a citizen halfway through the form keeps what they typed.
        $this->get(route('public.request.create'))
            ->assertOk()
            ->assertSee('href="'.route('privacy').'" target="_blank" rel="noopener"', false);
    }
}
