<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Site identity / SEO settings from the admin panel. */
class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_apply_when_nothing_is_set(): void
    {
        $this->get('/')
            ->assertSee('<title>'.config('trust.default_name').'</title>', false)
            ->assertSee(route('share-images.site', 'ar'), false)
            ->assertDontSee('name="robots"', false);
    }

    public function test_name_meta_and_footer_follow_the_settings_per_language(): void
    {
        Setting::put([Site::KEY => [
            'name' => ['ar' => 'موثوق', 'en' => 'Mawthooq'],
            'meta_description' => ['ar' => 'وصف عربي للموقع'],
            'meta_keywords' => ['ar' => 'توصيات, فوركس'],
            'footer_about' => ['ar' => 'نبذة التذييل'],
            'contact_email' => 'hello@example.com',
            'socials' => ['telegram' => 'https://t.me/mawthooq', 'x' => null],
            'google_verification' => 'abc123',
            'ga_id' => 'G-TEST123',
            'noindex' => true,
        ]]);

        $this->get('/')
            ->assertSee('<title>موثوق</title>', false)
            ->assertSee('<meta name="description" content="وصف عربي للموقع">', false)
            ->assertSee('<meta name="keywords" content="توصيات, فوركس">', false)
            ->assertSee('<meta name="google-site-verification" content="abc123">', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('gtag/js?id=G-TEST123', false)
            ->assertSee('نبذة التذييل')
            ->assertSee('mailto:hello@example.com', false)
            ->assertSee('https://t.me/mawthooq', false);

        // English name, Arabic description as fallback
        $this->get('/?lang=en')
            ->assertSee('<title>Mawthooq</title>', false)
            ->assertSee('وصف عربي للموقع');
    }

    public function test_uploaded_logo_favicon_and_share_image_are_used(): void
    {
        Storage::fake('public');
        foreach (['logo' => [256, 256], 'favicon' => [512, 512], 'og_image' => [1200, 630]] as $key => [$w, $h]) {
            Storage::disk('public')->putFileAs('site', UploadedFile::fake()->image("$key.png", $w, $h), "$key.png");
        }
        Setting::put([Site::KEY => ['logo' => 'site/logo.png', 'favicon' => 'site/favicon.png', 'og_image' => 'site/og_image.png', 'show_name_with_logo' => false]]);

        $this->get('/')
            ->assertSee('<link rel="icon" href="'.asset('storage/site/favicon.png').'">', false)
            ->assertSee('<meta property="og:image" content="'.asset('storage/site/og_image.png').'">', false)
            ->assertSee('<meta property="og:image:width" content="1200">', false)
            ->assertSee('src="'.asset('storage/site/logo.png').'"', false);
    }

    public function test_admin_saves_settings_and_old_files_are_removed(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/old-logo.png', 'x');
        Setting::put([Site::KEY => ['logo' => 'site/old-logo.png']]);

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get('/admin/site-settings')->assertOk()->assertSee('إعدادات الموقع');

        Livewire::test(SiteSettings::class)
            ->set('data.name.ar', 'اسم جديد')
            ->set('data.logo', [])
            ->set('data.google_verification', '<meta name="google-site-verification" content="xyz789" />')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('اسم جديد', Site::name('ar'));
        $this->assertSame('xyz789', Site::get('google_verification'));
        Storage::disk('public')->assertMissing('site/old-logo.png');
    }
}
