<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Members stay signed in while they keep visiting; admins sign in again every few days; "sign out other devices". */
class StaySignedInTest extends TestCase
{
    use RefreshDatabase;

    protected function recaller(): string
    {
        return auth()->guard('web')->getRecallerName();
    }

    public function test_the_remember_cookie_is_renewed_once_a_day_for_members(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/')->assertCookie($this->recaller());
        $this->assertNotNull($member->fresh()->remember_token);

    }

    public function test_it_is_not_sent_again_the_same_day(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['remember_renewed_at' => now()->subHours(3)->timestamp])
            ->get('/')->assertCookieMissing($this->recaller());
    }

    public function test_it_is_sent_again_a_day_later(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['remember_renewed_at' => now()->subHours(25)->timestamp])
            ->get('/')->assertCookie($this->recaller());
    }

    public function test_admins_do_not_get_a_renewed_cookie(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/')->assertCookieMissing($this->recaller());
    }

    public function test_the_admin_panel_asks_for_a_real_sign_in_after_a_week(): void
    {
        config(['trust.admin_session_days' => 7]);
        $admin = User::factory()->create(['is_admin' => true]);

        // Signed in with the remember cookie only: no real sign-in recorded
        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/login');
        $this->assertGuest();

        $this->actingAs($admin)->withSession(['signed_in_at' => now()->timestamp])->get('/admin')->assertOk();

        $this->travel(8)->days();
        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_a_password_sign_in_counts_as_a_real_sign_in(): void
    {
        User::factory()->create(['email' => 'boss@gmail.com', 'password' => 'secret-pass-1', 'is_admin' => true]);

        $this->post('/login', ['email' => 'boss@gmail.com', 'password' => 'secret-pass-1']);

        $this->get('/admin')->assertOk();
    }

    public function test_members_can_sign_out_everywhere_else(): void
    {
        config(['session.driver' => 'database']);
        $member = User::factory()->create(['remember_token' => 'old-token']);
        DB::table('sessions')->insert([
            ['id' => 'phone', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'someone-else', 'user_id' => User::factory()->create()->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($member)->get(route('profile.edit'))->assertOk()->assertSee('حسابك مفتوح أيضاً على 1');

        $this->actingAs($member)->post(route('profile.sign-out-others'))
            ->assertSessionHas('success')
            ->assertCookie($this->recaller());

        $this->assertDatabaseMissing('sessions', ['id' => 'phone']);
        $this->assertDatabaseHas('sessions', ['id' => 'someone-else']);
        $this->assertNotSame('old-token', $member->fresh()->remember_token);
    }
}
