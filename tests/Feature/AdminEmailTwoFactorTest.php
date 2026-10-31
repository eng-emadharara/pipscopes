<?php

namespace Tests\Feature;

use App\Filament\Auth\EmailTwoFactor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Solutionforest\FilamentEmail2fa\Mail\TwoFAEmail;
use Tests\TestCase;

/** The emailed code asked for after an admin signs in (App\Filament\Auth\EmailTwoFactor). */
class AdminEmailTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['trust.admin_email_2fa' => true]);
        Mail::fake();
        $this->admin = User::factory()->create(['is_admin' => true, 'email' => 'boss@gmail.com']);
        $this->actingAs($this->admin);
    }

    public function test_the_panel_waits_for_the_code_sent_on_arrival(): void
    {
        $this->get('/admin')->assertRedirect(route('filament.admin.pages.sf-filament-2fa.2fa'));

        $page = Livewire::test(EmailTwoFactor::class);
        $code = $this->sentCode();

        // Only a hash is stored
        $this->assertNotSame($code, $this->admin->twoFaCodes()->value('code'));

        $page->set('data.2fa_code', $code)->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame(1, $this->admin->twoFaVerifis()->count());
        // Used once
        $this->assertSame(0, $this->admin->twoFaCodes()->count());
    }

    public function test_wrong_codes_lock_the_page_and_drop_the_code(): void
    {
        $page = Livewire::test(EmailTwoFactor::class);
        $code = $this->sentCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, EmailTwoFactor::MAX_WRONG_CODES) as $i) {
            $page->set('data.2fa_code', $wrong)->call('save')->assertHasErrors('data.2fa_code');
        }

        $page->set('data.2fa_code', $code)->call('save')->assertHasErrors('data.2fa_code');
        $this->assertSame(0, $this->admin->twoFaVerifis()->count());
        $this->assertSame(0, $this->admin->twoFaCodes()->count());
    }

    public function test_resending_is_limited(): void
    {
        // The first email goes out on arrival
        $page = Livewire::test(EmailTwoFactor::class);

        foreach (range(2, EmailTwoFactor::MAX_EMAILS) as $i) {
            $page->call('resend')->assertHasNoErrors();
        }
        $page->call('resend')->assertHasErrors('data.2fa_code');

        Mail::assertSent(TwoFAEmail::class, EmailTwoFactor::MAX_EMAILS);
    }

    public function test_the_account_cannot_be_switched_from_the_browser(): void
    {
        User::factory()->create(['is_admin' => true, 'email' => 'other@gmail.com']);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(EmailTwoFactor::class)->set('email', 'other@gmail.com');
    }

    protected function sentCode(): string
    {
        $code = null;
        Mail::assertSent(TwoFAEmail::class, function (TwoFAEmail $mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('boss@gmail.com');
        });

        return $code;
    }
}
