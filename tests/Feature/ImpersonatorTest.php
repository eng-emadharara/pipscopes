<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Impersonator;
use App\Models\Provider;
use App\Models\User;
use App\Support\AccountHandle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonatorTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::create([
            'name' => 'Gold Pro',
            'service_type' => 'Gold signals',
            'telegram_url' => 'https://t.me/GoldPro',
            'instagram_url' => 'https://instagram.com/goldpro.official',
            'extra_links' => [['label' => 'Support', 'url' => 'https://t.me/GoldPro_Support']],
            'is_active' => true,
        ]);
        $this->provider->categories()->attach(Category::create(['name' => 'Signals', 'slug' => 'signals'])->id);
    }

    protected function report(string $account, string $platform = 'telegram', array $extra = [])
    {
        return $this->post(route('impersonators.report', $this->provider), ['platform' => $platform, 'account' => $account] + $extra);
    }

    public function test_handles_in_different_formats_normalize_to_the_same_key(): void
    {
        $keys = collect(['https://t.me/GoldPro_VIP', 't.me/goldpro_vip', '@GoldPro_VIP', 'goldpro_vip'])
            ->map(fn ($v) => AccountHandle::parse($v, 'telegram')['key'])
            ->unique();

        $this->assertSame(['telegram:goldpro_vip'], $keys->values()->all());
        $this->assertSame('whatsapp:962791234567', AccountHandle::parse('+962 79 123 4567', 'whatsapp')['key']);
        $this->assertSame('instagram:fake.gold', AccountHandle::parse('https://www.instagram.com/fake.gold/')['key']);
    }

    public function test_report_is_pending_and_not_public_until_confirmed(): void
    {
        $this->report('@goldpro_vip', 'telegram', ['details' => 'Asked me to pay for VIP'])
            ->assertRedirect(route('providers.show', $this->provider).'#impersonators')
            ->assertSessionHas('impersonator_success');

        $fake = Impersonator::first();
        $this->assertSame('pending', $fake->status);
        $this->assertSame('@goldpro_vip', $fake->handle);
        $this->assertSame(1, $fake->reports_count);

        $this->get(route('providers.show', $this->provider))->assertDontSee('@goldpro_vip');

        $fake->update(['status' => 'confirmed']);

        $this->get(route('providers.show', $this->provider))
            ->assertSee('@goldpro_vip')
            ->assertSee(__('حسابات منتحلة معروفة'));
    }

    public function test_official_accounts_cannot_be_reported(): void
    {
        $this->report('https://t.me/goldpro')->assertSessionHasErrorsIn('impersonator', 'account');
        $this->report('@goldpro_support')->assertSessionHasErrorsIn('impersonator', 'account');
        $this->report('goldpro.official', 'instagram')->assertSessionHasErrorsIn('impersonator', 'account');

        $this->assertSame(0, Impersonator::count());
    }

    public function test_same_account_reported_by_different_people_is_one_entry(): void
    {
        $this->report('@goldpro_vip');
        $this->report('https://t.me/GoldPro_VIP'); // same guest again: not counted twice
        $this->actingAs(User::factory()->create())->report('t.me/goldpro_vip');

        $this->assertSame(1, Impersonator::count());
        $this->assertSame(2, Impersonator::first()->reports_count);
    }

    public function test_searching_a_confirmed_impersonator_shows_a_warning(): void
    {
        Impersonator::create(['provider_id' => $this->provider->id, 'platform' => 'telegram', 'handle' => '@goldpro_vip', 'status' => 'confirmed']);

        $this->getJson(route('search.suggest', ['q' => '@GoldPro_VIP']))
            ->assertJsonPath('impersonators.0.handle', '@goldpro_vip');

        $this->get(route('providers.index', ['q' => 'https://t.me/goldpro_vip']))
            ->assertSee('@goldpro_vip')
            ->assertSee('Gold Pro');

        // Partial words never trigger a warning
        $this->getJson(route('search.suggest', ['q' => 'goldpro']))->assertJsonCount(0, 'impersonators');
    }

    public function test_pending_impersonators_do_not_trigger_search_warnings(): void
    {
        Impersonator::create(['provider_id' => $this->provider->id, 'platform' => 'telegram', 'handle' => '@goldpro_vip', 'status' => 'pending']);

        $this->getJson(route('search.suggest', ['q' => '@goldpro_vip']))->assertJsonCount(0, 'impersonators');
    }
}
