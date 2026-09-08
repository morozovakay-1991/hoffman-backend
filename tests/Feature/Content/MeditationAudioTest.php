<?php

namespace Tests\Feature\Content;

use App\Enums\GraduateStatus;
use App\Models\Meditation;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeditationAudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_get_a_presigned_audio_url_for_a_free_meditation(): void
    {
        $meditation = Meditation::factory()->free()->create(['audio_path' => 'meditations/free.mp3']);

        Storage::shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();

        Storage::shouldReceive('temporaryUrl')
            ->once()
            ->with('meditations/free.mp3', \Mockery::on(fn ($expiresAt) => $expiresAt instanceof \DateTimeInterface))
            ->andReturn('https://minio.local/hoffman/meditations/free.mp3?signature=abc');

        $response = $this->getJson("/api/v1/meditations/{$meditation->id}/audio");

        $response->assertOk()
            ->assertJsonPath('data.url', 'https://minio.local/hoffman/meditations/free.mp3?signature=abc')
            ->assertJsonStructure(['data' => ['url', 'expires_at']]);
    }

    public function test_guest_is_denied_audio_for_a_paid_meditation(): void
    {
        $meditation = Meditation::factory()->create();

        Storage::shouldReceive('disk')->never();

        $response = $this->getJson("/api/v1/meditations/{$meditation->id}/audio");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_user_without_subscription_is_denied_audio_for_a_paid_meditation(): void
    {
        $user = User::factory()->create();
        $meditation = Meditation::factory()->create();

        Storage::shouldReceive('disk')->never();

        $response = $this->actingAsApiUser($user)
            ->getJson("/api/v1/meditations/{$meditation->id}/audio");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_subscribed_user_can_get_a_presigned_audio_url_for_a_paid_meditation(): void
    {
        $user = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();
        Subscription::factory()->for($user)->create(['expires_at' => now()->addYear()]);
        $meditation = Meditation::factory()->create(['audio_path' => 'meditations/paid.mp3']);

        Storage::shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();

        Storage::shouldReceive('temporaryUrl')
            ->once()
            ->with('meditations/paid.mp3', \Mockery::on(fn ($expiresAt) => $expiresAt instanceof \DateTimeInterface))
            ->andReturn('https://minio.local/hoffman/meditations/paid.mp3?signature=def');

        $response = $this->actingAsApiUser($user)
            ->getJson("/api/v1/meditations/{$meditation->id}/audio");

        $response->assertOk()
            ->assertJsonPath('data.url', 'https://minio.local/hoffman/meditations/paid.mp3?signature=def');
    }

    public function test_unpublished_meditation_audio_is_not_found(): void
    {
        $meditation = Meditation::factory()->free()->unpublished()->create();

        Storage::shouldReceive('disk')->never();

        $response = $this->getJson("/api/v1/meditations/{$meditation->id}/audio");

        $response->assertStatus(404);
    }
}
