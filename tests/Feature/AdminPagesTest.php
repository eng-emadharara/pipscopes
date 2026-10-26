<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Impersonator;
use App\Models\Market;
use App\Models\Page;
use App\Models\Provider;
use App\Models\ProviderSubmission;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Opens every admin screen with real records, so a broken form or table fails here instead of in the browser. */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_page_renders(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Signals', 'slug' => 'signals']);

        $provider = Provider::create([
            'name' => 'Gold Pro', 'slug' => 'gold-pro', 'service_type' => 'Gold signals', 'is_active' => true,
            'telegram_url' => 'https://t.me/goldpro', 'extra_links' => [['label' => 'Support', 'url' => 'https://t.me/goldpro_support']],
        ]);
        $provider->categories()->attach($category->id);
        $provider->markets()->attach(Market::where('slug', 'gold')->value('id'));
        $owner = $provider->owners()->create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'Secret-12345']);
        $provider->changeLog()->create(['provider_owner_id' => $owner->id, 'diff' => ['telegram_url' => ['old' => 'https://t.me/a', 'new' => 'https://t.me/b']]]);

        $review = Review::create([
            'provider_id' => $provider->id, 'user_id' => User::factory()->create()->id, 'rating' => 4,
            'title' => 'Good', 'body' => 'A long enough review body for testing.', 'status' => Review::STATUS_APPROVED,
        ]);
        ReviewReport::create(['review_id' => $review->id, 'voter_key' => 'g:test', 'reason' => 'fake']);

        $submission = ProviderSubmission::create([
            'submitter_name' => 'Tester', 'submitter_contact' => '@tester', 'relation' => 'owner',
            'category_ids' => [$category->id], 'market_ids' => [Market::where('slug', 'forex')->value('id')],
            'name' => 'New Channel', 'service_type' => 'Forex', 'telegram_url' => 'https://t.me/new_channel',
        ]);

        Impersonator::create(['provider_id' => $provider->id, 'platform' => 'telegram', 'handle' => '@goldpro_vip', 'status' => 'pending']);
        ContactMessage::create(['name' => 'Visitor', 'email' => 'v@example.com', 'subject' => 'general', 'message' => 'Hello there, a question.']);
        $page = Page::create(['slug' => 'about', 'title' => 'من نحن', 'content' => '<h2>قسم</h2><p>نص</p>']);

        $urls = [
            '/admin',
            '/admin/launch-settings',
            '/admin/owner-portal',
            '/admin/providers', '/admin/providers/create', "/admin/providers/{$provider->slug}/edit",
            '/admin/categories', '/admin/markets', '/admin/users',
            '/admin/pages', '/admin/pages/create', "/admin/pages/{$page->slug}/edit",
            '/admin/reviews', "/admin/reviews/{$review->id}/edit",
            '/admin/review-reports',
            '/admin/provider-submissions', "/admin/provider-submissions/{$submission->id}/edit",
            '/admin/impersonators',
            '/admin/contact-messages',
        ];

        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_non_admins_cannot_open_the_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }
}
