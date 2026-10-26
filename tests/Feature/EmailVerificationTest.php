<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\Category;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function provider(): Provider
    {
        $provider = Provider::create(['name' => 'Test Channel', 'service_type' => 'Signals', 'is_active' => true]);
        $provider->categories()->attach(Category::create(['name' => 'Signals', 'slug' => 'signals'])->id);

        return $provider;
    }

    /** Registers and returns the code that was emailed. */
    protected function registerAndGetCode(string $email = 'new@example.com'): string
    {
        $code = null;

        $this->post('/register', [
            'name' => 'New User',
            'email' => $email,
            'password' => 'Secret-12345',
            'password_confirmation' => 'Secret-12345',
        ])->assertRedirect(route('verification.notice'));

        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use ($email, &$code) {
            $code = $mail->code;

            return $mail->hasTo($email);
        });

        return $code;
    }

    public function test_registration_emails_a_six_digit_code(): void
    {
        Mail::fake();

        $code = $this->registerAndGetCode();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNull(User::where('email', 'new@example.com')->value('email_verified_at'));
    }

    public function test_unverified_users_cannot_write_reviews(): void
    {
        Mail::fake();
        $provider = $this->provider();
        $this->registerAndGetCode();

        $this->get(route('reviews.create', $provider))->assertRedirect(route('verification.notice'));
    }

    public function test_correct_code_verifies_and_returns_to_the_review_form(): void
    {
        Mail::fake();
        $provider = $this->provider();
        $code = $this->registerAndGetCode();

        // Visiting the review form first remembers it as the destination after verifying
        $this->get(route('reviews.create', $provider));

        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('reviews.create', $provider));

        $this->assertNotNull(User::where('email', 'new@example.com')->value('email_verified_at'));
        $this->get(route('reviews.create', $provider))->assertOk();
    }

    public function test_wrong_codes_are_rejected_and_limited(): void
    {
        Mail::fake();
        $code = $this->registerAndGetCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('verification.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        }

        // After 5 wrong attempts even the right code is refused until a new one is requested
        $this->post(route('verification.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNull(User::where('email', 'new@example.com')->value('email_verified_at'));
    }

    public function test_expired_code_is_rejected(): void
    {
        Mail::fake();
        $code = $this->registerAndGetCode();

        $this->travel(31)->minutes();

        $this->post(route('verification.verify'), ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_resend_has_a_cooldown_and_sends_a_new_code(): void
    {
        Mail::fake();
        $this->registerAndGetCode();

        $this->post(route('verification.resend'))->assertSessionHasErrors('code');
        Mail::assertSentCount(1);

        $this->travel(61)->seconds();
        $this->post(route('verification.resend'))->assertSessionHasNoErrors();
        Mail::assertSentCount(2);
    }

    public function test_verification_email_renders_in_both_languages(): void
    {
        $user = User::factory()->create(['name' => 'Sara']);

        app()->setLocale('ar');
        $ar = (new VerificationCodeMail($user, '123456'))->render();
        $this->assertStringContainsString('123456', $ar);
        $this->assertStringContainsString('dir="rtl"', $ar);

        app()->setLocale('en');
        $en = (new VerificationCodeMail($user, '654321'))->render();
        $this->assertStringContainsString('Hi Sara,', $en);
        $this->assertStringContainsString('dir="ltr"', $en);
    }
}
