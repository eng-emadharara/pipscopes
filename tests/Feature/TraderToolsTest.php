<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The free trader calculators: their pages, links between them, and the sitemap. */
class TraderToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_calculator_has_its_page_with_the_form_and_the_check(): void
    {
        foreach (['tools.money-management' => 'plan', 'tools.lot-size' => 'lot', 'tools.pip-value' => 'pip', 'tools.profit' => 'profit'] as $route => $calc) {
            $this->get(route($route))->assertOk()
                ->assertSee('data-calc="'.$calc.'"', false)
                ->assertSee('data-tool-check-link', false)
                ->assertSee('<option value="XAUUSD">', false);
        }

        $this->get(route('tools.index'))->assertOk()->assertSee(route('tools.lot-size'))->assertSee(route('channel-compare'));
        $this->get('/')->assertSee(route('tools.index'));
    }

    public function test_the_money_management_plan_has_every_part_of_the_plan(): void
    {
        $this->get(route('tools.money-management'))->assertOk()
            ->assertSee('name="style"', false)
            ->assertSee('name="profile"', false)
            ->assertSee('data-plan-too-small', false)
            ->assertSee(collect(['risk-amount', 'lots', 'open-trades', 'target-pips', 'day-limit', 'week-limit', 'streak-left'])
                ->map(fn ($out) => 'data-out="'.$out.'"')->all(), false);

        // First among the calculators on the homepage, and in the sitemap
        $this->get('/')->assertSee(route('tools.money-management'));
        \Illuminate\Support\Facades\Cache::flush();
        $this->get(route('seo.sitemap'))->assertSee(route('tools.money-management'));
    }

    public function test_the_homepage_has_compare_and_tools_sections_the_admin_can_switch_off(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('data-home-channel-compare', false)
            ->assertSee('action="'.route('channel-compare').'"', false)
            ->assertSee('data-home-tools', false)
            ->assertSee(route('tools.pip-value'))
            // The comparison sits right under the check
            ->assertSeeInOrder(['data-home-channel-check', 'data-home-channel-compare', 'data-home-tools'], false);

        Setting::put(['home_layout' => [['key' => 'channel_check', 'enabled' => false], ['key' => 'tools', 'enabled' => false]]]);
        $this->get('/')->assertDontSee('data-home-channel-compare', false)->assertDontSee('data-home-tools', false);
    }

    public function test_the_account_check_is_a_card_in_the_tools_and_its_switch_still_works(): void
    {
        $this->get('/')->assertOk()
            ->assertSeeInOrder(['data-home-tools', route('account-check'), route('tools.lot-size')], false)
            ->assertDontSee('id="account-check"', false);
        $this->get(route('tools.index'))->assertSee(route('account-check'));
        $this->get(route('account-check'))->assertOk();

        Setting::put(['home_layout' => [['key' => 'account_check', 'enabled' => false]]]);
        $this->get('/')->assertDontSee(route('account-check'));
        $this->get(route('account-check'))->assertNotFound();
    }

    public function test_the_calculators_stay_open_before_launch_and_are_in_the_sitemap(): void
    {
        Setting::put(['prelaunch_enabled' => true]);
        $this->get(route('tools.lot-size'))->assertOk();

        Setting::put(['prelaunch_enabled' => false]);
        \Illuminate\Support\Facades\Cache::flush();
        $this->get(route('seo.sitemap'))->assertOk()
            ->assertSee(route('tools.lot-size'))
            ->assertSee(route('channel-check'));
    }
}
