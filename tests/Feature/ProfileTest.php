<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_member_sees_their_profile(): void
    {
        $user = User::factory()->create(['name' => 'Sara Ali', 'social_links' => ['twitter_url' => 'https://x.com/sara']]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Sara Ali')
            ->assertSee('https://x.com/sara');
    }

    public function test_member_updates_name_photo_and_links(): void
    {
        $user = User::factory()->create(['name' => 'Old']);

        $this->actingAs($user)->put('/profile', [
            'name' => '  New Name ',
            'avatar' => UploadedFile::fake()->image('me.jpg', 200, 200),
            'social_links' => [
                'telegram_url' => '@sara',
                'website_url' => 'example.com',
                'instagram_url' => 'https://instagram.com/sara',
                'youtube_url' => '',
                'unknown' => 'https://evil.test',
            ],
        ])->assertRedirect('/profile')->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame([
            'telegram_url' => 'https://t.me/sara',
            'website_url' => 'https://example.com',
            'instagram_url' => 'https://instagram.com/sara',
        ], $user->social_links);
        Storage::disk('public')->assertExists($user->avatar_url);
        $this->assertTrue($user->hasUploadedAvatar());
    }

    public function test_replacing_or_removing_the_photo_deletes_the_old_file(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/profile', ['name' => 'A', 'avatar' => UploadedFile::fake()->image('a.png', 100, 100)]);
        $first = $user->refresh()->avatar_url;

        $this->actingAs($user)->put('/profile', ['name' => 'A', 'avatar' => UploadedFile::fake()->image('b.png', 100, 100)]);
        $second = $user->refresh()->avatar_url;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAs($user)->put('/profile', ['name' => 'A', 'remove_avatar' => '1']);
        Storage::disk('public')->assertMissing($second);
        $this->assertNull($user->refresh()->avatar_url);
    }

    public function test_photo_from_a_social_sign_in_is_never_deleted_from_disk(): void
    {
        $user = User::factory()->create(['avatar_url' => 'https://example.com/a.png']);

        $this->actingAs($user)->put('/profile', ['name' => 'A', 'remove_avatar' => '1'])->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->avatar_url);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $user = User::factory()->create(['name' => 'Kept']);

        $this->actingAs($user)->put('/profile', [
            'name' => '',
            'avatar' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            'social_links' => ['website_url' => 'javascript://alert(1)'],
        ])->assertSessionHasErrors(['name', 'avatar', 'social_links.website_url']);

        $this->assertSame('Kept', $user->refresh()->name);
    }
}
