<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Maintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Maintenance mode closes the public site with a 503 page; admins and allowed visitors still get through. */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(['maintenance_enabled' => true, 'maintenance_secret' => 'team-secret']);
    }

    public function test_visitors_get_the_maintenance_page_on_every_public_page(): void
    {
        foreach (['/', '/providers', '/contact', '/login'] as $url) {
            $this->get($url)->assertStatus(503)
                ->assertHeader('Retry-After', 3600)
                ->assertSee('نعمل على تحسين المنصة من أجلك');
        }

        $this->getJson('/search/suggest?q=gold')->assertStatus(503)->assertJsonStructure(['message']);
    }

    public function test_the_site_is_open_when_maintenance_is_off(): void
    {
        Setting::put(['maintenance_enabled' => false]);

        $this->get('/')->assertOk()->assertDontSee('نعمل على تحسين المنصة من أجلك');
    }

    public function test_admins_see_the_site_with_a_notice_and_can_preview_the_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/')->assertOk()->assertSee('وضع الصيانة مفعّل');
        $this->actingAs($admin)->get('/?preview=maintenance')->assertStatus(503)->assertSee('معاينة: هذا ما يراه الزوار');
        $this->actingAs($admin)->get('/admin/maintenance')->assertOk()->assertSee('إيقاف وضع الصيانة');
    }

    public function test_members_are_not_let_through(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertStatus(503);
    }

    public function test_the_admin_login_and_robots_txt_stay_reachable(): void
    {
        $this->get('/admin/login')->assertOk();
        $this->get('/robots.txt')->assertOk();
    }

    public function test_the_bypass_link_lets_its_holder_in_until_a_new_link_is_made(): void
    {
        $this->get('/?bypass=wrong')->assertStatus(503);

        $response = $this->get('/providers?bypass=team-secret');
        $response->assertRedirect('/providers')->assertCookie(Maintenance::BYPASS_COOKIE);
        $cookie = $response->getCookie(Maintenance::BYPASS_COOKIE)->getValue();

        $this->withCookie(Maintenance::BYPASS_COOKIE, $cookie)->get('/')->assertOk()->assertSee('وضع الصيانة مفعّل');

        Setting::put(['maintenance_secret' => 'new-secret']);
        $this->withCookie(Maintenance::BYPASS_COOKIE, $cookie)->get('/')->assertStatus(503);
    }

    public function test_allowed_ips_see_the_site(): void
    {
        Setting::put(['maintenance_allowed_ips' => ['10.0.0.0/24']]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])->get('/')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.7'])->get('/')->assertStatus(503);
    }

    public function test_countdown_and_automatic_end(): void
    {
        Setting::put(['maintenance_ends_at' => now()->addHours(2)->format('Y-m-d H:i:s'), 'maintenance_timezone' => 'UTC', 'maintenance_auto_end' => true]);

        $this->get('/')->assertStatus(503)->assertSee('data-countdown', false);
        $this->assertEqualsWithDelta(7200, (int) $this->get('/')->headers->get('Retry-After'), 5);

        $this->travel(3)->hours();
        $this->assertFalse(Maintenance::isActive());
        $this->get('/')->assertOk();
    }

    public function test_the_page_uses_the_admin_texts_in_the_visitors_language(): void
    {
        Setting::put(['maintenance_texts' => ['ar' => ['headline' => 'نرقّي الخوادم'], 'en' => ['headline' => 'Upgrading servers']]]);

        $this->get('/')->assertSee('نرقّي الخوادم');
        $this->get('/?lang=en')->assertSee('Upgrading servers')->assertSee('Scheduled maintenance in progress');
    }
}
