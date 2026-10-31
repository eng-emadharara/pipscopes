<?php

namespace Tests\Feature;

use App\Models\Impersonator;
use App\Models\Provider;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Homepage hero: impersonators count and popular channels. */
class HomeHeroTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'is_active' => true]);
    }

    protected function review(string $body, ?Provider $provider = null): Review
    {
        return Review::create([
            'provider_id' => ($provider ?? $this->provider)->id, 'user_id' => User::factory()->create()->id, 'rating' => 5,
            'body' => $body, 'status' => Review::STATUS_APPROVED,
        ]);
    }

    public function test_only_confirmed_impersonators_of_active_channels_are_counted(): void
    {
        // Nothing to show yet: no "0" in the hero
        $this->get('/')->assertDontSee(__('حساب منتحل تم كشفه'));

        $hidden = Provider::create(['name' => 'Hidden', 'service_type' => 'Signals', 'is_active' => false]);
        Impersonator::create(['provider_id' => $this->provider->id, 'platform' => 'telegram', 'handle' => '@fake_one', 'status' => 'confirmed']);
        Impersonator::create(['provider_id' => $this->provider->id, 'platform' => 'telegram', 'handle' => '@fake_two', 'status' => 'confirmed']);
        Impersonator::create(['provider_id' => $this->provider->id, 'platform' => 'telegram', 'handle' => '@maybe', 'status' => 'pending']);
        Impersonator::create(['provider_id' => $hidden->id, 'platform' => 'telegram', 'handle' => '@other', 'status' => 'confirmed']);

        $this->get('/')->assertOk()->assertViewHas('stats', fn ($stats) => $stats['impersonators'] === 2)
            ->assertSee(__('حساب منتحل تم كشفه'));
    }

    public function test_popular_channels_are_the_most_reviewed_ones(): void
    {
        $quiet = Provider::create(['name' => 'Quiet Channel', 'service_type' => 'Signals', 'is_active' => true]);
        $this->review('A long enough review body for testing.');

        $this->get('/')->assertSee(__('شائع:'))
            ->assertViewHas('popular', fn ($popular) => $popular->pluck('id')->all() === [$this->provider->id]);

        $this->review('Another long enough review body.', $quiet);
        $this->review('And one more for the quiet channel.', $quiet);

        $this->get('/')->assertViewHas('popular', fn ($popular) => $popular->first()->is($quiet));
    }
}
