<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\Setting;
use App\Models\User;
use App\Support\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The Telegram direct-contact link and contact email across the site and in emails. */
class ContactLinksTest extends TestCase
{
    use RefreshDatabase;

    protected const LINK = 'https://t.me/pipscopes?direct';

    public function test_telegram_contact_link_shows_only_once_set(): void
    {
        foreach (['/', '/contact', '/submit'] as $url) {
            $this->get($url)->assertDontSee(__('راسلنا على تيليجرام'));
        }

        Setting::put([Site::KEY => ['telegram_contact' => self::LINK, 'contact_email' => 'hello@pipscopes.com']]);

        $this->get('/')->assertSee('href="'.self::LINK.'"', false);
        $this->get('/contact')->assertSee(__('تفضّل الرد الأسرع؟'))->assertSee('"location":"contact"', false)->assertSee('mailto:hello@pipscopes.com', false);
        $this->get('/submit')->assertSee('"location":"submit"', false);
    }

    public function test_verification_email_uses_the_site_logo_and_contacts(): void
    {
        Storage::fake('public');
        $logo = UploadedFile::fake()->image('logo.png', 120, 80)->store('site', 'public');
        $user = User::factory()->create();

        $html = (new VerificationCodeMail($user, '123456'))->render();
        $this->assertStringNotContainsString(__('تحتاج مساعدة؟ تواصل معنا:'), $html);
        $this->assertStringContainsString('★', $html);

        Setting::put([Site::KEY => ['logo' => $logo, 'telegram_contact' => self::LINK, 'contact_email' => 'hello@pipscopes.com']]);

        $html = (new VerificationCodeMail($user, '123456'))->render();
        $this->assertStringContainsString('src="'.asset('storage/'.$logo).'"', $html);
        $this->assertStringContainsString('href="'.self::LINK.'"', $html);
        $this->assertStringContainsString('mailto:hello@pipscopes.com', $html);
        $this->assertStringContainsString('123456', $html);
    }

    public function test_admin_rejects_links_outside_telegram(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        \Livewire\Livewire::test(\App\Filament\Pages\SiteSettings::class)
            ->set('data.telegram_contact', 'https://example.com/chat')
            ->call('save')
            ->assertHasErrors(['data.telegram_contact']);
    }
}
