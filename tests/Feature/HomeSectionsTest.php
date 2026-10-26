<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Impersonator;
use App\Models\Provider;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Optional homepage sections: account checker, alerts, trust principles. */
class HomeSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Signals', 'slug' => 'signals']);
        $this->provider = Provider::create([
            'name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/GoldPro',
            'instagram_url' => 'https://instagram.com/goldpro.official', 'whatsapp' => '+962 79 123 4567', 'is_active' => true,
        ]);
        $this->provider->categories()->attach($category->id);
    }

    protected function check(string $account)
    {
        return $this->get(route('account-check', ['account' => $account]), ['X-Requested-With' => 'XMLHttpRequest']);
    }

    public function test_sections_are_shown_by_default_and_can_be_switched_off(): void
    {
        $this->get('/')->assertSee(__('هل هذا الحساب حقيقي؟'))->assertSee(__('كيف نضمن المصداقية'));

        Setting::put(['home_sections' => ['account_check' => false, 'alerts' => false, 'trust' => false]]);

        $this->get('/')->assertDontSee(__('هل هذا الحساب حقيقي؟'))->assertDontSee(__('كيف نضمن المصداقية'));
        $this->get(route('account-check', ['account' => '@goldpro']))->assertNotFound();
    }

    public function test_official_accounts_are_recognised_from_links_names_and_numbers(): void
    {
        $this->check('https://t.me/goldpro')->assertSee(__('حساب رسمي'))->assertSee('Gold Pro');
        $this->check('@GoldPro')->assertSee(__('حساب رسمي'))
            ->assertSee(__('تأكد أن من يتواصل معك يستخدم المنصة نفسها، فقد يستخدم منتحل الاسم ذاته على منصة أخرى.'));
        $this->check('goldpro.official')->assertSee(__('حساب رسمي'));
        $this->check('+962791234567')->assertSee(__('حساب رسمي'));

        // A link names its platform, so the same name elsewhere is not "official"
        $this->check('https://x.com/goldpro')->assertSee(__('لا نعرف هذا الحساب'));
    }

    public function test_confirmed_impersonators_are_flagged_and_pending_ones_are_not(): void
    {
        $this->provider->impersonators()->create(['platform' => 'telegram', 'handle' => '@goldpro_support', 'status' => 'confirmed']);
        $this->provider->impersonators()->create(['platform' => 'telegram', 'handle' => '@goldpro_admin', 'status' => 'pending']);

        $this->check('t.me/goldpro_support')->assertSee(__('احذر: هذا حساب منتحل'))->assertSee('Gold Pro');
        $this->check('@goldpro_admin')->assertSee(__('لا نعرف هذا الحساب'));
        $this->check('ab')->assertSee(__('أدخل رابط الحساب أو اسم المستخدم (مثل @gold_signals) أو رقم الهاتف.'));
    }

    public function test_checker_works_without_javascript(): void
    {
        $this->get(route('account-check', ['account' => '@goldpro']))
            ->assertOk()
            ->assertSee(__('حساب رسمي'))
            ->assertSee('value="@goldpro"', false);
    }

    public function test_alerts_list_confirmed_impersonators_and_flagged_channels(): void
    {
        $this->get('/')->assertDontSee(__('تنبيهات')); // nothing to show yet

        $this->provider->impersonators()->create(['platform' => 'instagram', 'handle' => '@goldpro_vip', 'status' => 'confirmed']);

        $shady = Provider::create(['name' => 'Shady Signals', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/shady', 'is_active' => true]);
        foreach ([['scam'], ['deleted_trades', 'losses'], []] as $i => $cons) {
            Review::create([
                'provider_id' => $shady->id, 'user_id' => User::factory()->create()->id, 'rating' => 1,
                'title' => "Review $i", 'body' => 'A long enough review body for testing.', 'cons' => $cons,
                'status' => Review::STATUS_APPROVED,
            ]);
        }

        $this->get('/')
            ->assertSee(__('تنبيهات'))
            ->assertSee('@goldpro_vip')
            ->assertSee('Shady Signals')
            ->assertSee(__(':percent% من المقيّمين', ['percent' => 67]));

        Setting::put(['home_sections' => ['alerts' => false]]);
        $this->get('/')->assertDontSee('@goldpro_vip');
    }

    public function test_channels_with_too_few_reviews_are_not_flagged(): void
    {
        Review::create([
            'provider_id' => $this->provider->id, 'user_id' => User::factory()->create()->id, 'rating' => 1,
            'title' => 'One bad review', 'body' => 'A long enough review body for testing.', 'cons' => ['scam'],
            'status' => Review::STATUS_APPROVED,
        ]);

        $this->get('/')->assertDontSee(__('قنوات عليها مؤشرات خطيرة'));
    }

    public function test_telegram_join_section_needs_a_link_and_uses_admin_texts(): void
    {
        $this->get('/')->assertDontSee(__('انضم للقناة'));

        Setting::put([\App\Support\Site::KEY => ['socials' => ['telegram' => 'https://t.me/pipscopes', 'instagram' => 'https://instagram.com/pipscopes']]]);
        $this->get('/')
            ->assertSee(__('انضم لقناتنا على تيليجرام'))
            ->assertSee('href="https://t.me/pipscopes"', false)
            ->assertSee(__('تابعنا أيضاً على'));

        Setting::put(['telegram_join' => ['title' => ['ar' => 'كن أول من يعرف']]]);
        $this->get('/')->assertSee('كن أول من يعرف');

        Setting::put(['home_sections' => ['telegram_join' => false]]);
        $this->get('/')->assertDontSee('كن أول من يعرف');
    }

    public function test_admin_can_toggle_sections(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/home-sections')->assertOk()->assertSee('أقسام الصفحة الرئيسية');
    }
}
