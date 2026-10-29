<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Support\SignupGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Stops one person opening many accounts to post reviews: throwaway emails, aliases, one IP, new accounts. */
class SignupGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function register(string $email, string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/register', [
            'name' => 'Tester', 'email' => $email, 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ]);
    }

    protected function provider(): Provider
    {
        return Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/GoldPro', 'is_active' => true]);
    }

    protected function review(User $user, Provider $provider, string $ip = '10.0.0.9')
    {
        return $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('reviews.store', $provider), ['rating' => 5, 'title' => 'Great', 'body' => 'A long enough review body for testing.']);
    }

    public function test_throwaway_email_domains_are_refused(): void
    {
        foreach (['me@10minutemail.com', 'me@mailinator.com', 'me@sub.yopmail.com', 'ME@Temp-Mail.org'] as $email) {
            $this->register($email)->assertSessionHasErrors('email');
            auth()->logout();
        }

        $this->assertSame(0, User::count());
        $this->register('real@gmail.com')->assertSessionHasNoErrors();
    }

    public function test_admin_lists_and_switch(): void
    {
        Setting::put([SignupGuard::SETTING => ['blocked_domains' => "spammy.net", 'allowed_domains' => 'yopmail.com']]);

        $this->register('a@spammy.net')->assertSessionHasErrors('email');
        $this->register('a@yopmail.com')->assertSessionHasNoErrors();
        auth()->logout();

        Setting::put([SignupGuard::SETTING => ['block_disposable' => false]]);
        $this->register('b@mailinator.com')->assertSessionHasNoErrors();
    }

    public function test_domains_without_a_mail_server_are_refused(): void
    {
        SignupGuard::resolveMxUsing(fn ($domain) => $domain !== 'gmial.com');

        $this->register('me@gmial.com')->assertSessionHasErrors('email');
        $this->register('me@gmail.com')->assertSessionHasNoErrors();
    }

    public function test_gmail_dots_and_plus_tags_cannot_open_a_second_account(): void
    {
        $this->assertSame('ahmad@gmail.com', SignupGuard::canonical('A.h.Mad+reviews@GoogleMail.com'));
        $this->assertSame('sara.k@outlook.com', SignupGuard::canonical('Sara.K+2@outlook.com'));

        $this->register('ahmad@gmail.com')->assertSessionHasNoErrors();
        auth()->logout();

        foreach (['a.hmad@gmail.com', 'ahmad+1@gmail.com', 'ahmad@googlemail.com'] as $email) {
            $this->register($email, '10.0.0.2')->assertSessionHasErrors('email');
        }
        $this->assertSame(1, User::count());
    }

    public function test_adding_an_email_to_a_telegram_account_is_checked_too(): void
    {
        User::factory()->create(['email' => 'taken@gmail.com']);
        $user = User::factory()->create(['email' => null, 'email_verified_at' => null]);
        $user->socialAccounts()->create(['provider' => 'telegram', 'provider_id' => '555']);

        $this->actingAs($user)->post(route('verification.email'), ['email' => 'x@yopmail.com'])->assertSessionHasErrors('email');
        $this->actingAs($user)->post(route('verification.email'), ['email' => 'ta.ken+x@gmail.com'])->assertSessionHasErrors('email');
        $this->actingAs($user)->post(route('verification.email'), ['email' => 'mine@gmail.com'])->assertSessionHasNoErrors();
        $this->assertSame('mine@gmail.com', $user->fresh()->email_canonical);
    }

    public function test_sign_ups_per_ip_are_limited_per_day(): void
    {
        Setting::put([SignupGuard::SETTING => ['ip_daily_limit' => 2]]);

        $this->register('one@gmail.com')->assertSessionHasNoErrors();
        auth()->logout();
        $this->register('two@gmail.com')->assertSessionHasNoErrors();
        auth()->logout();
        $this->register('three@gmail.com')->assertSessionHasErrors('email');
        $this->register('three@gmail.com', '10.0.0.7')->assertSessionHasNoErrors();
        auth()->logout();

        $this->assertSame('10.0.0.1', User::where('email', 'one@gmail.com')->value('signup_ip'));

        $this->travel(25)->hours();
        $this->register('four@gmail.com')->assertSessionHasNoErrors();
    }

    public function test_new_accounts_reviews_wait_for_moderation(): void
    {
        $provider = $this->provider();
        $newcomer = User::factory()->create();
        $veteran = User::factory()->create(['created_at' => now()->subMonth()]);

        $this->review($newcomer, $provider, '10.0.0.1');
        $this->review($veteran, $provider, '10.0.0.2');

        $held = Review::where('user_id', $newcomer->id)->first();
        $this->assertSame(Review::STATUS_PENDING, $held->status);
        $this->assertSame('new_account', $held->hold_reason);
        $this->assertSame('10.0.0.1', $held->ip);
        $this->assertSame(Review::STATUS_APPROVED, Review::where('user_id', $veteran->id)->value('status'));

        Setting::put([SignupGuard::SETTING => ['hold_new_reviews' => false]]);
        $this->review($newcomer, $provider, '10.0.0.1');
        $this->assertSame(Review::STATUS_APPROVED, $held->fresh()->status);
        $this->assertNull($held->fresh()->hold_reason);
    }

    public function test_a_second_account_reviewing_the_same_channel_from_the_same_ip_is_held(): void
    {
        $provider = $this->provider();
        [$first, $second] = User::factory(2)->create(['created_at' => now()->subMonth()]);

        $this->review($first, $provider, '10.0.0.5');
        $this->review($second, $provider, '10.0.0.5');

        $this->assertSame(Review::STATUS_APPROVED, Review::where('user_id', $first->id)->value('status'));
        $this->assertSame('same_ip', Review::where('user_id', $second->id)->value('hold_reason'));

        // Shows up in the admin filter
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        \Livewire\Livewire::test(\App\Filament\Resources\ReviewResource\Pages\ListReviews::class)
            ->filterTable('same_ip')
            ->assertCanSeeTableRecords(Review::all())
            ->assertSee('⚠️ 1');
    }

    public function test_the_disposable_list_downloads_and_rejects_bad_downloads(): void
    {
        // Keep a list downloaded on this machine out of the way
        $saved = is_file(SignupGuard::listPath()) ? file_get_contents(SignupGuard::listPath()) : null;
        \Illuminate\Support\Facades\File::delete(SignupGuard::listPath());
        Http::fake([SignupGuard::LIST_URL => Http::sequence()
            ->push("tiny.com\n")
            ->push(collect(range(1, 1500))->map(fn ($i) => "throwaway$i.com")->implode("\n"))]);

        try {
            SignupGuard::updateDisposableList();
            $this->fail('A short list should be refused');
        } catch (\RuntimeException) {
        }
        $this->assertFileDoesNotExist(SignupGuard::listPath());

        $this->assertSame(1500, SignupGuard::updateDisposableList());
        $this->assertTrue(SignupGuard::isDisposable('x@throwaway77.com'));
        $this->assertFalse(SignupGuard::isDisposable('x@mailinator.com'), 'the downloaded list replaces the starter one');

        \Illuminate\Support\Facades\File::delete(SignupGuard::listPath());
        if ($saved !== null) {
            file_put_contents(SignupGuard::listPath(), $saved);
        }
    }

    public function test_admin_page_saves_settings(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get('/admin/signup-protection')->assertOk()->assertSee('حماية التسجيل');

        \Livewire\Livewire::test(\App\Filament\Pages\SignupProtection::class)
            ->set('data.ip_daily_limit', 5)
            ->set('data.blocked_domains', "@Bad.com, worse.net")
            ->call('save')->assertHasNoErrors();

        $this->assertSame(5, (int) SignupGuard::setting('ip_daily_limit'));
        $this->assertSame("bad.com\nworse.net", SignupGuard::setting('blocked_domains'));
    }
}
