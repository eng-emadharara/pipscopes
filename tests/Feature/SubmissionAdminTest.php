<?php

namespace Tests\Feature;

use App\Models\ProviderSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The admin's review screen for channels sent through the add-channel form. */
class SubmissionAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_screen_shows_the_data_read_from_telegram(): void
    {
        $submission = ProviderSubmission::create([
            'name' => 'Gold Channel', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/gold_channel',
            'category_ids' => [], 'market_ids' => [], 'status' => 'new',
            'avatar' => 'https://cdn4.telesco.pe/file/gold.jpg',
            'telegram_data' => ['username' => 'gold_channel', 'subscribers' => 12345, 'verified' => true, 'avg_views' => 900, 'last_post_at' => '2026-10-25T10:00:00+00:00'],
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get("/admin/provider-submissions/{$submission->id}/edit")
            ->assertOk()
            ->assertSee('بيانات القناة من تيليجرام')
            ->assertSee('12,345')
            ->assertDontSee('مرسل الطلب');

        $this->get('/admin/provider-submissions')->assertOk()->assertSee('Gold Channel');
    }
}
