<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The split-card login: brand on the left, credentials on the right, on a
 * plain white page
 */
class LoginPageDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_shows_the_brand_half_and_the_credentials_half(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('images/staff/logo-white.png', false)
            ->assertSee('Secure document tracking and QR verification.')
            ->assertSee('Welcome')
            ->assertSee('Input your credentials to continue');
    }

    public function test_credential_fields_are_prompted_and_carry_leading_icons(): void
    {
        // Placeholders tell the citizen what goes in the field; the icons are
        // decorative, so they stay hidden from assistive tech.
        $response = $this->get(route('login'))->assertOk();

        $response->assertSee('placeholder="Enter your email"', false);
        $response->assertSee('placeholder="Enter your password"', false);
        $response->assertSee('form-input-with-icon', false);
        $this->assertSame(
            2,
            substr_count($response->getContent(), 'class="field-icon" aria-hidden="true"')
        );
    }

    public function test_tab_title_names_the_brand_and_the_page(): void
    {
        // The brand leads so a row of pinned tabs stays identifiable, and the
        // page name distinguishes login from register.
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<title>SPeED TraQR — Login</title>', false);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('<title>SPeED TraQR — Forgot Password</title>', false);
    }

    public function test_login_backdrop_is_plain_white(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('doodle-pattern.jpg', false)
            ->assertDontSee('radial-gradient', false);
    }

    public function test_login_links_back_to_the_homepage(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('href="'.route('welcome').'" class="auth-home-link"', false)
            ->assertSee('Back to Homepage');
    }

    public function test_password_stays_masked_until_the_reveal_is_used(): void
    {
        // The pill field gained an icon and a placeholder; the reveal toggle
        // must still be the only thing that unmasks it.
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('type="password"', false)
            ->assertSee('show ? \'text\' : \'password\'', false);
    }
}
