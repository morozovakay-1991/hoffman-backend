<?php

namespace Tests\Feature\Verification;

use App\Enums\GraduateStatus;
use App\Models\GraduateDirectory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationSubmitTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('api_token')->plainTextToken;

        return [$user, $token];
    }

    public function test_it_confirms_verification_when_a_matching_graduate_is_found(): void
    {
        GraduateDirectory::create([
            'last_name' => 'Petrov',
            'first_name' => 'Ivan',
            'phone' => '+7 (900) 123-45-67',
            'imported_at' => now(),
        ]);

        [$user, $token] = $this->actingUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/verification/submit', [
                'last_name' => 'Petrov',
                'first_name' => 'Ivan',
                'phone' => '89001234567',
            ]);

        $response->assertOk()
            ->assertJsonPath('verification_request.status', 'confirmed');

        $this->assertDatabaseHas('verification_requests', [
            'user_id' => $user->id,
            'status' => 'confirmed',
        ]);

        $this->assertSame(GraduateStatus::Confirmed, $user->fresh()->graduate_status);
    }

    public function test_it_sets_pending_status_when_no_match_is_found(): void
    {
        [$user, $token] = $this->actingUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/verification/submit', [
                'last_name' => 'Unknown',
                'first_name' => 'Person',
                'phone' => '89991234567',
            ]);

        $response->assertOk()
            ->assertJsonPath('verification_request.status', 'pending');

        $this->assertDatabaseHas('verification_requests', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->assertSame(GraduateStatus::Unverified, $user->fresh()->graduate_status);
    }

    public function test_it_matches_regardless_of_phone_formatting_and_name_case(): void
    {
        GraduateDirectory::create([
            'last_name' => 'Sidorova',
            'first_name' => 'Maria',
            'phone' => '+7 900 111 22 33',
            'imported_at' => now(),
        ]);

        [, $token] = $this->actingUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/verification/submit', [
                'last_name' => '  SIDOROVA ',
                'first_name' => 'maria',
                'phone' => '8 (900) 111-22-33',
            ]);

        $response->assertOk()
            ->assertJsonPath('verification_request.status', 'confirmed');
    }

    public function test_resubmitting_updates_the_existing_verification_request_instead_of_creating_a_new_one(): void
    {
        [$user, $token] = $this->actingUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/verification/submit', [
                'last_name' => 'Unknown',
                'first_name' => 'Person',
                'phone' => '89991234567',
            ])->assertOk()
            ->assertJsonPath('verification_request.status', 'pending');

        $this->assertDatabaseCount('verification_requests', 1);

        GraduateDirectory::create([
            'last_name' => 'Unknown',
            'first_name' => 'Person',
            'phone' => '89991234567',
            'imported_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/verification/submit', [
                'last_name' => 'Unknown',
                'first_name' => 'Person',
                'phone' => '89991234567',
            ]);

        $response->assertOk()
            ->assertJsonPath('verification_request.status', 'confirmed');

        $this->assertDatabaseCount('verification_requests', 1);
        $this->assertSame(GraduateStatus::Confirmed, $user->fresh()->graduate_status);
    }

    public function test_it_rejects_the_request_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/verification/submit', [
            'last_name' => 'Unknown',
            'first_name' => 'Person',
            'phone' => '89991234567',
        ]);

        $response->assertStatus(401);
    }

    public function test_a_second_account_claiming_the_same_directory_entry_goes_to_manual_review(): void
    {
        GraduateDirectory::create([
            'last_name' => 'Kuznetsova',
            'first_name' => 'Olga',
            'phone' => '+7 (900) 555-11-22',
            'imported_at' => now(),
        ]);

        $firstUser = User::factory()->create();

        $this->actingAs($firstUser, 'sanctum')
            ->postJson('/api/v1/verification/submit', [
                'last_name' => 'Kuznetsova',
                'first_name' => 'Olga',
                'phone' => '89005551122',
            ])
            ->assertOk()
            ->assertJsonPath('verification_request.status', 'confirmed')
            ->assertJsonPath('verification_request.is_duplicate', false);

        $this->assertSame(GraduateStatus::Confirmed, $firstUser->fresh()->graduate_status);

        $secondUser = User::factory()->create();

        $response = $this->actingAs($secondUser, 'sanctum')
            ->postJson('/api/v1/verification/submit', [
                'last_name' => 'Kuznetsova',
                'first_name' => 'Olga',
                'phone' => '89005551122',
            ]);

        $response->assertOk()
            ->assertJsonPath('verification_request.status', 'pending')
            ->assertJsonPath('verification_request.is_duplicate', true);

        $this->assertDatabaseHas('verification_requests', [
            'user_id' => $secondUser->id,
            'status' => 'pending',
        ]);

        $secondRequest = $secondUser->fresh()->verificationRequests()->latest('id')->first();
        $this->assertNotNull($secondRequest->duplicate_of_verification_request_id);
        $this->assertNotNull($secondRequest->graduate_directory_id);

        $this->assertSame(GraduateStatus::Unverified, $secondUser->fresh()->graduate_status);
    }
}
