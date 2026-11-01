<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    /** A real 64x64 PNG, so type and size are verified from actual bytes. */
    private function pngDataUrl(int $side = 64): string
    {
        $image = imagecreatetruecolor($side, $side);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 80, 40));
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    public function test_user_can_upload_and_the_image_is_publicly_served(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/avatar', ['image' => $this->pngDataUrl()])
            ->assertOk();

        $this->assertMatchesRegularExpression("#^/api/users/{$user->id}/avatar\\?v=\\d+$#", $response->json('avatar'));
        $this->assertSame($response->json('avatar'), $user->fresh()->avatar);

        $served = $this->get("/api/users/{$user->id}/avatar");
        $served->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('immutable', $served->headers->get('Cache-Control'));
        $this->assertSame(64, getimagesizefromstring($served->getContent())[0]);
    }

    public function test_replacing_the_avatar_changes_the_version_url(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/profile/avatar', ['image' => $this->pngDataUrl()])->assertOk();
        $this->assertDatabaseCount('user_avatars', 1);

        $this->travel(5)->seconds();
        $second = $this->actingAs($user, 'sanctum')->postJson('/api/profile/avatar', ['image' => $this->pngDataUrl(96)])->assertOk();

        $this->assertDatabaseCount('user_avatars', 1);
        $this->assertSame($second->json('avatar'), $user->fresh()->avatar);
    }

    public function test_user_can_remove_the_avatar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/profile/avatar', ['image' => $this->pngDataUrl()])->assertOk();

        $this->actingAs($user, 'sanctum')->deleteJson('/api/profile/avatar')
            ->assertOk()
            ->assertJsonPath('avatar', null);

        $this->assertDatabaseCount('user_avatars', 0);
        $this->get("/api/users/{$user->id}/avatar")->assertNotFound();
    }

    public function test_rejects_non_images_oversized_and_out_of_range_uploads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Claimed as PNG, actually text.
        $this->postJson('/api/profile/avatar', ['image' => 'data:image/png;base64,'.base64_encode('not an image')])
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        // Not a data URL at all.
        $this->postJson('/api/profile/avatar', ['image' => 'https://example.com/a.png'])
            ->assertUnprocessable();

        // Too small.
        $this->postJson('/api/profile/avatar', ['image' => $this->pngDataUrl(8)])
            ->assertUnprocessable();

        // Over the byte limit.
        $this->postJson('/api/profile/avatar', ['image' => 'data:image/png;base64,'.base64_encode(str_repeat('a', 300000))])
            ->assertUnprocessable();

        $this->assertDatabaseCount('user_avatars', 0);
    }

    public function test_upload_requires_authentication(): void
    {
        $this->postJson('/api/profile/avatar', ['image' => $this->pngDataUrl()])->assertUnauthorized();
        $this->deleteJson('/api/profile/avatar')->assertUnauthorized();
    }

    public function test_avatars_appear_for_lobby_players_and_host(): void
    {
        $host = User::factory()->create(['avatar' => '/api/users/1/avatar?v=1']);

        $this->actingAs($host, 'sanctum')
            ->postJson('/api/lobbies', ['game_type' => 'poker'])
            ->assertCreated()
            ->assertJsonPath('players.0.avatar', '/api/users/1/avatar?v=1')
            ->assertJsonPath('host.avatar', '/api/users/1/avatar?v=1');
    }
}
