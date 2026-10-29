<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Link Telegram" shows in the header, on the profile page and in notification settings. */
class TelegramLinkButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.bot_token' => '123456:TEST']);
    }

    public function test_a_google_member_finds_it_on_the_profile_and_notification_pages(): void
    {
        $member = User::factory()->create();
        $member->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'g-1']);

        foreach ([route('profile.edit'), route('notifications.edit')] as $page) {
            $html = $this->actingAs($member)->get($page)->assertOk()->getContent();

            $this->assertSame(2, substr_count($html, ' data-telegram-link'), "header + page button on $page");
            $this->assertSame(1, substr_count($html, 'telegram-widget.js'), "the script loads once on $page");
        }
    }

    public function test_linked_members_see_no_button_unless_the_bot_cannot_reach_them(): void
    {
        $member = User::factory()->create();
        $member->socialAccounts()->create(['provider' => 'telegram', 'provider_id' => '777']);

        $this->actingAs($member)->get(route('profile.edit'))->assertDontSee('data-telegram-link', false);
        $this->actingAs($member)->get(route('notifications.edit'))->assertDontSee('data-telegram-link', false);

        $member->forceFill(['telegram_blocked_at' => now()])->save();
        $this->actingAs($member)->get(route('notifications.edit'))
            ->assertSee('data-telegram-link', false)->assertSee('تفعيل الرسائل');
    }

    public function test_nothing_shows_while_telegram_sign_in_is_not_set_up(): void
    {
        config(['services.telegram.bot_token' => null]);

        $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertDontSee('data-telegram-link', false);
    }
}
