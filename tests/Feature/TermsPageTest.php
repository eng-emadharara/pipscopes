<?php

namespace Tests\Feature;

use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The terms of service page, seeded like the other content pages. */
class TermsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_page_exists_in_both_languages_and_is_linked(): void
    {
        $this->seed(PageSeeder::class);

        $this->get(route('pages.show', 'terms'))
            ->assertOk()
            ->assertSee('شروط الاستخدام')
            ->assertSee('لسنا مستشاراً مالياً');

        $this->get(route('pages.show', 'terms').'?lang=en')
            ->assertSee('Terms of service')
            ->assertSee('We are not a financial adviser');

        // In the footer of every page, and on the sign-up form
        $this->get('/?lang=ar')->assertSee(route('pages.show', 'terms'), false);
        $this->get(route('register'))->assertSee(route('pages.show', 'terms'), false);
    }
}
